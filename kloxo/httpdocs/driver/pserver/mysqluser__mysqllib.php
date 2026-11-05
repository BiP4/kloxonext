<?php

// KloxoNext - MySQL / MariaDB accounts of the panel (see mysqluserlib.php)

class Mysqluser__mysql extends lxDriverClass
{
	static function knConnect($admin, $pass)
	{
		global $login;

		$rdb = @new mysqli('localhost', $admin, $pass);

		if ($rdb->connect_errno) {
			log_error($rdb->connect_error);

			throw new lxException($login->getThrow('could_not_connect_to_db'), '', $rdb->connect_error);
		}

		return $rdb;
	}

	/** 'user'@'host' with the value escaped */
	static function knAccount($rdb, $user, $host)
	{
		return "'" . $rdb->real_escape_string($user) . "'@'" . $rdb->real_escape_string($host) . "'";
	}

	static function knRun($rdb, $sql, $throw = true)
	{
		global $login;

		if (!$rdb->query($sql) && $throw) {
			throw new lxException($login->getThrow('mysql_error'), '', $rdb->error);
		}
	}

	function knDb()
	{
		return self::knConnect($this->main->__var_dbadmin, $this->main->__var_dbpassword);
	}

	function dbactionAdd()
	{
		global $login;

		$rdb = $this->knDb();
		$u = $this->main->username;

		$res = $rdb->query("SELECT 1 FROM mysql.user WHERE User = '" . $rdb->real_escape_string($u) . "'");

		if ($res && $res->num_rows) {
			throw new lxException($login->getThrow('user_already_exists'), '', $u);
		}

		$p = "'" . $rdb->real_escape_string($this->main->dbpassword) . "'";

		foreach (array('localhost', '%') as $h) {
			self::knRun($rdb, "CREATE USER " . self::knAccount($rdb, $u, $h) . " IDENTIFIED BY {$p}");
		}

		$rdb->query("FLUSH PRIVILEGES");
	}

	function dbactionDelete()
	{
		$rdb = $this->knDb();

		foreach (array('localhost', '%') as $h) {
			self::knRun($rdb, "DROP USER IF EXISTS " . self::knAccount($rdb, $this->main->username, $h), false);
		}

		$rdb->query("FLUSH PRIVILEGES");
	}

	function dbactionUpdate($subaction)
	{
		$rdb = $this->knDb();
		$p = "'" . $rdb->real_escape_string($this->main->dbpassword) . "'";

		foreach (array('localhost', '%') as $h) {
			self::knRun($rdb, "ALTER USER " . self::knAccount($rdb, $this->main->username, $h) . " IDENTIFIED BY {$p}");
		}

		$rdb->query("FLUSH PRIVILEGES");
	}
}
