<?php

// KloxoNext - subdomains (domain.dtype = 'subdomain') do not get a zone of their own:
// their DNS records are rendered inside the zone of the parent domain.

function kn_dns_is_valid_name($name)
{
	return is_string($name) && preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $name);
}

/** Parent domain of a subdomain, or null when $nname is not a subdomain. */
function kn_dns_get_subdomain_parent($nname)
{
	if (!kn_dns_is_valid_name($nname)) {
		return null;
	}

	$db = new Sqlite(null, 'domain');
	$r = $db->getRowsWhere("nname = '" . kn_sql_escape($nname) . "' AND dtype = 'subdomain'", array('subdomain_parent'));

	if (!empty($r[0]['subdomain_parent'])) {
		return $r[0]['subdomain_parent'];
	}

	return null;
}

/** Names of all subdomains of $parent. */
function kn_dns_get_subdomain_list($parent = null)
{
	$db = new Sqlite(null, 'domain');

	if ($parent === null) {
		$r = $db->getRowsWhere("dtype = 'subdomain'", array('nname'));
	} else {
		if (!kn_dns_is_valid_name($parent)) {
			return array();
		}

		$r = $db->getRowsWhere("dtype = 'subdomain' AND subdomain_parent = '" . kn_sql_escape($parent) . "'", array('nname'));
	}

	$ret = array();

	foreach ((array)$r as $row) {
		if (!empty($row['nname'])) {
			$ret[] = $row['nname'];
		}
	}

	return $ret;
}

/**
 * DNS records of every subdomain of $parent, rewritten relative to the parent zone
 * (hostname 'www' of blog.example.com becomes 'www.blog' in example.com).
 */
function kn_dns_get_subdomain_records($parent)
{
	$ret = array();

	foreach (kn_dns_get_subdomain_list($parent) as $sub) {
		$suffix = ".{$parent}";

		if (substr($sub, -strlen($suffix)) !== $suffix) {
			continue;
		}

		$label = substr($sub, 0, -strlen($suffix));

		$dns = new Dns(null, null, $sub);
		$dns->get();

		// zone removed (subdomain being deleted) or never created
		if ($dns->dbaction === 'add' || empty($dns->dns_record_a)) {
			continue;
		}

		foreach ($dns->dns_record_a as $k => $o) {
			// the parent zone already carries the delegation/NS data
			if ($o->ttype === 'ns') {
				continue;
			}

			$h = (string)$o->hostname;

			if (($h === '') || ($h === '@') || ($h === '__base__') || ($h === $sub) || ($h === "{$sub}.")) {
				$h = $label;
			} else {
				$h = rtrim($h, '.');
				$h = str_replace(".__base__", '', $h);

				if (substr($h, -strlen(".{$sub}")) === ".{$sub}") {
					$h = substr($h, 0, -strlen(".{$sub}"));
				}

				// nameserver host records belong to the parent only
				if (in_array($h, array('ns1', 'ns2', 'ns3', 'ns4'), true)) {
					continue;
				}

				$h = "{$h}.{$label}";
			}

			$n = clone $o;
			$n->hostname = $h;

			$p = (string)$o->param;

			switch ($o->ttype) {
				case 'cn':
				case 'cname':
					// relative targets ('__base__', 'mail') point inside the subdomain
					if ($p === '__base__') {
						$n->param = $label;
					} elseif (($p !== '') && (strpos(rtrim($p, '.'), '.') === false)) {
						$n->param = "{$p}.{$label}";
					}

					break;
				case 'fcname':
				case 'mx':
				case 'srv':
					if ($p === '__base__') {
						$n->param = $sub;
					}

					break;
				case 'txt':
					$n->param = str_replace(array('<%domain%>', '__base__'), $sub, $p);

					break;
			}

			$ret["kn_sub_{$label}_{$k}"] = $n;
		}
	}

	return $ret;
}

/** Parent records plus subdomain records; a parent record with the same type and host wins. */
function kn_dns_merge_records($records, $subrecords)
{
	$norm = function ($o) {
		$t = in_array($o->ttype, array('cn', 'cname', 'fcname'), true) ? 'cname' : $o->ttype;

		return strtolower("{$t}|{$o->hostname}");
	};

	$seen = array();

	foreach ((array)$records as $o) {
		if ($o->ttype !== 'mx') {
			$seen[$norm($o)] = true;
		}
	}

	foreach ((array)$subrecords as $k => $o) {
		if (($o->ttype !== 'mx') && isset($seen[$norm($o)])) {
			continue;
		}

		$records[$k] = $o;
	}

	return $records;
}

/** After a subdomain's DNS changed: re-render the zone of its parent domain. */
function kn_dns_refresh_parent_zone($parent)
{
	if (!kn_dns_is_valid_name($parent)) {
		return;
	}

	$dns = new Dns(null, null, $parent);
	$dns->get();

	if ($dns->dbaction === 'add') {
		return;
	}

	$dns->setUpdateSubaction('full_update');
	$dns->was();
}

/** Refresh the parent zone once, at the end of the request (all rows are saved by then). */
function kn_dns_schedule_parent_refresh($parent)
{
	static $queued = array();

	if (!kn_dns_is_valid_name($parent) || isset($queued[$parent])) {
		return;
	}

	$queued[$parent] = true;

	register_shutdown_function(function () use ($parent) {
		try {
			kn_dns_refresh_parent_zone($parent);
		} catch (Exception $e) {
			log_log("dns_subdomain", "refresh of {$parent} failed: " . $e->getMessage());
		}
	});
}

/**
 * Drivers whose zone files are rendered. BIND and YADIFA read the zones written by
 * the NSD template, so 'nsd' is rendered with them even where it is not offered
 * (Ubuntu: dns = none, bind) - otherwise new domains got no zone file there.
 */
function kn_dns_render_drivers()
{
	$list = (array)getAllDnsDriverList();

	if ((in_array('bind', $list, true) || in_array('yadifa', $list, true)) && !in_array('nsd', $list, true)) {
		$list[] = 'nsd';
	}

	return $list;
}

/** A zone name: at least two labels, letters/digits/hyphens (no panel object names) */
function kn_dns_is_zone_name($name)
{
	return is_string($name) && (strpos($name, '.') !== false)
		&& preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z0-9][a-z0-9-]*[a-z0-9]$/i', $name);
}

/**
 * Remove DNS rows that belong to no domain: a DNS object opened below a panel page
 * (e.g. 'cfdomain') was saved under that page's name. Only names without a dot.
 */
function kn_dns_remove_stray_rows()
{
	static $done = false;

	if ($done) {
		return;
	}

	$done = true;

	$db = new Sqlite(null, 'dns');
	$rows = $db->getRowsWhere("nname NOT LIKE '%.%'", array('nname'));

	foreach ((array)$rows as $r) {
		if (empty($r['nname']) || !preg_match('/^[a-z0-9_-]+$/i', $r['nname'])) {
			continue;
		}

		$db->rawQuery("DELETE FROM dns WHERE nname = '" . kn_sql_escape($r['nname']) . "'");
		log_log("dns_stray", "removed DNS row '{$r['nname']}' (no domain)");
	}
}
