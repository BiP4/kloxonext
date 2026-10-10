<?php

class Lxupdate extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "",  "update");

	// Mysql
	static $__desc_nname = array("n", "",  "_version", "a=show");
	static $__desc_state = array("e", "",  "state", "a=show");
	static $__desc_schedule = array("n", "",  "_schedule_update_later", "a=show");

	static $__desc_detected_version_f = array("", "",  "detected_version");
	static $__desc_installed_rpm_version_f = array("", "",  "installed_rpm_version");
	static $__desc_latest_rpm_version_f = array("", "",  "latest_rpm_version");

	static $__desc_buglist_f = array("T", "",  "bugs_in_this_version");

	// MR -- add new var
	static $__desc_stamp_f = array("", "",  "stamp");
	static $__desc_step_f = array("", "",  "step");
	static $__desc_name_f = array("", "",  "Name");
	static $__desc_note_f = array("", "",  "Note");

	static $__desc_updatewarning_f = array("", "",  "Attention");

	// KloxoNext - updates from the git repository (GitHub)
	static $__desc_update_source_f = array("", "",  "update_source");
	static $__desc_installed_commit_f = array("", "",  "installed_commit");
	static $__desc_latest_commit_f = array("", "",  "latest_commit");
	static $__desc_auto_update_panel_flag = array("f", "",  "auto_update_panel");
	static $__acdesc_update_autoupdate = array("", "",  "automatic_updates");

	static $__acdesc_update_lxupdateinfo = array("", "",  "update");
	static $__acdesc_update_bugs = array("", "",  "bugs");

	static $__desc_releasenote_l = array("", "",  "");

	function get(){}
	function write(){}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
		$alist['property'][] = "a=list&c=releasenote";
	}

	function createShowAlist(&$alist, $subaction = null)
	{
		global $gbl, $sgbl, $login, $ghtml;
		
		return $alist;
		
		// MR -- still used?
	}

	function createShowUpdateform()
	{
		$uflist['lxupdateinfo'] = null;
		$uflist['autoupdate'] = null;

		return $uflist;
	}

	static function getUpdateSource()
	{
		$src = '/usr/local/lxlabs/kloxo/etc/conf/update-source.conf';

		return file_exists($src) ? (array)parse_ini_file($src) : array();
	}

	static function getInstalledCommit()
	{
		return trim((string)@file_get_contents('/usr/local/lxlabs/kloxo/etc/conf/update-commit'));
	}

	static function getLatestCommit()
	{
		$conf = self::getUpdateSource();

		if (empty($conf['REPO_URL'])) {
			return '';
		}

		$branch = !empty($conf['BRANCH']) ? $conf['BRANCH'] : 'main';
		$out = array();

		exec("timeout 20 git ls-remote " . escapeshellarg($conf['REPO_URL']) . " "
			. escapeshellarg("refs/heads/{$branch}") . " 2>/dev/null", $out);

		$c = isset($out[0]) ? strtok($out[0], "\t") : '';

		return preg_match('/^[0-9a-f]{40}$/', (string)$c) ? $c : '';
	}

	static function isPanelUpdating()
	{
		$pid = (int)@file_get_contents('/usr/local/lxlabs/kloxo/log/kloxonext-update.pid');

		return ($pid > 0) && posix_kill($pid, 0);
	}

	static function getAutoUpdateFlagFile()
	{
		return '/usr/local/lxlabs/kloxo/etc/flag/auto-update-panel.flg';
	}

	function updateform($subaction, $param)
	{
		global $gbl, $sgbl, $login, $ghtml;
		
		$maj = $sgbl->__ver_major;

		switch($subaction) {
			case "lxupdateinfo":
				$conf = self::getUpdateSource();
				$installed = self::getInstalledCommit();
				$latest = self::getLatestCommit();

				$vlist['name_f'] = array('M', $sgbl->__ver_name);
				$vlist['note_f'] = array('M', $sgbl->__ver_note);

				$vlist['detected_version_f'] = array('M', $sgbl->__ver_full);
				$vlist['installed_rpm_version_f'] = array('M', getInstalledVersion());
				$vlist['latest_rpm_version_f'] = array('M', getLatestVersion());

				$vlist['update_source_f'] = array('M', !empty($conf['REPO_URL'])
					? preg_replace('~^https?://~', '', $conf['REPO_URL']) . ' (' . (!empty($conf['BRANCH']) ? $conf['BRANCH'] : 'main') . ')' : '-');
				$vlist['installed_commit_f'] = array('M', $installed ? substr($installed, 0, 12) : '-');
				$vlist['latest_commit_f'] = array('M', $latest ? substr($latest, 0, 12) : '-');

				if (self::isPanelUpdating()) {
					$vlist['updatewarning_f'] = array('W', $login->getThrow("program_is_already_updating"));
					$vlist['__v_button'] = array();
				} else if ($latest && ($latest === $installed)) {
					$vlist['__v_button'] = array();
				} else {
					$vlist['updatewarning_f'] = array('W', $login->getKeywordUc('panel_update_warning'));
					$vlist['__v_button'] = "Update Now";
				}

				return $vlist;

			case "autoupdate":
				$this->auto_update_panel_flag = file_exists(self::getAutoUpdateFlagFile()) ? 'on' : 'off';
				$vlist['auto_update_panel_flag'] = null;

				return $vlist;

			case "bugs":
				$file = "bugs/bugs-{$sgbl->__ver_major_minor_release}.txt";
				$content = curl_get_file_contents($file);
				$content = trim($content);
				
				if (!$content) {
					$content = "There are no Bugs Reported for this Version";
				}
				
				$vlist['buglist_f'] = array('t', $content);
				
				return $vlist;
		}
	}

	function updateAutoupdate($param)
	{
		global $login;

		// only the administrator may let the panel replace its own code
		if (!$login->isAdmin()) {
			throw new lxException($login->getThrow("no_permission"));
		}

		$f = self::getAutoUpdateFlagFile();

		if (isset($param['auto_update_panel_flag']) && ($param['auto_update_panel_flag'] === 'on')) {
			lfile_put_contents($f, date('c') . " enabled by {$login->nname}\n");
		} else if (file_exists($f)) {
			unlink($f);
		}

		return null;
	}

	function updateLxupdateInfo()
	{
		global $login;

		if (!$login->isAdmin()) {
			throw new lxException($login->getThrow("no_permission"));
		}

		if (self::isPanelUpdating()) {
			throw new lxException($login->getThrow("program_is_already_updating"));
		} else {
			rl_exec_get($this->__masterserver, 'localhost', array('lxupdate', 'execUpdate'), null);

			throw new lxException($login->getThrow("update_scheduled"));
		}
	}

	static function execUpdate()
	{
		// KloxoNext - own systemd unit: the cleanup restarts the panel (and this PHP)
		exec("systemctl start --no-block kloxo-panel-update.service >/dev/null 2>&1");
	}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		global $gbl, $sgbl, $login, $ghtml;
		
	/*
		if (!$parent->isLocalhost('nname')) {
			throw new lxException($login->getThrow("slave_is_automatically_updated"), '', $parent->nname);
		}
	*/
		
		$thisversion = $sgbl->__ver_major_minor_release;
		$upversion = getLatestVersion();
		
		return $upversion;
	}
}

