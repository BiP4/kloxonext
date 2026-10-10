<?php

// KloxoNext - Cloudflare DNS (and proxy) for domains, driven through the Cloudflare API.
//
//   - every client may connect his own Cloudflare account with an API token
//     (permissions: Zone:Read + DNS:Edit; Zone:Edit as well to let the panel create zones)
//   - every domain chooses its DNS service: local BIND or Cloudflare
//   - with Cloudflare the records of the panel DNS page (plus the subdomains merged into it)
//     are synchronised into the zone; only records created by the panel (comment
//     'KloxoNext') are changed or deleted, records added by hand in Cloudflare stay
//
// Storage (root only, never part of the repository or of updates):
//   etc/conf/cloudflare/accounts/<client>.json   {"token": "...", "account_id": "...", "verified": "..."}
//   etc/conf/cloudflare/domains/<domain>.json    {"provider": "cloudflare", "client": "...", "proxied": [...],
//                                                 "zone_id": "...", "nameservers": [...], "status": "...", "synced": "..."}

define('KN_CF_DIR', '/usr/local/lxlabs/kloxo/etc/conf/cloudflare');
define('KN_CF_COMMENT', 'KloxoNext');

class KnCloudflare
{
	public $token;
	public $error = '';

	function __construct($token)
	{
		$this->token = (string)$token;
	}

	/** API base; tests may point it to a mock with etc/conf/cloudflare/api_base */
	static function base()
	{
		$f = KN_CF_DIR . '/api_base';

		if (file_exists($f)) {
			$b = trim((string)file_get_contents($f));

			if (preg_match('~^https?://[A-Za-z0-9.:/_-]+$~', $b)) {
				return rtrim($b, '/');
			}
		}

		return 'https://api.cloudflare.com/client/v4';
	}

	/** @return array|null decoded 'result' (null on error, see $this->error) */
	function request($method, $path, $body = null, &$info = null)
	{
		$this->error = '';

		$ch = curl_init(self::base() . $path);
		$headers = array('Authorization: Bearer ' . $this->token, 'Content-Type: application/json');

		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
		));

		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}

		$raw = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($raw === false) {
			$this->error = 'Cloudflare API not reachable: ' . curl_error($ch);
			curl_close($ch);

			return null;
		}

		curl_close($ch);

		$res = json_decode($raw, true);

		if (!is_array($res) || empty($res['success'])) {
			$msg = array();

			foreach ((isset($res['errors']) ? (array)$res['errors'] : array()) as $e) {
				$msg[] = (isset($e['code']) ? "[{$e['code']}] " : '') . (isset($e['message']) ? $e['message'] : '');
			}

			$this->error = $msg ? implode('; ', $msg) : "HTTP {$code}";

			return null;
		}

		$info = isset($res['result_info']) ? $res['result_info'] : null;

		return isset($res['result']) ? $res['result'] : array();
	}

	/** Token check: returns a short description of the token or null */
	function verify()
	{
		$r = $this->request('GET', '/user/tokens/verify');

		if ($r !== null) {
			return isset($r['status']) ? "token {$r['status']}" : 'token valid';
		}

		// account owned tokens are verified per account: fall back to listing zones
		$z = $this->request('GET', '/zones?per_page=1');

		return ($z !== null) ? 'token valid' : null;
	}

	function findZone($name)
	{
		$r = $this->request('GET', '/zones?name=' . rawurlencode($name));

		return ($r && isset($r[0]['id'])) ? $r[0] : null;
	}

	function createZone($name, $accountId)
	{
		$body = array('name' => $name, 'type' => 'full');

		if ($accountId) {
			$body['account'] = array('id' => $accountId);
		}

		return $this->request('POST', '/zones', $body);
	}

	function listRecords($zoneId)
	{
		$all = array();
		$page = 1;

		do {
			$r = $this->request('GET', "/zones/{$zoneId}/dns_records?per_page=500&page={$page}", null, $info);

			if ($r === null) {
				return null;
			}

			$all = array_merge($all, $r);
			$pages = isset($info['total_pages']) ? (int)$info['total_pages'] : 1;
			$page++;
		} while ($page <= $pages);

		return $all;
	}
}

