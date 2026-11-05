<?php

// KloxoNext - the access of a MySQL user to one database (see mysqluserlib.php)

class Mysqlgrant__mysql extends lxDriverClass
{
	function knDb()
	{
		return Mysqluser__mysql::knConnect($this->main->__var_dbadmin, $this->main->__var_dbpassword);
	}

	function knTarget()
	{
		return '`' . str_replace('`', '``', (string)$this->main->dbname) . '`.*';
	}

	/** privileges, from the panel's list only (Mysqlgrant::knPrivileges checked them) */
	function knPrivSql()
	{
		$p = array_intersect(explode(',', (string)$this->main->privileges), Mysqlgrant::$privlist);

		return in_array('ALL', $p, true) ? 'ALL PRIVILEGES' : implode(', ', $p);
	}

	function knRevoke($rdb)
	{
		foreach (array('localhost', '%') as $h) {
			// nothing given yet is not an error
			$rdb->query("REVOKE ALL PRIVILEGES ON {$this->knTarget()} FROM " . Mysqluser__mysql::knAccount($rdb, $this->main->username, $h));
		}
	}

	function knApply()
	{
		$rdb = $this->knDb();
		$this->knRevoke($rdb);

		foreach (array('localhost', '%') as $h) {
			Mysqluser__mysql::knRun($rdb, "GRANT {$this->knPrivSql()} ON {$this->knTarget()} TO "
				. Mysqluser__mysql::knAccount($rdb, $this->main->username, $h));
		}

		$rdb->query("FLUSH PRIVILEGES");
	}

	function dbactionAdd()
	{
		$this->knApply();
	}

	function dbactionUpdate($subaction)
	{
		$this->knApply();
	}

	function dbactionDelete()
	{
		$rdb = $this->knDb();
		$this->knRevoke($rdb);
		$rdb->query("FLUSH PRIVILEGES");
	}
}
