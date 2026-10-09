<?php

// KloxoNext - mail accounts on Postfix + Dovecot (see lib/php/mailmapslib.php)
class Mailaccount__Postfix extends lxDriverClass
{
	static function Mailaccdisk_usage($accname)
	{
		return KnMail::diskUsage($accname);
	}

	function dbactionAdd()
	{
		global $login;

		if (!$this->main->password) {
			$this->main->password = lx_password_hash(randomString(12));
		}

		list($user, $domain) = explode('@', $this->main->nname, 2);

		if (!preg_match('/^[a-z0-9._+-]+$/i', $user)) {
			throw new lxException($login->getThrow("mailaccount_add_failed"), '', $this->main->nname);
		}

		KnMail::ensureMailbox($domain, $user);
		KnMail::scheduleRebuild();
	}

	function dbactionDelete()
	{
		list($user, $domain) = explode('@', $this->main->nname, 2);

		KnMail::scheduleRebuild();
		KnMail::removeMailbox($domain, $user);
	}

	function dbactionUpdate($subaction)
	{
		switch ($subaction) {
			case "train_as_system_spam":
			case "train_as_system_ham":
			case "train_as_spam":
			case "train_as_ham":
				$this->trainAsSpam();
				break;

			case "clear_spam_db":
				break;

			default:
				// password, limit (quota), toggle_status, forwards, autoresponder,
				// spam filter, configuration ... all generated from the database
				KnMail::scheduleRebuild();
		}
	}

	function trainAsSpam()
	{
		$listname = "{$this->main->subaction}_list";
		$flag = cse($this->main->subaction, '_spam') ? '--spam' : '--ham';

		foreach ((array)$this->main->$listname as $f) {
			$file = str_replace(array("_s_coma_s_", "_s_colon_s_"), array(",", ":"), $f);

			if (file_exists('/usr/bin/sa-learn')) {
				exec("/usr/bin/sa-learn {$flag} " . escapeshellarg($file) . " >/dev/null 2>&1");
			} elseif (file_exists('/usr/bin/bogofilter')) {
				$b = ($flag === '--spam') ? '-s' : '-n';
				exec("bogofilter -d /var/lib/bogofilter {$b} < " . escapeshellarg($file) . " >/dev/null 2>&1");
			}
		}
	}
}
