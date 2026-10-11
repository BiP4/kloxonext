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
 *   /home/vmail/<domain>/<user>/.kloxo-spam   present: spam filter on (see /script/setup-spam)
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
	const SPAM_CONF = '/etc/dovecot/kloxo/spam/spam.conf';

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

		kn_register_shutdown(function () {
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

		$spamRows = array();

		foreach (self::rows('spam') as $s) {
			$spamRows[strtolower(trim((string)$s['nname']))] = $s;
		}

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

			self::writeSieve($dom, $user, $a, $spamRows[$addr] ?? null);
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

	/** Postfix lookup table type: 'hash' (Berkeley DB) or 'lmdb' (EL10 dropped Berkeley DB) */
	public static function dbType()
	{
		static $t = null;

		if ($t === null) {
			$t = trim((string)shell_exec("postconf -h default_database_type 2>/dev/null")) ?: 'hash';
		}

		return $t;
	}

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

	/** Spam engine in use: spamassassin, bogofilter or none (/script/setup-spam). */
	public static function spamEngine()
	{
		static $e = null;

		if ($e === null) {
			$e = 'none';

			foreach ((array)@file(self::SPAM_CONF, FILE_IGNORE_NEW_LINES) as $l) {
				if (preg_match('/^ENGINE=(spamassassin|bogofilter|none)$/', trim($l), $m)) {
					$e = $m[1];
				}
			}
		}

		return $e;
	}

	/** Addresses / domains of a white or black list (ser_wlist_a / ser_blist_a). */
	public static function spamList($ser)
	{
		$out = array();

		class_exists('Spam');   // defines Wlist_a / Blist_a, needed to unserialize the list

		foreach (self::forwardList($ser) as $v) {
			$v = strtolower(trim($v));

			// user@domain.com, @domain.com or domain.com; '*' and '?' as wildcards
			if ($v !== '' && preg_match('/^[a-z0-9._%+*?@-]+$/', $v)) {
				$out[] = $v;
			}
		}

		return array_values(array_unique($out));
	}

	private static function sieveStrings($list)
	{
		return '["' . implode('", "', $list) . '"]';
	}

	/**
	 * Spam rules of an account. Delivery adds the headers (X-Spam-Level: one star
	 * per point of the SpamAssassin score; X-Bogosity), the account decides here.
	 */
	private static function spamRules($a, $sp, &$req)
	{
		$action = $a['filter_spam_status'] ?? '';

		// not chosen yet: the Junk folder
		if (!in_array($action, array('spambox', 'mailbox', 'delete'), true)) {
			$action = 'spambox';
		}

		if (self::spamEngine() === 'spamassassin') {
			$hit = (int)round((float)($sp['spam_hit'] ?? 5));
			$hit = ($hit < 1) ? 5 : min(30, $hit);
			$isSpam = 'header :contains "X-Spam-Level" "' . str_repeat('*', $hit) . '"';
		} else {
			$isSpam = 'header :matches "X-Bogosity" "Spam,*"';
		}

		$tests = array();

		foreach (array('white' => 'ser_wlist_a', 'black' => 'ser_blist_a') as $k => $field) {
			$addr = array();
			$doms = array();

			foreach (self::spamList($sp[$field] ?? '') as $v) {
				if (strpos($v, '@') === false) {
					$doms[] = $v;
				} elseif ($v[0] === '@') {
					$doms[] = substr($v, 1);
				} else {
					$addr[] = $v;
				}
			}

			$tests[$k] = array();

			if ($addr) {
				$tests[$k][] = 'address :all :matches "from" ' . self::sieveStrings($addr);
			}

			if ($doms) {
				$tests[$k][] = 'address :domain :matches "from" ' . self::sieveStrings($doms);
			}
		}

		$todo = array();
		$tag = trim(str_replace(array('"', '\\', "\r", "\n", '$'), '', (string)($sp['subject_tag'] ?? '')));

		if ($tag !== '' && $action !== 'delete') {
			$req[] = 'editheader';
			$req[] = 'variables';
			$todo[] = '  if header :matches "Subject" "*" {' . "\n"
				. '    set "subject" "${1}";' . "\n"
				. '  }' . "\n"
				. '  deleteheader "Subject";' . "\n"
				. '  addheader :last "Subject" "' . $tag . ' ${subject}";';
		}

		if ($action === 'spambox') {
			$todo[] = "  fileinto \"Junk\";\n  stop;";
		} elseif ($action === 'delete') {
			$todo[] = "  discard;\n  stop;";
		}

		if (!$todo) {
			$todo[] = '  keep;';
		}

		$cond = $tests['black'] ? 'anyof (' . $isSpam . ', ' . implode(', ', $tests['black']) . ')' : $isSpam;
		$rule = "if {$cond} {\n" . implode("\n", $todo) . "\n}";

		if ($tests['white']) {
			$w = (count($tests['white']) > 1) ? 'anyof (' . implode(', ', $tests['white']) . ')' : $tests['white'][0];
			$rule = "if not {$w} {\n" . preg_replace('/^/m', '  ', $rule) . "\n}";
		}

		return '# spam filter (' . self::spamEngine() . ", spam: {$action})\n" . $rule;
	}

	/** Sieve script: autoresponder (vacation) and spam filing. */
	private static function writeSieve($dom, $user, $a, $sp = null)
	{
		$home = self::mailboxDir($dom, $user);
		$rules = array();
		$req = array('fileinto');

		// spam filter: on for the account and an engine on the server
		$flag = "{$home}/.kloxo-spam";
		$spamOn = $sp && (($sp['status'] ?? 'on') !== 'off') && (self::spamEngine() !== 'none');

		if ($spamOn) {
			$rules[] = self::spamRules($a, $sp, $req);

			if (!file_exists($flag)) {
				self::writeFile($flag, "on\n", 0600, self::VMAIL_UID, self::VMAIL_GID);
			}
		} elseif (file_exists($flag)) {
			@unlink($flag);
		}

		if (($a['autorespond_status'] ?? '') === 'on' && !empty($a['autores_name'])) {
			$db = new Sqlite(null, 'autoresponder');
			$r = $db->getRowsWhere("nname = '" . kn_sql_escape($a['autores_name']) . "'");

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
		exec("postmap " . escapeshellarg(self::dbType() . ":{$file}") . " 2>&1", $out, $ret);
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
