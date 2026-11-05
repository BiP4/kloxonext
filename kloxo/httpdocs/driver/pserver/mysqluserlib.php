<?php

// KloxoNext - MySQL users of a client, apart from the databases.
//
//   client -> mysqluser (a MySQL account: user@localhost and user@%)
//          -> mysqlgrant (one database the user may use, with its privileges)
//
// A user can be given several databases of the same client, a database several users.
// The database page lists who has access (mysqldbaccess, read only).

class Mysqluser extends Lxdb
{
	static $__table = 'mysqluser';

	static $__desc = array("", "", "mysql_user");
	static $__desc_nname = array("n", "", "mysql_user_name", URL_SHOW);
	static $__desc_username = array("", "", "mysql_user_name");
	static $__desc_dbpassword = array("n", "", "password");
	static $__desc_syncserver = array("", "", "database_server");
	static $__desc_access_f = array("", "", "mysql_user_databases");
	static $__desc_phpmyadmin_f = array("b", "", "", "__stub_phpmyadmin_url");

	static $__desc_mysqlgrant_l = array("db", "", "");

	static $__acdesc_update_password = array("", "", "password");
	static $__acdesc_update_phpmyadmin = array("", "", "phpmyadmin");

	// MySQL / MariaDB keep 32 characters for a user name everywhere
	const MAXLEN = 32;

	function inheritSynserverFromParent()
	{
		return false;
	}

	function isCoreBackup()
	{
		return true;
	}

	/** database administrator of $server (Admin > Database Admins) */
	static function knDbAdmin($server)
	{
		global $login;

		$db = new Sqlite(null, 'dbadmin');
		$res = $db->getRowsWhere("dbtype = 'mysql' AND syncserver = '" . kn_sql_escape((string)$server) . "'");

		if (!$res) {
			throw new lxException($login->isAdmin() ? 'e_no_dbadmin_entries_admin' : 'e_no_dbadmin_entries', '', $server);
		}

		return array('dbadmin' => $res[0]['dbadmin_name'], 'dbpassword' => $res[0]['dbpassword']);
	}

	function createExtraVariables()
	{
		$ret = self::knDbAdmin($this->syncserver ? $this->syncserver : 'localhost');
		$this->__var_dbadmin = $ret['dbadmin'];
		$this->__var_dbpassword = $ret['dbpassword'];
	}

	/** prefix of the users (and databases) of a client: '<client>_' (none for the administrator) */
	static function knPrefix($client)
	{
		if ($client->isAdmin()) {
			return '';
		}

		return substr(preg_replace('/[^a-z0-9]/', '', strtolower((string)$client->nname)), 0, 15) . '_';
	}

	static function knValidateName($name)
	{
		global $login;

		if (!preg_match('/^[a-z0-9_]{1,' . self::MAXLEN . '}$/', $name) || ($name === 'root') || (strpos($name, 'mysql') === 0)) {
			throw new lxException($login->getThrow("mysql_invalid_user_name"), '', $name);
		}
	}

	static function add($parent, $class, $param)
	{
		global $login;

		$name = strtolower(trim((string)$param['nname']));
		$prefix = self::knPrefix($parent);

		if (($prefix !== '') && (strpos($name, $prefix) !== 0)) {
			$name = $prefix . $name;
		}

		self::knValidateName($name);

		$param['dbpassword'] = trim((string)$param['dbpassword']);
		validate_password($param['dbpassword']);

		// a user of the panel, or the user of a database (same name as the database)
		if (exists_in_db(null, 'mysqluser', $name) || db_get_count('mysqldb', "username = '" . kn_sql_escape($name) . "'")) {
			throw new lxException($login->getThrow("user_already_exists"), '', $name);
		}

		$param['nname'] = $name;
		$param['username'] = $name;

		return $param;
	}

	function postAdd()
	{
		$client = $this->getParentO();
		$this->syncserver = $client->mysqldbsyncserver ? $client->mysqldbsyncserver : 'localhost';
		$this->fixSyncServer();
	}

	static function AddListForm($parent, $class)
	{
		return self::addform($parent, $class);
	}

	static function addform($parent, $class, $typetd = null)
	{
		$prefix = self::knPrefix($parent);

		$vlist['nname'] = ($prefix !== '') ? array('m', array('pretext' => $prefix)) : null;
		$vlist['dbpassword'] = null;

		$ret['variable'] = $vlist;
		$ret['action'] = 'add';

		return $ret;
	}

	function updateform($subaction, $param)
	{
		switch ($subaction) {
			case "password":
				$vlist['nname'] = array('M', $this->nname);
				$vlist['dbpassword'] = null;

				return $vlist;
		}

		return parent::updateform($subaction, $param);
	}

	function updatePassword($param)
	{
		$param['dbpassword'] = trim((string)$param['dbpassword']);
		validate_password($param['dbpassword']);

		return $param;
	}

