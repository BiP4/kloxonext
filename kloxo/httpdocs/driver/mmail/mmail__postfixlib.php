<?php

// KloxoNext - mail domains on Postfix + Dovecot (see lib/php/mailmapslib.php)
class Mmail__Postfix extends lxDriverClass
{
	function dbactionAdd()
	{
		KnMail::scheduleRebuild();
	}

	function dbactionDelete()
	{
		KnMail::scheduleRebuild();
		KnMail::removeMailbox($this->main->nname);
	}

	function dbactionUpdate($subaction)
	{
		if ($subaction === 'graph_mailtraffic') {
			return rrd_graph_single("mailtraffic (bytes)", $this->main->nname, $this->main->rrdtime);
		}

		// full_update, toggle_status, catchall, remotelocalmail, add/delete_alias,
		// redirect_domain, changeowner ... all end up in the generated maps
		KnMail::scheduleRebuild();
	}

	static function getDir($domain)
	{
		return KnMail::VMAIL_HOME . "/{$domain}";
	}

	static function doesDomainExist($domain)
	{
		return is_dir(KnMail::VMAIL_HOME . "/{$domain}");
	}

	static function getUserGroup($domain, $flag_useralone = false)
	{
		return $flag_useralone ? 'vmail' : 'vmail:vmail';
	}

	static function createAliasdomain($source, $maindomain)
	{
		KnMail::scheduleRebuild();
	}

	// Called by domain::generateDomainKey() when 'domain key' is enabled in the
	// server mail settings: creates the OpenDKIM key (selector 'private', the name
	// Kloxo publishes in DNS) and returns the public key for the TXT record.
	static function generateDKey($domain)
	{
		if (!preg_match('/^[a-z0-9.-]+$/i', $domain)) {
			return null;
		}

		$dir = "/etc/opendkim/keys/{$domain}";

		if (!file_exists("{$dir}/private.private")) {
			@mkdir($dir, 0750, true);
			exec("opendkim-genkey -b 2048 -d " . escapeshellarg($domain) . " -D " . escapeshellarg($dir) . " -s private >/dev/null 2>&1");
			exec("chown -R opendkim:opendkim " . escapeshellarg($dir));
			exec("sh /script/fixmail-dkim >/dev/null 2>&1");
		}

		$txt = @file_get_contents("{$dir}/private.txt");

		if (!$txt) {
			return null;
		}

		// private._domainkey IN TXT ( "v=DKIM1; h=sha256; k=rsa; " "p=MIIB..." "..." )
		preg_match_all('/"([^"]*)"/', $txt, $m);
		$joined = implode('', $m[1]);

		return preg_match('/p=([A-Za-z0-9+\/=]+)/', $joined, $p) ? $p[1] : null;
	}
}
