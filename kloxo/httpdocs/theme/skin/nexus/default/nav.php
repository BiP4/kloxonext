<?php

/**
 * KloxoNext 'nexus' skin - sidebar navigation.
 *
 * The menu content (and every permission rule deciding what a given admin,
 * reseller, customer or mail user may see) lives in the simplicity skin
 * menu.php. Instead of duplicating 1100+ lines, we render it into a buffer
 * and convert its mega-menu markup into a structured list of sections.
 */

function kn_nav_icon($name)
{
	// 24x24 stroke icons (MIT-licensed shapes in the style of Lucide)
	$p = array(
		'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		'admin'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18.5 14.5A6.5 6.5 0 0 1 21.5 20"/>',
		'resource' => '<path d="M4 4h16v6H4z"/><path d="M4 14h16v6H4z"/><path d="M8 7h.01M8 17h.01"/>',
		'advanced' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M1 14h6M9 8h6M17 16h6"/>',
		'server'   => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'security' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'task'     => '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 2v4M16 2v4M8 11h8M8 15h5"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7v.5"/><path d="M12 17h.01"/>',
		'logout'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17-5-5 5-5"/><path d="M5 12h12"/>',
		'web'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/>',
		'db'       => '<ellipse cx="12" cy="5.5" rx="8" ry="2.5"/><path d="M4 5.5v13c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5v-13"/><path d="M4 12c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5"/>',
		'dns'      => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5l3 8M16 7.5l-3 8M8.5 6h7"/>',
		'backup'   => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/>',
		'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
		'more'     => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
		'dot'      => '<circle cx="12" cy="12" r="3"/>',
	);

	$d = isset($p[$name]) ? $p[$name] : $p['dot'];

	return '<svg class="kn-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
		. 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
}

/**
 * @return array list of sections: ['title', 'icon', 'href'|null, 'groups' => [['label', 'links' => [['text','href']]]]]
 */
