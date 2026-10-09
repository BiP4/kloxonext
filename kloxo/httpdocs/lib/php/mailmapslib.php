<?php

/**
 * KloxoNext - Postfix + Dovecot virtual mail.
 *
 * Instead of incremental per-object changes (the vpopmail way), every mail
 * change regenerates the complete Postfix maps and the Dovecot user file from
 * the Kloxo database. They are small text files, regeneration is cheap and the
 * result is always consistent with what the panel shows.
 *
 *   /etc/postfix/kloxo/virtual_domains     mail domains hosted here
 *   /etc/postfix/kloxo/virtual_mailboxes   user@domain -> local mailbox
 *   /etc/postfix/kloxo/virtual_aliases     forwards, catch-all, alias domains
 *   /etc/dovecot/kloxo/users               passwd-file: password hash + quota
 *   /home/vmail/<domain>/<user>/           Maildir + sieve (autoresponder, spam)
 *
 * Delivery: Postfix -> Dovecot LMTP (sieve, quota). Authentication for IMAP,
 * POP3 and SMTP submission (SASL) is done by Dovecot.
 */
final class KnMail
{
	const VMAIL_HOME = '/home/vmail';
	const VMAIL_UID = 5000;
	const VMAIL_GID = 5000;
	const PF_DIR = '/etc/postfix/kloxo';
	const DC_USERS = '/etc/dovecot/kloxo/users';

	private static $scheduled = false;

	/**
	 * Drivers run before Kloxo writes the object to its database, so the maps
	 * are regenerated once, at the end of the request, from the committed data.
	 */
	public static function scheduleRebuild()
	{
		if (self::$scheduled) {
			return;
		}

		self::$scheduled = true;

		register_shutdown_function(function () {
			try {
				KnMail::rebuild();
			} catch (Throwable $e) {
				@file_put_contents('/usr/local/lxlabs/kloxo/log/php-exceptions.log',
					date('c') . " KnMail::rebuild: " . $e->getMessage() . "\n", FILE_APPEND);
			}
		});
	}

