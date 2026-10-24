<?php 

class ftpuser__pureftp extends lxDriverClass
{
	function dbactionAdd()
	{
		global $gbl, $sgbl, $login, $ghtml; 

		$dir = $this->main->__var_full_directory;
		$dir = expand_real_root($dir);
		$pass = $this->main->realpass;

		if (!lxfile_exists($dir)) {
			lxfile_mkdir($dir);
			lxfile_unix_chown($dir, $this->main->__var_username);
		}

		if (!$pass) { $pass = randomString(8); }

		lxshell_input("$pass\n$pass\n", "pure-pw", "useradd",  $this->main->nname, "-u", $this->main->__var_username, "-d",  $dir, "-m");
		$this->knUseStoredHash(true);

		$this->setQuota();

		// If the user is added is fully formed, this makes sure that all his properties are synced.
		$this->toggleStatus();
	}

	// MR -- function to combine add + quota + status without '-m' (create .pdb) - using by fixftpuser
	function setFix()
	{
		global $gbl, $sgbl, $login, $ghtml; 

		$dir = $this->main->__var_full_directory;
		$dir = expand_real_root($dir);
		$pass = $this->main->realpass;

		$nname = $this->main->nname;
		$username = $this->main->__var_username;

		if (!lxfile_exists($dir)) {
			lxfile_mkdir($dir);
			lxfile_unix_chown($dir, $username);
		}

		if (!$pass) { $pass = randomString(8); }

	//	if ($this->main->isOn('status')) {
		if ($this->main->status == 'on') {
			$z = "0000-2359";
		} else {
			$z = "0000-0000";
		}


		if ($this->main->ftp_disk_usage > 0) {
			$q = $this->main->ftp_disk_usage;
			lxshell_input("$pass\n$pass\n", "pure-pw", "useradd",  $nname, "-u", $username, "-d", $dir, "-N", $q, "-z", $z);
		} else {
			lxshell_input("$pass\n$pass\n", "pure-pw", "useradd",  $nname, "-u", $username, "-d", $dir, "-z", $z);
		}

		// fixftpuser builds the .pdb once at the end
		$this->knUseStoredHash(false);
	}

	// KloxoNext - accounts of an older Kloxo may keep only the password hash (no clear
	// password in the panel): that hash (crypt() format, which pure-ftpd checks) replaces
	// the random password the account was created with
	function knUseStoredHash($mkdb)
	{
		$hash = (string)$this->main->password;

		if ($this->main->realpass || !preg_match('/^\$(1|2[aby]|5|6)\$[^:\s]+$/', $hash)) {
			return;
		}

		$file = '/etc/pure-ftpd/pureftpd.passwd';
		$lines = @file($file, FILE_IGNORE_NEW_LINES);

		if (!$lines) {
			return;
		}

		foreach ($lines as $i => $l) {
			$f = explode(':', $l);

			if ((count($f) > 2) && ($f[0] === $this->main->nname)) {
				$f[1] = $hash;
				$lines[$i] = implode(':', $f);
			}
		}

		file_put_contents($file, implode("\n", $lines) . "\n");

		if ($mkdb) {
			exec("pure-pw mkdb");
		}
	}

	function dbactionDelete()
	{
		global $gbl, $sgbl, $login, $ghtml; 


		$u = $this->main->__var_username;
		$n = $this->main->nname;

		$c = db_get_count("ftpuser", "nname = '{$n}' AND username = '{$u}'");
		$d = db_get_count("web", "nname = '{$n}' AND username = '{$u}'");

		if (((int)$c !== 0) && ((int)$d !== 0)) {
			throw new lxException($login->getThrow("no_permit_to_delete_main_ftpuser"), '', $n);
		}

		lxshell_return("pure-pw", "userdel", $n, "-m");
	}

	function toggleStatus()
	{
	//	if ($this->main->isOn('status')) {
		if ($this->main->status == 'on') {
			lxshell_return("pure-pw", "usermod", $this->main->nname, "-z", "0000-2359", "-m");
		} else {
			lxshell_return("pure-pw", "usermod", $this->main->nname, "-z", "0000-0000", "-m");
		}
	}

	function setQuota()
	{
		if ($this->main->ftp_disk_usage > 0) {
			lxshell_return("pure-pw", "usermod", $this->main->nname, "-N", $this->main->ftp_disk_usage, "-m");
		} else {
			// This is because the shell_return cannot send '' to the program.
			$cmd = "pure-pw usermod " . escapeshellarg($this->main->nname) . " -N '' -m";
			log_log("shell_exec", $cmd);
			exec($cmd);
		}
	}

	function dbactionUpdate($subaction)
	{
		global $gbl, $sgbl, $login, $ghtml; 

		$dir = $this->main->__var_full_directory;
		$dir = expand_real_root($dir);

		switch($subaction) {
			case "full_update":
				$pass = $this->main->realpass;
				lxshell_input("$pass\n$pass\n", "pure-pw", "passwd", $this->main->nname, "-m");
				lxshell_return("pure-pw", "usermod", $this->main->nname, "-d", $dir, "-m");
				$this->toggleStatus();
				$this->setQuota();
				break;

			case "password":
				$pass = $this->main->realpass;
				lxshell_input("$pass\n$pass\n", "pure-pw", "passwd", $this->main->nname, "-m");
				break;

			case "toggle_status":
				$this->toggleStatus();
				break;

			case "edit":
				lxshell_return("pure-pw", "usermod", $this->main->nname, "-d", $dir, "-m");
				$this->setQuota();
				break;

			case "changeowner":
				lxshell_return("pure-pw", "usermod", $this->main->nname, "-u", $this->main->__var_username, "-d", $dir, "-m");
				break;

			case "fix":
				$this->setFix();
				break;
		}
	}
}


