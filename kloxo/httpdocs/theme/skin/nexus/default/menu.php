<?php

if (strpos($_SERVER["SCRIPT_NAME"], "/display.php") === false) {
	print("No permit access directly");
	return;
}

/*
 * KloxoNext - the panel menu: every entry and the rules deciding who sees it (admin,
 * reseller, customer, an admin looking at a client, several servers). nav.php turns it
 * into the nexus sidebar; it reads the sections (<li><a>), the groups (<b>) and the links.
 *
 * An entry seen while looking at a client has two links: one for the logged in account,
 * one for the client. Its query is written once, with these marks:
 *   {C}  nothing / the client query
 *   {O}  frm_o_o[0][class]= / the client query + frm_o_o[1][class]=  (object below the client)
 *   {L}  frm_o_o[0] / frm_o_o[1]                                     (that object)
 *   {P}  frm_o_o[1] / frm_o_o[2]                                     (one level further)
 * An entry of a server has, with several servers, a link for localhost and one for the
 * server in use: {S} is the server query.
 */

$syncserver = $login->syncserver;

$loginas = $login->nname;
$clientid = $loginas;

$consumedlogin = (isset($ghtml->frm_consumedlogin) && ($ghtml->frm_consumedlogin === 'true')) ? "frm_consumedlogin=true&" : "";

$loginquery = "frm_o_o[0][class]=client&frm_o_o[0][nname]={$loginas}&";

$clientquery = "";
$clientquery2 = "";
$clientid2 = "";
$domainid = "";
$domainid2 = "";

if (isset($ghtml->frm_o_o[0]['class']) && ($ghtml->frm_o_o[0]['class'] === 'client')) {
	$clientid = $ghtml->frm_o_o[0]['nname'];
	$clientquery = "frm_o_o[0][class]=client&frm_o_o[0][nname]={$clientid}&";
}

if (isset($ghtml->frm_o_o[1]['class']) && ($ghtml->frm_o_o[1]['class'] === 'client')) {
	$clientid2 = $ghtml->frm_o_o[1]['nname'];
	$clientquery2 = "frm_o_o[0][class]=client&frm_o_o[1][nname]={$clientid}&";
}

if (isset($ghtml->frm_o_o[0]['class']) && ($ghtml->frm_o_o[0]['class'] === 'domain')) {
	$domainid = $ghtml->frm_o_o[0]['nname'];
}

if (isset($ghtml->frm_o_o[1]['class']) && ($ghtml->frm_o_o[1]['class'] === 'domain')) {
	$domainid2 = $ghtml->frm_o_o[1]['nname'];
}

$serverquery = "frm_o_o[0][class]=pserver&frm_o_o[0][nname]={$syncserver}&";
$localhostquery = "frm_o_o[0][class]=pserver&frm_o_o[0][nname]=localhost&";

$kn_many = check_if_many_server();
$kn_other_server = $kn_many && ($syncserver !== 'localhost');
$kn_client = ($clientquery !== '');

$kn_html = '';

$kn_t = function ($s) use ($ghtml) {
	return $ghtml->getTitleOnly($s);
};

$kn_k = function ($s) use ($login) {
	return $login->getKeywordUc($s);
};

$kn_a = function ($q, $text) use (&$kn_html) {
	$kn_html .= "<a href=\"/display.php?{$q}\">{$text}</a><br/>\n";
};

$kn_b = function ($label) use (&$kn_html) {
	$kn_html .= "<b>{$label}</b>\n";
};

$kn_section = function ($title, $href = 'javascript://') use (&$kn_html) {
	$kn_html .= "<li><a href=\"{$href}\">{$title}</a>\n<div class=\"drop\">\n";
};

$kn_end = function () use (&$kn_html) {
	$kn_html .= "</div>\n</li>\n";
};