// ------------------------------------------------------------------ storage

function kn_cf_valid_name($n)
{
	return is_string($n) && preg_match('/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i', $n);
}

function kn_cf_read($kind, $name)
{
	if (!kn_cf_valid_name($name)) {
		return array();
	}

	$f = KN_CF_DIR . "/{$kind}/{$name}.json";
	$d = file_exists($f) ? json_decode((string)file_get_contents($f), true) : null;

	return is_array($d) ? $d : array();
}

function kn_cf_write($kind, $name, $data)
{
	if (!kn_cf_valid_name($name)) {
		return false;
	}

	$dir = KN_CF_DIR . "/{$kind}";

	if (!is_dir($dir)) {
		mkdir($dir, 0700, true);
		@chmod(KN_CF_DIR, 0700);
	}

	$f = "{$dir}/{$name}.json";
	file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	chmod($f, 0600);

	return true;
}

function kn_cf_delete($kind, $name)
{
	if (kn_cf_valid_name($name)) {
		@unlink(KN_CF_DIR . "/{$kind}/{$name}.json");
	}
}

/** Cloudflare settings of a domain, with the defaults */
function kn_cf_domain($domain)
{
	$d = kn_cf_read('domains', $domain);

	return $d + array('provider' => 'local', 'client' => '', 'proxied' => array('@', 'www'),
		'zone_id' => '', 'nameservers' => array(), 'status' => '', 'synced' => '');
}

function kn_cf_uses_cloudflare($domain)
{
	$d = kn_cf_domain($domain);

	return ($d['provider'] === 'cloudflare');
}

// ------------------------------------------------------------------ records

/**
 * Panel DNS records (dns_record_a objects, '__base__' notation) -> Cloudflare records.
 * $proxied: relative names ('@', 'www', 'shop') whose A/AAAA/CNAME go through the proxy.
 */
function kn_cf_map_records($domain, $records, $proxied, $ttl)
{
	$fq = function ($h) use ($domain) {
		$h = rtrim(trim((string)$h), '.');

		if (($h === '') || ($h === '@') || ($h === '__base__') || ($h === $domain)) {
			return $domain;
		}

		$h = str_replace('.__base__', '', $h);

		if (substr($h, -strlen(".{$domain}")) === ".{$domain}") {
			return $h;
		}

		return "{$h}.{$domain}";
	};

	// relative target ('mail', '__base__') -> FQDN; absolute stays
	$target = function ($p) use ($domain, $fq) {
		$p = rtrim(trim((string)$p), '.');

		if (($p === '__base__') || ($p === '')) {
			return $domain;
		}

		return (strpos($p, '.') === false) ? $fq($p) : $p;
	};

	$rel = function ($name) use ($domain) {
		return ($name === $domain) ? '@' : substr($name, 0, -strlen(".{$domain}"));
	};

	$ttl = max(60, (int)$ttl);
	$out = array();

	foreach ((array)$records as $o) {
		$t = strtolower((string)$o->ttype);
		$name = $fq(isset($o->hostname) ? $o->hostname : '');
		$p = isset($o->param) ? (string)$o->param : '';
		$r = null;

		switch ($t) {
			case 'a':
			case 'aaaa':
				$r = array('type' => strtoupper($t), 'name' => $name, 'content' => trim($p));
				break;

			case 'cn':
				// panel 'cn' targets are relative to the domain ('mail', 'mail.blog', '__base__')
				$pp = rtrim(trim($p), '.');
				$abs = (substr($p, -1) === '.') || ($pp === $domain) || (substr($pp, -strlen(".{$domain}")) === ".{$domain}");
				$r = array('type' => 'CNAME', 'name' => $name, 'content' => $abs ? $pp : $fq($pp === '' ? '__base__' : $pp));
				break;

			case 'cname':
			case 'fcname':
				$r = array('type' => 'CNAME', 'name' => $name, 'content' => $target($p));
				break;

			case 'mx':
				$r = array('type' => 'MX', 'name' => $name, 'content' => $target($p),
					'priority' => (int)(isset($o->priority) ? $o->priority : 10));
				break;

			case 'txt':
				$r = array('type' => 'TXT', 'name' => $name,
					'content' => str_replace(array('<%domain%>', '__base__'), $domain, $p));
				break;

			case 'ns':
				// the apex NS records belong to Cloudflare; delegations of subzones are kept
				if ($name === $domain) {
					continue 2;
				}

				$r = array('type' => 'NS', 'name' => $name, 'content' => $target($p));
				break;

			case 'srv':
				$r = array('type' => 'SRV', 'name' => $name, 'data' => array(
					'priority' => (int)(isset($o->priority) ? $o->priority : 0),
					'weight' => (int)(isset($o->weight) ? $o->weight : 0),
					'port' => (int)(isset($o->port) ? $o->port : 0),
					'target' => $target($p)));
				break;

			case 'caa':
				$r = array('type' => 'CAA', 'name' => $name, 'data' => array(
					'flags' => (int)(isset($o->flag) ? $o->flag : 0),
					'tag' => (string)(isset($o->tag) ? $o->tag : 'issue'),
					'value' => $p));
				break;
		}

		if (!$r || (isset($r['content']) && $r['content'] === '')) {
			continue;
		}

		$px = in_array($r['type'], array('A', 'AAAA', 'CNAME'), true) && in_array($rel($name), (array)$proxied, true);
		$r['proxied'] = $px;
		$r['ttl'] = $px ? 1 : $ttl;
		$r['comment'] = KN_CF_COMMENT;

		$out[] = $r;
	}

	return $out;
}