	function createShowUpdateform()
	{
		$uflist['password'] = null;

		return $uflist;
	}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
		$alist['property'][] = 'a=updateform&sa=password';
		$alist['property'][] = 'a=list&c=mysqlgrant';

		$pma = $this->getStubUrl('__stub_phpmyadmin_url');

		if ($pma) {
			$alist['property'][] = $pma;
		}
	}

	function createShowClist($subaction)
	{
		$clist['mysqlgrant'] = null;

		return $clist;
	}

	function getStubUrl($name)
	{
		if ($name === '__stub_phpmyadmin_url') {
			return create_simpleObject(array('url' => kn_pma_sso_url('/thirdparty/phpMyAdmin/', $this->username, $this->dbpassword),
				'purl' => "c=mysqldb&a=updateform&sa=phpmyadmin", 'target' => "target='_blank'"));
		}

		return null;
	}

	/** the databases of the user, as listed: 'db1 (ALL), db2 (SELECT, INSERT)' */
	function display($var)
	{
		if ($var === 'access_f') {
			$out = array();

			foreach (Mysqlgrant::knRows("username = '" . kn_sql_escape($this->nname) . "'") as $r) {
				$out[] = $r['dbname'] . ' (' . Mysqlgrant::knShort($r['privileges']) . ')';
			}

			return $out ? implode(', ', $out) : '-';
		}

		return parent::display($var);
	}

	static function createListNlist($parent, $view)
	{
		$nlist['phpmyadmin_f'] = '3%';
		$nlist['nname'] = '30%';
		$nlist['access_f'] = '67%';

		return $nlist;
	}

	static function createListAlist($parent, $class)
	{
		$alist[] = "a=list&c=mysqldb";
		$alist[] = "a=list&c=$class";

		return $alist;
	}
}

class Mysqlgrant extends Lxdb
{
	static $__table = 'mysqlgrant';

	static $__desc = array("", "", "mysql_database_access");
	static $__desc_nname = array("", "", "mysql_database_access");
	static $__desc_username = array("n", "", "mysql_user_name");
	static $__desc_dbname = array("n", "", "mysql_database_name");
	static $__desc_privileges = array("", "", "mysql_privileges");
	static $__desc_syncserver = array("", "", "database_server");

	static $__acdesc_update_update = array("", "", "mysql_privileges");

	// what can be given on one database (ALL = every one of them)
	static $privlist = array('ALL', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX',
		'REFERENCES', 'CREATE TEMPORARY TABLES', 'LOCK TABLES', 'EXECUTE', 'CREATE VIEW', 'SHOW VIEW',
		'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER');

	function inheritSynserverFromParent()
	{
		return true;
	}

	function isCoreBackup()
	{
		return true;
	}

	function createExtraVariables()
	{
		$ret = Mysqluser::knDbAdmin($this->syncserver ? $this->syncserver : 'localhost');
		$this->__var_dbadmin = $ret['dbadmin'];
		$this->__var_dbpassword = $ret['dbpassword'];
	}

	static function knRows($where)
	{
		$sq = new Sqlite(null, 'mysqlgrant');

		return (array)$sq->getRowsWhere($where);
	}

	static function knShort($privs)
	{
		$p = array_filter(array_map('trim', explode(',', (string)$privs)));

		if (in_array('ALL', $p, true)) {
			return 'ALL';
		}

		return (count($p) > 4) ? implode(', ', array_slice($p, 0, 4)) . ', +' . (count($p) - 4) : implode(', ', $p);
	}

	/** the privileges asked for, checked against the list: 'SELECT,INSERT' (ALL alone when chosen) */
	static function knPrivileges($v)
	{
		global $login;

		$in = is_array($v) ? $v : explode(',', (string)$v);
		$out = array();

		foreach ($in as $p) {
			$p = strtoupper(trim((string)$p));

			if ($p === '') {
				continue;
			}

			if (!in_array($p, self::$privlist, true)) {
				throw new lxException($login->getThrow("mysql_invalid_privilege"), '', $p);
			}

			$out[$p] = $p;
		}

		if (!$out) {
			throw new lxException($login->getThrow("mysql_invalid_privilege"), '', "choose at least one privilege");
		}

		return isset($out['ALL']) ? 'ALL' : implode(',', array_values($out));
	}

	/** databases of the client of $user that can be given to it */
	static function knDatabases($user)
	{
		$client = $user->getParentO();
		$list = array();

		foreach ((array)$client->getList('mysqldb') as $d) {
			$list[] = $d->dbname;
		}

		sort($list);

		return $list;
	}

	static function add($parent, $class, $param)
	{
		global $login;

		$db = trim((string)$param['dbname']);

		// only a database of the same client
		if (!in_array($db, self::knDatabases($parent), true)) {
			throw new lxException($login->getThrow("mysql_invalid_database"), '', $db);
		}

		$param['privileges'] = self::knPrivileges($param['privileges']);
		$param['username'] = $parent->nname;
		$param['dbname'] = $db;
		$param['nname'] = "{$parent->nname}___{$db}";

		if (exists_in_db(null, 'mysqlgrant', $param['nname'])) {
			throw new lxException($login->getThrow("mysql_access_exists"), '', $db);
		}

		return $param;
	}