$kn_q = function ($q, $forclient) use ($clientquery) {
	return strtr($q, array(
		'{C}' => $forclient ? $clientquery : '',
		'{O}' => $forclient ? "{$clientquery}frm_o_o[1][class]=" : 'frm_o_o[0][class]=',
		'{L}' => $forclient ? 'frm_o_o[1]' : 'frm_o_o[0]',
		'{P}' => $forclient ? 'frm_o_o[2]' : 'frm_o_o[1]',
	));
};

// an entry of the account: alone, or for the login and for the client looked at
$kn_pair = function ($title, $q) use ($kn_a, $kn_q, $kn_client, $consumedlogin, $loginas, $clientid) {
	if (!$kn_client) {
		$kn_a($consumedlogin . $kn_q($q, false), $title);

		return;
	}

	$kn_a($consumedlogin . $kn_q($q, false), $loginas);
	$kn_a($consumedlogin . $kn_q($q, true), $clientid);
};

// an entry of a server: the server in use, or localhost and the other server
$kn_srv = function ($title, $q, $prefix = null) use ($kn_a, $kn_other_server, $consumedlogin, $serverquery, $localhostquery, $syncserver) {
	$prefix = ($prefix === null) ? $consumedlogin : $prefix;

	if (!$kn_other_server) {
		$kn_a($prefix . str_replace('{S}', $serverquery, $q), $title);

		return;
	}

	$kn_a($prefix . str_replace('{S}', $localhostquery, $q), 'localhost');
	$kn_a($prefix . str_replace('{S}', $serverquery, $q), $syncserver);
};

// --- Home
$kn_section($kn_k('home'), "/display.php?{$consumedlogin}frm_action=show");

if (!$login->isAdmin()) {
	if ($kn_client) {
		$kn_a("frm_action=show&{$clientquery}", $clientid);

		if ($clientquery2 !== '') {
			$kn_a("frm_action=show&{$clientquery}{$clientquery2}", $clientid2);
		}
	}

	if ($consumedlogin !== '') {
		// no $consumedlogin here: this link ends it
		$kn_a("frm_action=show&{$loginquery}", "{$loginas} (" . $kn_k('cancel') . ")");
	} else {
		$kn_a("frm_action=update&frm_subaction=dologin&{$clientquery}", $clientid);
	}
}

$kn_end();

// --- Administration
$kn_section($kn_k('administration'));

if (!$login->isCustomer()) {
	$kn_b($kn_k('alllist'));

	foreach (array('client', 'domain', 'addondomain', 'mailaccount', 'mailforward', 'mailinglist', 'mysqldb', 'cron', 'ftpuser', 'sslcert') as $c) {
		$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=all_{$c}", $kn_client ? $loginas : $kn_t("a=list&c=all_{$c}"));
	}

	$kn_b($kn_k('resourceplan'));
	$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=resourceplan", $kn_t("a=list&c=resourceplan"));

	if ($kn_client) {
		$kn_a("{$consumedlogin}frm_action=updateform&frm_subaction=change_plan&{$clientquery}", $clientid);
	}

	$kn_b($kn_k('support'));
	$kn_pair($kn_t("a=list&c=ticket"), "frm_action=list&{C}frm_o_cname=ticket");

	if ($login->isAdmin()) {
		$kn_a("frm_action=updateform&frm_subaction=ticketconfig&frm_o_o[0][class]=ticketconfig", $kn_t("a=updateform&sa=ticketconfig&o=ticketconfig"));
		$kn_a("frm_action=list&frm_o_o[0][class]=general&frm_o_cname=helpdeskcategory_a", $kn_t("a=list&o=general&c=helpdeskcategory_a"));
	}
}

$kn_b($kn_k('message'));
$kn_pair($kn_t("a=list&c=smessage"), "frm_action=list&{C}frm_o_cname=smessage");

if ($login->isAdmin()) {
	$kn_b($kn_k('custombutton'));
	$kn_a("frm_action=list&frm_o_cname=custombutton", $kn_t("a=list&c=custombutton"));

	$kn_b($kn_k('update'));
	$kn_a("frm_action=show&frm_o_o[0][class]=lxupdate", $kn_t("a=show&o=lxupdate"));
	$kn_a("frm_action=list&frm_o_o[0][class]=lxupdate&frm_o_cname=releasenote", $kn_t("a=list&o=lxupdate&c=releasenote"));
}