/** identity of a record for matching desired vs existing */
function kn_cf_record_key($r)
{
	$v = isset($r['data']) ? json_encode($r['data']) : (isset($r['content']) ? strtolower(rtrim($r['content'], '.')) : '');

	if ($r['type'] === 'TXT') {
		$v = trim((string)$r['content'], '"');
	}

	return strtoupper($r['type']) . '|' . strtolower($r['name']) . '|' . $v;
}

/**
 * Bring the zone of $domain to the panel records. Updates etc/conf/cloudflare/domains/<domain>.json.
 * @return string summary ('OK: ...' or 'ERROR: ...')
 */
function kn_cf_sync_domain($domain)
{
	$cfg = kn_cf_domain($domain);

	if ($cfg['provider'] !== 'cloudflare') {
		return 'OK: local DNS';
	}

	$acc = kn_cf_read('accounts', $cfg['client']);

	if (empty($acc['token'])) {
		$cfg['status'] = 'ERROR: the Cloudflare account of ' . $cfg['client'] . ' is not connected';
		kn_cf_write('domains', $domain, $cfg);

		return $cfg['status'];
	}

	$api = new KnCloudflare($acc['token']);

	// zone: existing one or created in the account
	$zone = $cfg['zone_id'] ? $api->request('GET', "/zones/{$cfg['zone_id']}") : null;

	if (!$zone) {
		$zone = $api->findZone($domain);
	}

	if (!$zone) {
		$zone = $api->createZone($domain, isset($acc['account_id']) ? $acc['account_id'] : '');

		if (!$zone) {
			$cfg['status'] = 'ERROR: zone not found and not created: ' . $api->error;
			kn_cf_write('domains', $domain, $cfg);

			return $cfg['status'];
		}
	}

	$cfg['zone_id'] = $zone['id'];
	$cfg['nameservers'] = isset($zone['name_servers']) ? (array)$zone['name_servers'] : array();
	$cfg['zone_status'] = isset($zone['status']) ? $zone['status'] : '';

	// desired records: the panel DNS page plus the subdomains merged into it
	$dns = new Dns(null, null, $domain);
	$dns->get();

	if ($dns->dbaction === 'add') {
		$cfg['status'] = 'ERROR: no DNS records for this domain in the panel';
		kn_cf_write('domains', $domain, $cfg);

		return $cfg['status'];
	}

	$records = (array)$dns->dns_record_a;

	if (function_exists('kn_dns_get_subdomain_records')) {
		$records = kn_dns_merge_records($records, kn_dns_get_subdomain_records($domain));
	}

	$want = kn_cf_map_records($domain, $records, $cfg['proxied'], isset($dns->ttl) ? $dns->ttl : 3600);

	$have = $api->listRecords($zone['id']);

	if ($have === null) {
		$cfg['status'] = 'ERROR: cannot read the records: ' . $api->error;
		kn_cf_write('domains', $domain, $cfg);

		return $cfg['status'];
	}

	$managed = array();

	foreach ($have as $h) {
		if (isset($h['comment']) && ($h['comment'] === KN_CF_COMMENT)) {
			$managed[kn_cf_record_key($h)][] = $h;
		}
	}

	$created = $updated = $deleted = 0;
	$errors = array();
	$todo = array();

	// 1. identical records: keep, or update proxy / TTL / priority
	foreach ($want as $w) {
		$k = kn_cf_record_key($w);

		if (empty($managed[$k])) {
			$todo[] = $w;

			continue;
		}

		$h = array_shift($managed[$k]);

		if (empty($managed[$k])) {
			unset($managed[$k]);
		}

		$same = ((bool)$h['proxied'] === $w['proxied']) && ((int)$h['ttl'] === $w['ttl'])
			&& (!isset($w['priority']) || ((int)(isset($h['priority']) ? $h['priority'] : -1) === $w['priority']));

		if (!$same) {
			if ($api->request('PATCH', "/zones/{$zone['id']}/dns_records/{$h['id']}", $w) === null) {
				$errors[] = "{$w['type']} {$w['name']}: {$api->error}";
			} else {
				$updated++;
			}
		}
	}

	// 2. same type and name, new value: change the record in place
	$left = array();

	foreach ($managed as $list) {
		foreach ($list as $h) {
			$left[strtoupper($h['type']) . '|' . strtolower($h['name'])][] = $h;
		}
	}

	$create = array();

	foreach ($todo as $w) {
		$tn = strtoupper($w['type']) . '|' . strtolower($w['name']);

		if (empty($left[$tn])) {
			$create[] = $w;

			continue;
		}

		$h = array_shift($left[$tn]);

		if ($api->request('PATCH', "/zones/{$zone['id']}/dns_records/{$h['id']}", $w) === null) {
			$errors[] = "{$w['type']} {$w['name']}: {$api->error}";
		} else {
			$updated++;
		}
	}

	// 3. panel records that were removed (before creating: a name may change type)
	foreach ($left as $list) {
		foreach ($list as $h) {
			if ($api->request('DELETE', "/zones/{$zone['id']}/dns_records/{$h['id']}") === null) {
				$errors[] = "delete {$h['type']} {$h['name']}: {$api->error}";
			} else {
				$deleted++;
			}
		}
	}

	// 4. new records
	foreach ($create as $w) {
		if ($api->request('POST', "/zones/{$zone['id']}/dns_records", $w) === null) {
			$errors[] = "{$w['type']} {$w['name']}: {$api->error}";
		} else {
			$created++;
		}
	}

	$cfg['synced'] = date('Y-m-d H:i:s');
	$cfg['status'] = ($errors ? 'ERROR: ' . implode(' | ', array_slice($errors, 0, 5)) . ' - ' : 'OK: ')
		. count($want) . " records, {$created} created, {$updated} updated, {$deleted} deleted";

	kn_cf_write('domains', $domain, $cfg);

	return $cfg['status'];
}

/** Background sync (the DNS rebuild must not wait for the API) */
function kn_cf_schedule_sync($domain)
{
	if (!kn_cf_valid_name($domain) || !kn_cf_uses_cloudflare($domain)) {
		return;
	}

	exec("cd /usr/local/lxlabs/kloxo/httpdocs && lxphp.exe ../bin/misc/cloudflare-sync.php "
		. escapeshellarg($domain) . " >> /usr/local/lxlabs/kloxo/log/cloudflare.log 2>&1 &");
}

/** Sync after the current request (its records are saved by then), once per domain */
function kn_cf_schedule_sync_after_request($domain)
{
	static $queued = array();

	if (!kn_cf_valid_name($domain) || isset($queued[$domain])) {
		return;
	}

	$queued[$domain] = true;

	register_shutdown_function(function () use ($domain) {
		kn_cf_schedule_sync($domain);
	});
}