	/** Regenerate every map and reload the daemons. */
	public static function rebuild()
	{
		$domains = self::rows('mmail');
		$accounts = self::rows('mailaccount');
		$forwards = self::rows('mailforward');

		$vdomains = array();
		$vmailboxes = array();
		$valiases = array();
		$dcusers = array();
		$aliasDomains = array();

		$local = array();

		foreach ($domains as $d) {
			$name = strtolower(trim($d['nname']));

			if ($name === '') {
				continue;
			}

			// alias ('forward') domains: every address goes to the target domain
			if (($d['ttype'] ?? '') === 'forward' && !empty($d['redirect_domain'])) {
				$aliasDomains[$name] = strtolower(trim($d['redirect_domain']));
				$vdomains[] = $name;

				continue;
			}

			// mail handled by an external server (MX elsewhere)
			if (($d['remotelocalflag'] ?? '') === 'remote') {
				continue;
			}

			$local[$name] = $d;
			$vdomains[] = $name;
		}

		foreach ($accounts as $a) {
			$addr = strtolower(trim($a['nname']));

			if (strpos($addr, '@') === false) {
				continue;
			}

			list($user, $dom) = explode('@', $addr, 2);

			if (!isset($local[$dom])) {
				continue;
			}

			$enabled = (($a['status'] ?? 'on') !== 'off') && (($local[$dom]['status'] ?? 'on') !== 'off');

			self::ensureMailbox($dom, $user);

			if (!$enabled) {
				continue;
			}

			$vmailboxes[] = "{$addr} {$dom}/{$user}/";

			// forwards of the account (+ local copy unless disabled)
			$targets = array();

			if (($a['forward_status'] ?? '') === 'on') {
				foreach (self::forwardList($a['ser_forward_a'] ?? '') as $f) {
					$f = trim($f);

					if ($f === '' || $f[0] === '|') {
						continue;   // pipes are not supported for virtual users
					}

					$targets[] = (strpos($f, '@') === false) ? "{$f}@{$dom}" : $f;
				}

				if (!empty($targets) && (($a['no_local_copy'] ?? '') !== 'on')) {
					array_unshift($targets, $addr);
				}
			}

			// every mailbox is listed in the alias map too (to itself when it has no
			// forward), otherwise a catch-all '@domain' alias would capture it
			if (empty($targets)) {
				$targets = array($addr);
			}

			$valiases[] = $addr . ' ' . implode(',', array_unique($targets));

			$quota = self::quotaMb($a['priv_q_maildisk_usage'] ?? '');
			// quota field name differs between Dovecot 2.3 and 2.4
			if ($quota > 0) {
				$extra = self::dovecot24() ? "userdb_quota_storage_size={$quota}M" : "userdb_quota_rule=*:storage={$quota}M";
			} else {
				$extra = '';
			}

			$hash = (string)($a['password'] ?? '');

			if ($hash === '' || $hash[0] !== '$') {
				$hash = '!';   // no usable hash: account cannot log in
			}

			$dcusers[] = "{$addr}:{CRYPT}{$hash}:" . self::VMAIL_UID . ':' . self::VMAIL_GID
				. '::' . self::VMAIL_HOME . "/{$dom}/{$user}::{$extra}";

			self::writeSieve($dom, $user, $a);
		}

		// standalone forwards (Mail Forwards page)
		foreach ($forwards as $f) {
			$addr = strtolower(trim($f['nname']));
			$to = trim((string)($f['forwardaddress'] ?? ''));

			if ($addr === '' || $to === '' || $to[0] === '|') {
				continue;
			}

			$valiases[] = "{$addr} {$to}";
		}

		// catch-all: '--bounce--' (default), 'Delete' or a local account name
		foreach ($local as $dom => $d) {
			$c = trim((string)($d['catchall'] ?? ''));

			if ($c === '' || $c === '--bounce--') {
				continue;
			}

			$valiases[] = ($c === 'Delete') ? "@{$dom} devnull@localhost" : "@{$dom} {$c}@{$dom}";
		}

		foreach ($aliasDomains as $src => $dst) {
			$valiases[] = "@{$src} @{$dst}";
		}

		self::writeMap('virtual_domains', array_map(function ($d) { return "{$d} OK"; }, array_unique($vdomains)));
		self::writeMap('virtual_mailboxes', $vmailboxes);
		self::writeMap('virtual_aliases', $valiases);

		self::writeFile(self::DC_USERS, implode("\n", $dcusers) . "\n", 0640, 'root', 'dovecot');

		exec("postfix reload >/dev/null 2>&1");
	}

	/** Mailbox directory of an account (created on demand). */
	public static function mailboxDir($domain, $user)
	{
		return self::VMAIL_HOME . "/{$domain}/{$user}";
	}

	public static function ensureMailbox($domain, $user)
	{
		$dir = self::mailboxDir($domain, $user);

		if (!is_dir("{$dir}/Maildir/cur")) {
			foreach (array('cur', 'new', 'tmp') as $s) {
				@mkdir("{$dir}/Maildir/{$s}", 0700, true);
			}

			exec("chown -R " . self::VMAIL_UID . ":" . self::VMAIL_GID . " " . escapeshellarg(self::VMAIL_HOME . "/{$domain}"));
		}

		return $dir;
	}

	public static function removeMailbox($domain, $user = null)
	{
		$path = ($user === null) ? self::VMAIL_HOME . "/{$domain}" : self::mailboxDir($domain, $user);

		// keep the data: move aside instead of deleting (restorable by the admin)
		if (is_dir($path)) {
			@mkdir(self::VMAIL_HOME . "/.deleted", 0700, true);
			rename($path, self::VMAIL_HOME . "/.deleted/" . str_replace('/', '_', trim(substr($path, strlen(self::VMAIL_HOME)), '/')) . '.' . time());
		}
	}