$kn_b($kn_k('other'));
$kn_pair($kn_t("a=updateform&sa=information&o=client"), "frm_action=updateform&frm_subaction=information&{C}");
$kn_pair($kn_t("a=list&c=actionlog"), "frm_action=list&{C}frm_o_cname=actionlog");
$kn_pair($kn_t("a=updateform&sa=password&o=client"), "frm_action=updateform&frm_subaction=password&{C}");
$kn_pair($kn_t("a=updateform&sa=skin&o=sp_specialplay"), "frm_action=updateform&frm_subaction=skin&{O}sp_specialplay");
$kn_end();

// --- Resource
$kn_section($kn_k('resource'));

$kn_b($kn_k('auxiliary'));
$kn_pair($kn_t("a=list&c=auxiliary"), "frm_action=list&{C}frm_o_cname=auxiliary");

if ($login->isAdmin()) {
	$kn_pair($kn_t("a=list&c=all_auxiliary"), "frm_action=list&{C}frm_o_cname=all_auxiliary");
}

$kn_b($kn_k('ssl'));
$kn_ssl = $kn_client ? "frm_o_o[0][class]=client&frm_o_o[0][nname]={$clientid}&" : '';

if ($domainid === '') {
	$kn_a("frm_action=list&{$kn_ssl}frm_o_cname=sslcert", $kn_t("a=list&c=sslcert"));
} elseif (!$kn_client) {
	$kn_a("frm_action=list&frm_o_o[0][class]=domain&frm_o_o[0][nname]={$domainid}&frm_o_o[1][class]=web&frm_o_cname=sslcert", $kn_t("a=list&c=sslcert"));
} else {
	$kn_a("frm_action=list&{$kn_ssl}frm_o_o[1][class]=domain&frm_o_o[1][nname]={$domainid2}&frm_o_o[2][class]=web&frm_o_cname=sslcert", $kn_t("a=list&c=sslcert"));
}

if ($login->isAdmin()) {
	$kn_b($kn_t("a=list&c=ipaddress"));
	if ($kn_other_server) {
		$kn_srv($kn_t("a=list&c=ipaddress"), "frm_action=list&{S}frm_o_cname=ipaddress", '');
	} else {
		$kn_a("frm_action=list&frm_o_cname=ipaddress", $kn_t("a=list&c=ipaddress"));
	}

	$kn_srv($kn_t("a=update&sa=readipaddress&o=pserver"), "frm_action=update&frm_subaction=readipaddress&{S}", '');

	$kn_b($kn_k('dnstemplate'));
	$kn_a("frm_action=list&frm_o_cname=dnstemplate", $kn_t("a=list&c=dnstemplate"));
	$kn_a("frm_action=show&frm_o_o[0][class]=pserver&frm_o_o[0][nname]=localhost&frm_o_o[1][class]=dnscustom", $kn_k('dnscustom'));
	$kn_a("frm_action=show&frm_o_o[0][class]=cfaccount", "Cloudflare Account");

	if ($kn_client) {
		$kn_a("frm_action=updateform&frm_subaction=dnstemplatelist&{$clientquery}", $clientid);
	}
}

$kn_b($kn_k('backuprestore'));
$kn_pair($kn_t("a=show&o=lxbackup"), "frm_action=show&{O}lxbackup");
$kn_pair($kn_t("a=updateform&sa=ftp_conf&o=lxbackup"), "frm_action=updateform&frm_subaction=ftp_conf&{O}lxbackup");
$kn_pair($kn_t("a=updateform&sa=schedule_conf&o=lxbackup"), "frm_action=updateform&frm_subaction=schedule_conf&{O}lxbackup");
$kn_pair($kn_t("a=show&o=lxbackup&l[class]=ffile&l[nname]=/"), "frm_action=show&{O}lxbackup&{P}[class]=ffile&{P}[nname]=/");
$kn_pair($kn_t("a=updateform&sa=upload&o=lxbackup&l[class]=ffile&l[nname]=/"), "frm_action=updateform&frm_subaction=upload&{O}lxbackup&{P}[class]=ffile&{P}[nname]=/");