function kn_nav_sections($menuhtml, $login)
{
	$icons = array(
		'home' => 'home', 'administration' => 'admin', 'resource' => 'resource', 'advanced' => 'advanced',
		'server' => 'server', 'security' => 'security', 'webmailanddb' => 'mail', 'task' => 'task',
	);

	$titleToIcon = array();

	foreach ($icons as $kw => $icon) {
		$titleToIcon[mb_strtolower(trim($login->getKeywordUc($kw)))] = $icon;
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="utf-8"?><div>' . $menuhtml . '</div>');
	libxml_clear_errors();

	$xp = new DOMXPath($doc);
	$sections = array();

	foreach ($xp->query("//ul[contains(@class,'menuTemplate2')]/li") as $li) {
		$top = $xp->query("./a", $li)->item(0);

		if (!$top) {
			continue;
		}

		$title = trim($top->textContent);
		$href = $top->getAttribute('href');

		// help / logout live in the top bar
		if ($title === '' || stripos($href, 'logout') !== false || stripos($top->getAttribute('onclick'), 'infomsg') !== false) {
			continue;
		}

		$section = array(
			'title' => $title,
			'icon' => isset($titleToIcon[mb_strtolower($title)]) ? $titleToIcon[mb_strtolower($title)] : 'dot',
			'href' => (strpos($href, 'javascript') === 0) ? null : $href,
			'groups' => array(),
		);

		$group = array('label' => '', 'links' => array());
		$seen = array();

		// walk the dropdown in document order: <b> starts a group, <a> adds a link
		foreach ($xp->query(".//div[contains(@class,'drop')]//b | .//div[contains(@class,'drop')]//a", $li) as $n) {
			if ($n->nodeName === 'b') {
				if (!empty($group['links'])) {
					$section['groups'][] = $group;
				}

				$group = array('label' => trim($n->textContent), 'links' => array());

				continue;
			}

			$h = $n->getAttribute('href');
			$t = trim(preg_replace('/\s+/u', ' ', $n->textContent));

			if ($t === '' || $h === '' || strpos($h, 'javascript') === 0 || isset($seen[$h . '|' . $t])) {
				continue;
			}

			$seen[$h . '|' . $t] = true;
			$group['links'][] = array('text' => $t, 'href' => $h, 'target' => $n->getAttribute('target'));
		}

		if (!empty($group['links'])) {
			$section['groups'][] = $group;
		}

		$sections[] = $section;
	}

	return $sections;
}

/** Query keys of a menu link: action, subaction, list class, object classes, dttype value. */
function kn_nav_key($href)
{
	parse_str(parse_url(html_entity_decode($href), PHP_URL_QUERY) ?? '', $q);

	return array(
		'a'  => isset($q['frm_action']) ? $q['frm_action'] : '',
		'sa' => isset($q['frm_subaction']) ? $q['frm_subaction'] : '',
		'c'  => isset($q['frm_o_cname']) ? $q['frm_o_cname'] : '',
		'o0' => isset($q['frm_o_o'][0]['class']) ? $q['frm_o_o'][0]['class'] : '',
		'o1' => isset($q['frm_o_o'][1]['class']) ? $q['frm_o_o'][1]['class'] : '',
		'dt' => isset($q['frm_dttype']['val']) ? $q['frm_dttype']['val'] : '',
	);
}

/**
 * KloxoNext - a shorter sidebar: the useful options in task oriented sections with
 * clear names; everything else stays reachable under "More" (grouped by its old
 * section). Only links the original menu offers to this login are used, so the
 * per-role permissions of menu.php are unchanged.
 */
function kn_nav_curate($sections)
{
	// section => [icon, [[label, conditions on kn_nav_key()], ...]]
	$layout = array(
		'Websites' => array('web', array(
			array('All Domains', array('c' => 'all_domain')),
			array('Domains', array('a' => 'list', 'c' => 'domain')),
			array('Subdomains', array('a' => 'list', 'c' => 'subdomain')),
			array('All Pointer Domains', array('c' => 'all_addondomain')),
			array('Pointer Domains', array('a' => 'list', 'c' => 'addondomain')),
			array('Default Domain', array('sa' => 'default_domain')),
			array('All SSL Certificates', array('c' => 'all_sslcert')),
			array('SSL Certificates', array('a' => 'list', 'c' => 'sslcert')),
			array('All FTP Users', array('c' => 'all_ftpuser')),
			array('FTP Users', array('a' => 'list', 'c' => 'ftpuser')),
			array('File Manager', array('a' => 'show', 'o0' => 'ffile')),
			array('All Cron Tasks', array('c' => 'all_cron')),
			array('Cron Tasks', array('a' => 'list', 'c' => 'cron')),
		)),
		'Clients' => array('admin', array(
			array('All Clients', array('c' => 'all_client')),
			array('My Clients', array('a' => 'list', 'c' => 'client')),
			array('Add Customer', array('a' => 'addform', 'c' => 'client', 'dt' => 'customer')),
			array('Add Reseller', array('a' => 'addform', 'c' => 'client', 'dt' => 'reseller')),
			array('Resource Plans', array('c' => 'resourceplan')),
			array('Auxiliary Logins', array('a' => 'list', 'c' => 'auxiliary')),
			array('Messages', array('c' => 'smessage')),
		)),
		'Mail' => array('mail', array(
			array('All Mail Accounts', array('c' => 'all_mailaccount')),
			array('Mail Accounts', array('a' => 'list', 'c' => 'mailaccount')),
			array('All Mail Forwards', array('c' => 'all_mailforward')),
			array('All Mailing Lists', array('c' => 'all_mailinglist')),
			array('Mail Server Settings', array('o1' => 'servermail', 'sa' => 'update')),
			array('Mail Queue', array('c' => 'mailqueue')),
			array('Whitelist IPs', array('c' => 'mail_graylist_wlist_a')),
		)),
		'Databases' => array('db', array(
			array('All Databases', array('c' => 'all_mysqldb')),
			array('MySQL Databases', array('a' => 'list', 'c' => 'mysqldb')),
			array('Database Admins', array('c' => 'dbadmin')),
			array('MySQL Password Reset', array('sa' => 'mysqlpasswordreset')),
		)),
		'DNS' => array('dns', array(
			array('DNS Templates', array('c' => 'dnstemplate')),
			array('Reverse DNS (custom zones)', array('o1' => 'dnscustom')),
			array('Cloudflare Account', array('o0' => 'cfaccount')),
		)),
		'Server' => array('server', array(
			array('Services', array('c' => 'service')),
			array('Processes', array('c' => 'process')),
			array('Web Server', array('o1' => 'serverweb')),
			array('PHP Configuration', array('a' => 'show', 'o1' => 'phpini')),
			array('PHP Advanced Configuration', array('sa' => 'extraedit', 'o1' => 'phpini')),
			array('PHP Modules', array('c' => 'phpmodule')),
			array('FTP Server', array('o1' => 'serverftp')),
			array('Switch Programs', array('sa' => 'switchprogram')),
			array('IP Addresses', array('a' => 'list', 'c' => 'ipaddress')),
			array('Timezone', array('sa' => 'timezone')),
			array('Log Manager', array('o1' => 'llog')),
			array('Server File Manager', array('a' => 'show', 'o0' => 'pserver', 'o1' => 'ffile')),
			array('SSH Terminal', array('o1' => 'sshclient')),
			array('Reboot', array('sa' => 'reboot')),
			array('Power Off', array('sa' => 'poweroff')),
		)),
		'Security' => array('security', array(
			array('Firewall', array('o1' => 'firewall')),
			array('LxGuard', array('a' => 'show', 'o1' => 'lxguard')),
			array('Blocked IP Addresses', array('c' => 'hostdeny')),
			array('SSH Configuration', array('o1' => 'sshconfig')),
			array('SSH Authorized Keys', array('c' => 'sshauthorizedkey')),
			array('Panel Allowed Logins', array('c' => 'allowedip')),
			array('Panel Blocked Logins', array('c' => 'blockedip')),
		)),
		'Backup' => array('backup', array(
			array('Backup / Restore', array('a' => 'show', 'o0' => 'lxbackup', 'o1' => '')),
			array('Backup Schedule', array('sa' => 'schedule_conf')),
			array('Backup FTP Server', array('sa' => 'ftp_conf')),
			array('Panel Self Backup', array('sa' => 'selfbackupconfig')),
		)),
		'Settings' => array('settings', array(
			array('General Settings', array('sa' => 'generalsetting')),
			array('Panel Ports & Login', array('sa' => 'portconfig')),
			array('Login Page Options', array('sa' => 'login_options')),
			array('Notifications', array('o0' => 'notification')),
			array('Appearance', array('sa' => 'skin', 'o0' => 'sp_specialplay')),
			array('Upload Logo', array('sa' => 'upload_logo')),
			array('My Information', array('sa' => 'information')),
			array('Password', array('sa' => 'password', 'o0' => '')),
			array('Update', array('a' => 'show', 'o0' => 'lxupdate')),
		)),
	);

	// clearer names for entries left under "More" ([label, conditions])
	$rename = array(
		array('Re-read IP Addresses', array('sa' => 'readipaddress')),
		array('Backup File Manager', array('a' => 'show', 'o0' => 'lxbackup', 'o1' => 'ffile')),
		array('Upload Backup File', array('sa' => 'upload', 'o0' => 'lxbackup')),
		array('Add Cron Task (simple)', array('a' => 'addform', 'c' => 'cron', 'dt' => 'simple')),
		array('Add Cron Task (advanced)', array('a' => 'addform', 'c' => 'cron', 'dt' => 'complex')),
		array('LxGuard Connections', array('c' => 'lxguardhitdisplay')),
		array('LxGuard Raw Connections', array('c' => 'rawlxguardhit')),
		array('LxGuard White List', array('c' => 'lxguardwhitelist')),
		array('Mail Spamdyke', array('sa' => 'spamdyke')),
	);

	// "More" group names
	$groupname = array('Basic' => 'Mail', 'Task' => 'Other');

	$home = array();
	$items = array();

	foreach ($sections as $s) {
		if (empty($s['groups'])) {
			$home[] = $s;

			continue;
		}

		foreach ($s['groups'] as $g) {
			foreach ($g['links'] as $l) {
				$l['from'] = $s['title'];
				$l['key'] = kn_nav_key($l['href']);
				$items[] = $l;
			}
		}
	}

	$used = array();
	$out = $home;

	foreach ($layout as $title => $def) {
		$links = array();

		foreach ($def[1] as $rule) {
			foreach ($items as $i => $l) {
				if (isset($used[$i])) {
					continue;
				}

				$ok = true;

				foreach ($rule[1] as $k => $v) {
					if ($l['key'][$k] !== $v) {
						$ok = false;

						break;
					}
				}

				if ($ok) {
					$used[$i] = true;
					$links[] = array('text' => $rule[0], 'href' => $l['href'], 'target' => $l['target']);

					break;
				}
			}
		}

		if ($links) {
			$out[] = array('title' => $title, 'icon' => $def[0], 'href' => null,
				'groups' => array(array('label' => '', 'links' => $links)));
		}
	}

	// everything else: "More", grouped by where it was, duplicates dropped
	$more = array();
	$seen = array();

	foreach ($items as $i => $l) {
		if (isset($used[$i])) {
			$seen[$l['href']] = true;
		}
	}

	foreach ($items as $i => $l) {
		if (isset($used[$i]) || isset($seen[$l['href']])) {
			continue;
		}

		$seen[$l['href']] = true;

		$text = $l['text'];

		foreach ($rename as $r) {
			if (array_intersect_assoc($r[1], $l['key']) == $r[1]) {
				$text = $r[0];

				break;
			}
		}

		if (($text === 'localhost') || ($text === 'admin')) {
			$text = "{$l['from']} ({$text})";
		}

		$grp = isset($groupname[$l['from']]) ? $groupname[$l['from']] : $l['from'];
		$more[$grp][] = array('text' => $text, 'href' => $l['href'], 'target' => $l['target']);
	}

	if ($more) {
		$groups = array();

		foreach ($more as $label => $links) {
			$groups[] = array('label' => $label, 'links' => $links);
		}

		$out[] = array('title' => 'More', 'icon' => 'more', 'href' => null, 'groups' => $groups);
	}

	return $out;
}

/** True when $href points to the page currently displayed. */
function kn_nav_is_current($href)
{
	$q = isset($_SERVER['QUERY_STRING']) ? urldecode($_SERVER['QUERY_STRING']) : '';

	if ($q === '' || strpos($href, '/display.php?') !== 0) {
		return false;
	}

	parse_str(parse_url(html_entity_decode($href), PHP_URL_QUERY) ?? '', $want);
	parse_str($q, $have);

	foreach (array('frm_action', 'frm_o_cname', 'frm_subaction', 'frm_dttype') as $k) {
		$w = isset($want[$k]) ? $want[$k] : '';
		$h = isset($have[$k]) ? $have[$k] : '';

		if ($w !== $h) {
			return false;
		}
	}

	// KloxoNext - the object too ('show' of firewall is not 'show' of the file manager);
	// the page may sit deeper (client/domain context), so compare from the end
	$wo = array();
	$ho = array();

	foreach ((isset($want['frm_o_o']) ? (array)$want['frm_o_o'] : array()) as $o) {
		$wo[] = isset($o['class']) ? $o['class'] : '';
	}

	foreach ((isset($have['frm_o_o']) ? (array)$have['frm_o_o'] : array()) as $o) {
		$ho[] = isset($o['class']) ? $o['class'] : '';
	}

	$wl = $wo ? end($wo) : '';
	$hl = $ho ? end($ho) : '';

	return ($wl === $hl);
}

function kn_nav_render($sections)
{
	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

	$out = "<nav class=\"kn-nav\" aria-label=\"Main\">\n";

	foreach ($sections as $i => $s) {
		$hasSub = !empty($s['groups']);

		if (!$hasSub) {
			$cur = ($s['href'] && kn_nav_is_current($s['href'])) ? ' aria-current="page"' : '';
			$out .= "<a class=\"kn-nav-top\" href=\"{$h($s['href'] ?: '#')}\"{$cur}>"
				. kn_nav_icon($s['icon']) . "<span class=\"kn-nav-text\">{$h($s['title'])}</span></a>\n";

			continue;
		}

		$open = '';
		$body = '';

		foreach ($s['groups'] as $g) {
			$body .= "<div class=\"kn-nav-group\">";

			if ($g['label'] !== '') {
				$body .= "<div class=\"kn-nav-label\">{$h($g['label'])}</div>";
			}

			foreach ($g['links'] as $l) {
				$cur = '';

				if (kn_nav_is_current($l['href'])) {
					$cur = ' aria-current="page"';
					$open = ' open';
				}

				$tgt = $l['target'] ? " target=\"{$h($l['target'])}\" rel=\"noopener\"" : '';
				$body .= "<a href=\"{$h($l['href'])}\"{$cur}{$tgt}>{$h($l['text'])}</a>";
			}

			$body .= "</div>";
		}

		$out .= "<details class=\"kn-nav-section\" data-kn-section=\"{$i}\"{$open}>"
			. "<summary class=\"kn-nav-top\" title=\"{$h($s['title'])}\">" . kn_nav_icon($s['icon'])
			. "<span class=\"kn-nav-text\">{$h($s['title'])}</span>"
			. "<svg class=\"kn-chevron\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"m9 6 6 6-6 6\"/></svg>"
			. "</summary><div class=\"kn-nav-sub\"><div class=\"kn-nav-flyout-title\">{$h($s['title'])}</div>{$body}</div></details>\n";
	}

	return $out . "</nav>\n";
}