	public static function diskUsage($address)
	{
		list($user, $dom) = array_pad(explode('@', $address, 2), 2, '');

		return lxfile_dirsize(self::mailboxDir($dom, $user));
	}

	// ---------------------------------------------------------------- helpers

	public static function dovecot24()
	{
		static $v = null;

		if ($v === null) {
			$v = version_compare(trim((string)shell_exec("dovecot --version 2>/dev/null")), '2.4', '>=');
		}

		return $v;
	}

	private static function rows($table)
	{
		$db = new Sqlite(null, $table);
		$rows = $db->getTable();

		return is_array($rows) ? $rows : array();
	}

	private static function forwardList($ser)
	{
		if (!$ser) {
			return array();
		}

		$list = @unserialize(base64_decode($ser));
		$out = array();

		foreach ((array)$list as $o) {
			if (is_object($o) && isset($o->nname)) {
				$out[] = $o->nname;
			} elseif (is_string($o)) {
				$out[] = $o;
			}
		}

		return $out;
	}

	private static function quotaMb($v)
	{
		if ($v === '' || $v === null || is_unlimited($v)) {
			return 0;
		}

		return max(0, (int)$v);
	}

	/** Sieve script: autoresponder (vacation) and spam filing. */
	private static function writeSieve($dom, $user, $a)
	{
		$home = self::mailboxDir($dom, $user);
		$rules = array();
		$req = array('fileinto');

		$spam = $a['filter_spam_status'] ?? 'mailbox';

		if ($spam === 'spambox') {
			$rules[] = "if anyof (header :contains \"X-Spam-Flag\" \"YES\", header :contains \"X-Bogosity\" \"Spam\") {\n  fileinto \"Junk\";\n  stop;\n}";
		} elseif ($spam === 'delete') {
			$rules[] = "if anyof (header :contains \"X-Spam-Flag\" \"YES\", header :contains \"X-Bogosity\" \"Spam\") {\n  discard;\n  stop;\n}";
		}

		if (($a['autorespond_status'] ?? '') === 'on' && !empty($a['autores_name'])) {
			$db = new Sqlite(null, 'autoresponder');
			$r = $db->getRowsWhere("nname = '" . str_replace("'", "\\'", $a['autores_name']) . "'");

			if (!empty($r[0])) {
				$req[] = 'vacation';
				$subj = addcslashes((string)($r[0]['reply_subject'] ?: 'Auto reply'), "\"\\");
				$msg = addcslashes((string)$r[0]['text_message'], "\"\\");
				$rules[] = "vacation :days 1 :subject \"{$subj}\" \"{$msg}\";";
			}
		}

		$script = "require [\"" . implode('", "', array_unique($req)) . "\"];\n\n" . implode("\n\n", $rules) . "\n";

		@mkdir("{$home}/sieve", 0700, true);
		@chown("{$home}/sieve", self::VMAIL_UID);
		@chgrp("{$home}/sieve", self::VMAIL_GID);
		self::writeFile("{$home}/sieve/kloxo.sieve", $script, 0600, self::VMAIL_UID, self::VMAIL_GID);

		$active = "{$home}/.dovecot.sieve";

		if (!is_link($active)) {
			@unlink($active);
			symlink('sieve/kloxo.sieve', $active);
			@lchown($active, self::VMAIL_UID);
		}
	}

	private static function writeMap($name, $lines)
	{
		sort($lines);
		$file = self::PF_DIR . "/{$name}";

		self::writeFile($file, "# generated by KloxoNext - do not edit\n" . implode("\n", $lines) . "\n", 0640, 'root', 'postfix');
		exec("postmap " . escapeshellarg("hash:{$file}") . " 2>&1", $out, $ret);
	}

	private static function writeFile($file, $content, $mode, $owner, $group)
	{
		@mkdir(dirname($file), 0755, true);

		$tmp = $file . '.tmp';
		file_put_contents($tmp, $content);
		chmod($tmp, $mode);
		@chown($tmp, $owner);
		@chgrp($tmp, $group);
		rename($tmp, $file);
	}
}