$kn_b($kn_k('other'));
$kn_pair($kn_t("a=updateform&sa=update&o=domaindefault"), "frm_action=updateform&frm_subaction=update&{O}domaindefault");
$kn_pair($kn_t("a=list&c=ftpsession"), "frm_action=list&{C}frm_o_cname=ftpsession");
$kn_pair($kn_t("a=updateform&sa=shell_access&o=client"), "frm_action=updateform&frm_subaction=shell_access&{C}");
$kn_end();

// --- Advanced
$kn_section($kn_k('advanced'));

$kn_b($kn_k('general'));

if ($login->isAdmin()) {
	foreach (array('scavengetime', 'generalsetting', 'selfbackupconfig') as $sa) {
		$kn_a("frm_action=updateform&frm_subaction={$sa}&frm_o_o[0][class]=general", $kn_t("a=updateform&sa={$sa}&o=general"));
	}

	$kn_a("frm_action=list&frm_o_o[0][class]=genlist&frm_o_cname=dirindexlist_a", $kn_t("a=list&o=genlist&c=dirindexlist_a"));
	$kn_a("frm_action=updateform&frm_subaction=portconfig&frm_o_o[0][class]=general", $kn_t("a=updateform&sa=portconfig&o=general"));
	$kn_a("frm_action=updateform&frm_subaction=disable_skeleton", $kn_t("a=updateform&sa=disable_skeleton&c=client"));
	$kn_a("frm_action=updateform&frm_subaction=upload_logo&frm_o_o[0][class]=sp_specialplay", $kn_t("a=updateform&sa=upload_logo&o=sp_specialplay"));
}

$kn_pair($kn_t("a=show&o=notification"), "frm_action=show&{O}notification");
$kn_pair($kn_t("a=updateform&sa=login_options&o=sp_specialplay"), "frm_action=updateform&frm_subaction=login_options&{O}sp_specialplay");

$kn_b($kn_k('blockedallowedips'));
$kn_pair($kn_t("a=list&c=allowedip"), "frm_action=list&{C}frm_o_cname=allowedip");
$kn_pair($kn_t("a=list&c=blockedip"), "frm_action=list&{C}frm_o_cname=blockedip");

if ($login->isAdmin()) {
	$kn_b($kn_k('maintenance'));
	$kn_a("{$consumedlogin}frm_action=updateform&frm_subaction=maintenance&frm_o_o[0][class]=general", $kn_t("a=updateform&sa=maintenance&o=general"));
}

$kn_b($kn_k('other'));
$kn_pair($kn_t("a=updateform&sa=miscinfo&o=pserver"), "frm_action=updateform&{C}frm_subaction=miscinfo");

if ($login->isAdmin()) {
	$kn_a("{$consumedlogin}frm_action=updateform&frm_subaction=skin&frm_o_o[0][class]=sp_childspecialplay", $kn_t("a=updateform&sa=skin&o=sp_childspecialplay"));
}

$kn_end();