	static function AddListForm($parent, $class)
	{
		return self::addform($parent, $class);
	}

	static function addform($parent, $class, $typetd = null)
	{
		global $login;

		$given = array();

		foreach (self::knRows("username = '" . kn_sql_escape($parent->nname) . "'") as $r) {
			$given[] = $r['dbname'];
		}

		$dbs = array_values(array_diff(self::knDatabases($parent), $given));

		if (!$dbs) {
			throw new lxException($login->getThrow("mysql_no_database_left"), '', $parent->nname);
		}

		$vlist['username'] = array('M', $parent->nname);
		$vlist['dbname'] = array('s', $dbs);
		$vlist['privileges'] = array('U', self::$privlist);

		$ret['variable'] = $vlist;
		$ret['action'] = 'add';

		return $ret;
	}

	function updateform($subaction, $param)
	{
		// the multiple select shows the privileges given
		$this->privileges = array_values(array_filter(explode(',', (string)$this->privileges)));

		$vlist['username'] = array('M', $this->username);
		$vlist['dbname'] = array('M', $this->dbname);
		$vlist['privileges'] = array('U', self::$privlist);

		return $vlist;
	}

	function update($subaction, $param)
	{
		$param['privileges'] = self::knPrivileges($param['privileges']);

		return $param;
	}

	function createShowUpdateform()
	{
		$uflist['update'] = null;

		return $uflist;
	}

	function display($var)
	{
		if ($var === 'privileges') {
			return str_replace(',', ', ', (string)$this->privileges);
		}

		return parent::display($var);
	}

	static function createListNlist($parent, $view)
	{
		$nlist['dbname'] = '30%';
		$nlist['privileges'] = '70%';

		return $nlist;
	}

	static function createListAlist($parent, $class)
	{
		$alist[] = "a=list&c=$class";
		$alist[] = "a=addform&c=$class";

		return $alist;
	}
}

// the database page: who may use it (read only; edited on the user's page)
class Mysqldbaccess extends Lxclass
{
	static $__ttype = "transient";
	static $__desc = array("", "", "mysql_database_access");

	static $__desc_nname = array("", "", "mysql_database_access");
	static $__desc_username = array("", "", "mysql_user_name", "__stub_mysqluser");
	static $__desc_privileges = array("", "", "mysql_privileges");

	function get() {}
	function write() {}

	static function perPage() { return 500; }
	static function defaultSort() { return "username"; }
	static function defaultSortDir() { return "asc"; }
	static function searchVar() { return "username"; }

	static function createListNlist($parent, $view)
	{
		return array('username' => '30%', 'privileges' => '70%');
	}

	static function createListAlist($parent, $class)
	{
		return array("a=list&c=$class");
	}

	static function createListBlist($parent, $class)
	{
		return null;
	}

	function isSelect()
	{
		return false;
	}

	static function initThisListRule($parent, $class) { return null; }

	static function initThisList($parent, $class)
	{
		$res = array();

		if (!$parent || !$parent->isClass('mysqldb')) {
			return $res;
		}

		// the database's own user (created with it)
		if ((string)$parent->username !== '') {
			$res[] = array('nname' => "own_{$parent->username}", 'parent_clname' => $parent->getClName(),
				'username' => (string)$parent->username, 'privileges' => 'ALL (user of the database)');
		}

		foreach (Mysqlgrant::knRows("dbname = '" . kn_sql_escape($parent->dbname) . "'") as $r) {
			$res[] = array('nname' => $r['nname'], 'parent_clname' => $parent->getClName(),
				'username' => $r['username'], 'privileges' => str_replace(',', ', ', $r['privileges']));
		}

		return $res;
	}

	// the user's page, under the same client as this page
	function getStubUrl($var)
	{
		global $ghtml;

		if (strpos((string)$this->nname, 'own_') === 0) {
			return null;
		}

		$q = array('frm_action' => 'show');
		$i = 0;

		foreach ((array)$ghtml->frm_o_o as $o) {
			if (!isset($o['class']) || ($o['class'] === 'mysqldb')) {
				break;
			}

			$q["frm_o_o[{$i}][class]"] = $o['class'];

			if (isset($o['nname'])) {
				$q["frm_o_o[{$i}][nname]"] = $o['nname'];
			}

			$i++;
		}

		$q["frm_o_o[{$i}][class]"] = 'mysqluser';
		$q["frm_o_o[{$i}][nname]"] = $this->username;

		return create_simpleObject(array('url' => '/display.php?' . http_build_query($q),
			'purl' => 'a=show&o=mysqluser', 'target' => null));
	}
}