if ($login->isAdmin()) {
	$kn_pserver = "l[class]=pserver&l[nname]={$syncserver}";

	// --- Server
	$kn_section($kn_k('server'));

	$kn_b($kn_k('server'));
	$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=pserver", $kn_t("a=list&c=pserver"));
	$kn_a("{$consumedlogin}frm_action=updateform&frm_subaction=forcedeletepserver&frm_o_cname=client", $kn_t("a=updateform&sa=forcedeletepserver&c=client"));

	if ($kn_many) {
		$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=psrole_a", $kn_t("a=list&c=psrole_a"));
	}

	$kn_srv($kn_t("a=updateform&sa=reboot&{$kn_pserver}"), "frm_action=updateform&frm_subaction=reboot&{S}");
	$kn_srv($kn_t("a=updateform&sa=poweroff&{$kn_pserver}"), "frm_action=updateform&frm_subaction=poweroff&{S}");

	$kn_b($kn_k('show'));

	foreach (array('service', 'process', 'component') as $c) {
		$kn_srv($kn_t("a=list&c={$c}"), "frm_action=list&{S}frm_o_cname={$c}");
	}

	$kn_srv($kn_t("a=show&o=llog"), "frm_action=show&{S}frm_o_o[1][class]=llog");
	$kn_srv($kn_t("a=updateform&sa=update&o=driver"), "frm_action=updateform&frm_subaction=update&{S}frm_o_o[1][class]=driver");

	$kn_b($kn_k('other'));
	$kn_srv($kn_t("a=updateform&sa=switchprogram&{$kn_pserver}"), "frm_action=updateform&frm_subaction=switchprogram&{S}");
	$kn_srv($kn_t("a=updateform&sa=timezone&{$kn_pserver}"), "frm_action=updateform&frm_subaction=timezone&{S}");
	$kn_srv($kn_t("a=updateform&sa=commandcenter"), "frm_action=updateform&frm_subaction=commandcenter&{S}");
	$kn_srv($kn_t("a=show&o=sshclient"), "frm_action=show&{S}frm_o_o[1][class]=sshclient");
	$kn_pair($kn_t("a=list&c=traceroute"), "frm_action=list&{C}frm_o_cname=traceroute");
	$kn_end();

	// --- Security
	$kn_section($kn_k('security'));

	$kn_b($kn_k('lxguard'));
	$kn_srv($kn_t("a=show&o=lxguard"), "frm_action=show&{S}frm_o_o[1][class]=lxguard");

	foreach (array('lxguardhitdisplay', 'rawlxguardhit', 'lxguardwhitelist') as $c) {
		$kn_srv($kn_t("a=list&o=lxguard&c={$c}"), "frm_action=list&{S}frm_o_o[1][class]=lxguard&frm_o_cname={$c}");
	}

	$kn_b($kn_k('other'));
	$kn_a("{$consumedlogin}frm_action=show&{$localhostquery}frm_o_o[1][class]=firewall", $kn_k('firewall'));
	$kn_srv($kn_t("a=show&o=sshconfig"), "frm_action=show&{S}frm_o_o[1][class]=sshconfig");

	foreach (array('watchdog', 'hostdeny', 'sshauthorizedkey') as $c) {
		$kn_srv($kn_t("a=list&c={$c}"), "frm_action=list&{S}frm_o_cname={$c}");
	}

	$kn_end();

	// --- Web, mail and database
	$kn_section($kn_k('webmailanddb'));

	$kn_b($kn_k('configure'));
	$kn_srv($kn_t("a=show&o=phpini"), "frm_action=show&{S}frm_o_o[1][class]=phpini");
	$kn_srv($kn_t("a=updateform&sa=extraedit&o=phpini"), "frm_action=updateform&frm_subaction=extraedit&{S}frm_o_o[1][class]=phpini");
	$kn_srv($kn_t("a=show&o=serverweb"), "frm_action=show&{S}frm_o_o[1][class]=serverweb");
	$kn_srv($kn_t("a=show&o=serverftp"), "frm_action=show&{S}frm_o_o[1][class]=serverftp");
	$kn_srv($kn_t("a=list&c=phpmodule"), "frm_action=list&{S}frm_o_cname=phpmodule");

	$kn_b($kn_k('database'));
	$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=dbadmin", $kn_t("a=list&c=dbadmin"));
	$kn_srv($kn_t("a=updateform&sa=mysqlpasswordreset&o=pserver"), "frm_action=updateform&frm_subaction=mysqlpasswordreset&{S}");

	$kn_b($kn_k('mail'));
	$kn_srv($kn_t("a=updateform&sa=update&o=servermail"), "frm_action=updateform&frm_subaction=update&{S}frm_o_o[1][class]=servermail");
	$kn_srv($kn_t("a=updateform&sa=spamdyke&o=servermail"), "frm_action=updateform&frm_subaction=spamdyke&{S}frm_o_o[1][class]=servermail");
	$kn_srv($kn_t("a=list&c=mail_graylist_wlist_a"), "frm_action=list&{S}frm_o_o[1][class]=servermail&frm_o_cname=mail_graylist_wlist_a");
	$kn_srv($kn_t("a=list&c=mailqueue"), "frm_action=list&{S}frm_o_cname=mailqueue");
	$kn_srv($kn_t("a=list&c=clientmail"), "frm_action=list&{S}frm_o_cname=clientmail");
	$kn_end();
}

// --- Task
$kn_section($kn_k('task'));

$kn_b($kn_k('domain'));

foreach (array('domain', 'subdomain', 'addondomain') as $c) {
	$kn_pair($kn_t("a=list&c={$c}"), "frm_action=list&{C}frm_o_cname={$c}");
}

$kn_pair($kn_t("a=updateform&sa=default_domain&o=client"), "frm_action=updateform&frm_subaction=default_domain&{C}");

$kn_b($kn_k('ftpmaildatabase'));

foreach (array('ftpuser', 'mailaccount', 'mysqldb') as $c) {
	$kn_pair($kn_t("a=list&c={$c}"), "frm_action=list&{C}frm_o_cname={$c}");
}

$kn_b($kn_k('filemanager'));

if ($login->isAdmin()) {
	$kn_srv($syncserver, "frm_action=show&{S}frm_o_o[1][class]=ffile&frm_o_o[1][nname]=", '');
}

// the file manager of the account itself is named after it
$kn_pair($clientid, "frm_action=show&{O}ffile&{L}[nname]=/");

$kn_b($kn_k('crontask'));
$kn_pair($kn_t("a=list&c=cron"), "frm_action=list&{C}frm_o_cname=cron");

foreach (array('simple', 'complex') as $t) {
	$kn_pair($kn_t("a=addform&c=cron&dta[var]=ttype&dta[val]={$t}"), "frm_action=addform&{C}frm_dttype[var]=ttype&frm_dttype[val]={$t}&frm_o_cname=cron");
}

if (!$login->isCustomer()) {
	$kn_b($kn_k('client'));

	if ($login->isAdmin()) {
		$kn_a("{$consumedlogin}frm_action=list&frm_o_cname=all_client", $kn_t("a=list&c=all_client"));
	}

	$kn_pair($kn_t("a=list&c=client"), "frm_action=list&{C}frm_o_cname=client");

	$kn_types = array();

	if ($login->isAdmin()) {
		if ($kn_many) {
			$kn_types[] = 'wholesale';
		}

		$kn_types[] = 'reseller';
	}

	$kn_types[] = 'customer';

	foreach ($kn_types as $t) {
		$kn_a("{$consumedlogin}frm_action=addform&frm_dttype[var]=cttype&frm_dttype[val]={$t}&frm_o_cname=client", $kn_t("a=addform&dta[var]=cttype&dta[val]={$t}&c=client"));
	}
}

$kn_end();

?>
<div id="menu_div" class="div_menu">
<?php
// a mail account has no panel menu (only help / logout)
if (strpos($loginas, "@") === false) {
?>
	<ul class="menuTemplate2">
<?= $kn_html ?>
	</ul>
<?php
}
?>
	<ul class="menuTemplate2">
		<li><a title="<?= $kn_k('click_here_for') ?> <?= $kn_k('help') ?>" href="javascript://" onClick="toggleVisibilityById('infomsg');"><?= $kn_k('help') ?></a></li>
		<li><a title="<?= $kn_k('click_here_for') ?> <?= $kn_k('logout') ?>" href="javascript:if (confirm('<?= $kn_k('is_want_logout') ?>')) { location = '/lib/php/logout.php'; }"><?= $kn_k('logout') ?></a></li>
	</ul>
</div>
