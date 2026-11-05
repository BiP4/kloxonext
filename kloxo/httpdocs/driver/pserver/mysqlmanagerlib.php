<?php

// KloxoNext - "Manage My Databases": the MySQL page of an account on one screen, like cPanel.
//
//   Create New Database      with a user of the same name, or the database alone
//   Modify Databases         check / repair (CHECK TABLE / REPAIR TABLE of every table)
//   Current Databases        size, users with access (remove), phpMyAdmin, delete
//   Add New User             user + password (strength, generator)
//   Add User To Database     one or more privileges, or ALL
//   Current Users            databases, change password, phpMyAdmin as the user, delete
//
// Every action goes through the panel's own objects (mysqldb, mysqluser, mysqlgrant): same
// checks, quotas and drivers as the separate pages.

class mysqlmanager extends lxclass
{
	static $__desc = array("", "", "mysql_manager");
	static $__desc_nname = array("", "", "mysql_manager");

	static $__acdesc_show = array("", "", "mysql_manager");

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'mysqlmanager';
	}

	// ------------------------------------------------------------ helpers

	static function h($s)
	{
		return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	}

	/** form token of this page (HMAC of the panel session), on top of the panel's own */
	static function token()
	{
		global $gbl, $sgbl;

		$f = "{$sgbl->__path_program_root}/etc/conf/mysqlmgr-form.key";

		if (!is_file($f)) {
			@file_put_contents($f, bin2hex(random_bytes(32)));
			@chmod($f, 0600);
		}

		$sid = isset($gbl->c_session->nname) ? $gbl->c_session->nname : '';

		return hash_hmac('sha256', 'mysqlmgr|' . $sid, (string)@file_get_contents($f));
	}

	static function url($anchor = '')
	{
		$q = array();
		parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q);

		return '/display.php?' . http_build_query($q) . ($anchor ? "#{$anchor}" : '');
	}

	function client()
	{
		return $this->getParentO();
	}

	/** prefix of the account's databases and users ('' for the administrator) */
	function dbPrefix()
	{
		$c = $this->client();

		return $c->isAdmin() ? '' : substr((string)$c->nname, 0, 15) . '_';
	}

	function userPrefix()
	{
		return Mysqluser::knPrefix($this->client());
	}

	function databases()
	{
		$out = array();

		foreach ((array)$this->client()->getList('mysqldb') as $k => $d) {
			$out[(string)$d->dbname] = $d;
		}

		ksort($out);

		return $out;
	}

	function users()
	{
		$out = array();

		foreach ((array)$this->client()->getList('mysqluser') as $u) {
			$out[(string)$u->nname] = $u;
		}

		ksort($out);

		return $out;
	}

	/** username => [dbname => privileges] of the account's users */
	function grants($users)
	{
		$out = array();

		if (!$users) {
			return $out;
		}

		$in = implode(',', array_map(function ($u) { return "'" . kn_sql_escape($u) . "'"; }, array_keys($users)));

		foreach (Mysqlgrant::knRows("username IN ({$in})") as $r) {
			$out[$r['username']][$r['dbname']] = $r['privileges'];
		}

		return $out;
	}

	function dbAdmin()
	{
		$c = $this->client();
		$a = Mysqluser::knDbAdmin($c->mysqldbsyncserver ? $c->mysqldbsyncserver : 'localhost');

		return Mysqluser__mysql::knConnect($a['dbadmin'], $a['dbpassword']);
	}

	/** bytes used by each database */
	function sizes($dbs)
	{
		$out = array();

		if (!$dbs) {
			return $out;
		}

		try {
			$rdb = $this->dbAdmin();
			$in = implode(',', array_map(function ($d) use ($rdb) { return "'" . $rdb->real_escape_string($d) . "'"; }, array_keys($dbs)));
			$res = $rdb->query("SELECT table_schema, SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema IN ({$in}) GROUP BY table_schema");

			while ($res && ($r = $res->fetch_row())) {
				$out[$r[0]] = (int)$r[1];
			}
		} catch (Exception $e) {
		}

		return $out;
	}

	static function size($b)
	{
		if ($b === null) {
			return '-';
		}

		foreach (array('B', 'KB', 'MB', 'GB') as $i => $u) {
			if (($b < 1024) || ($u === 'GB')) {
				return ($i ? number_format($b, 2) : $b) . " {$u}";
			}

			$b /= 1024;
		}
	}

	static function pma($user, $pass)
	{
		return kn_pma_sso_url('/thirdparty/phpMyAdmin/', $user, $pass);
	}

	static function errorText($e)
	{
		$m = isset($e->message) ? (string)$e->message : $e->getMessage();
		$v = isset($e->value) ? (string)$e->value : '';

		return trim($m . (($v !== '') ? ": {$v}" : ''));
	}

	// ------------------------------------------------------------ actions

	function handlePost(&$msg, &$out)
	{
		$a = isset($_POST['kn_my_action']) ? (string)$_POST['kn_my_action'] : '';

		if ($a === '') {
			return;
		}

		if (!isset($_POST['kn_my_token']) || !hash_equals(self::token(), (string)$_POST['kn_my_token'])) {
			$msg = array('err', 'The form expired. Reload the page and try again.');

			return;
		}

		$p = function ($k) {
			return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
		};

		try {
			switch ($a) {
				case 'createdb':
					$this->createDb($p('db'), isset($_POST['with_user']), $p('pass'), $p('pass2'), $msg);

					break;
				case 'deletedb':
					$this->deleteDb($p('db'), $msg);

					break;
				case 'check':
				case 'repair':
					$this->checkDb($a, $p('db'), $msg, $out);

					break;
				case 'adduser':
					$this->addUser($p('user'), $p('pass'), $p('pass2'), $msg);

					break;
				case 'passwd':
					$this->setPassword($p('user'), $p('pass'), $p('pass2'), $msg);

					break;
				case 'deluser':
					$this->deleteUser($p('user'), $msg);

					break;
				case 'grant':
					$this->grant($p('user'), $p('db'), isset($_POST['priv']) ? (array)$_POST['priv'] : array(), $msg);

					break;
				case 'revoke':
					$this->revoke($p('user'), $p('db'), $msg);

					break;
				default:
					$msg = array('err', 'Unknown action.');
			}
		} catch (Exception $e) {
			$msg = array('err', self::errorText($e));
		}
	}

	function passwordsMatch($a, $b)
	{
		global $login;

		if ($a !== $b) {
			throw new lxException('The passwords do not match', '', '');
		}
	}

	function createDb($name, $withUser, $pass, $pass2, &$msg)
	{
		$c = $this->client();
		$prefix = $this->dbPrefix();
		$name = strtolower($name);

		// typed with the prefix: added only once
		if (($prefix !== '') && (strpos($name, $prefix) === 0)) {
			$name = substr($name, strlen($prefix));
		}

		$param = array('nname' => $name, 'clientname_as_prefix' => 'on', 'create_user_flag' => $withUser ? 'on' : 'off');

		if ($withUser) {
			$this->passwordsMatch($pass, $pass2);
			$param['dbpassword'] = $pass;

			if ($c->isAdmin()) {
				$param['username'] = $name;
			}
		}

		do_desc_add($c, 'mysqldb', $param);
		$c->was();

		$msg = array('ok', "Database {$prefix}{$name} created" . ($withUser ? ', with its user' : '') . '.');
	}

	function dbObject($db)
	{
		global $login;

		$dbs = $this->databases();

		if (!isset($dbs[$db])) {
			throw new lxException($login->getThrow("mysql_invalid_database"), '', $db);
		}

		return $dbs[$db];
	}

	function deleteDb($db, &$msg)
	{
		$o = $this->dbObject($db);
		do_desc_delete_single($o);
		$o->was();

		$msg = array('ok', "Database {$db} deleted.");
	}

	function checkDb($what, $db, &$msg, &$out)
	{
		$this->dbObject($db);

		$rdb = $this->dbAdmin();
		$q = '`' . str_replace('`', '``', $db) . '`';
		$tables = array();
		$res = $rdb->query("SHOW TABLES FROM {$q}");

		while ($res && ($r = $res->fetch_row())) {
			$tables[] = $q . '.`' . str_replace('`', '``', $r[0]) . '`';
		}

		if (!$tables) {
			$msg = array('ok', "{$db} has no tables.");

			return;
		}

		$lines = array();
		$bad = 0;

		foreach (array_chunk($tables, 50) as $chunk) {
			$res = $rdb->query(($what === 'repair' ? 'REPAIR' : 'CHECK') . ' TABLE ' . implode(', ', $chunk));

			while ($res && ($r = $res->fetch_assoc())) {
				$t = preg_replace('/^[^.]*\./', '', (string)$r['Table']);
				$lines[] = sprintf("%-40s %-8s %s", $t, $r['Msg_type'], $r['Msg_text']);

				if (in_array(strtolower((string)$r['Msg_type']), array('error', 'warning'), true)
					|| (strtolower((string)$r['Msg_text']) !== 'ok' && strtolower((string)$r['Msg_type']) === 'status')) {
					$bad++;
				}
			}
		}

		$out = implode("\n", $lines);
		$msg = array($bad ? 'err' : 'ok', (($what === 'repair') ? 'Repair' : 'Check') . " of {$db}: " . count($tables) . " tables"
			. ($bad ? ", {$bad} with messages (see below)" : ', everything OK') . '.');
	}

	function addUser($name, $pass, $pass2, &$msg)
	{
		$c = $this->client();
		$this->passwordsMatch($pass, $pass2);

		do_desc_add($c, 'mysqluser', array('nname' => $name, 'dbpassword' => $pass));
		$c->was();

		$msg = array('ok', "User " . $this->userPrefix() . preg_replace('/^' . preg_quote($this->userPrefix(), '/') . '/', '', strtolower($name)) . " created.");
	}

	/** a MySQL User of the account, or the own user of one of its databases */
	function userObject($user)
	{
		global $login;

		$users = $this->users();

		if (isset($users[$user])) {
			return array('mysqluser', $users[$user]);
		}

		foreach ($this->databases() as $d) {
			if (((string)$d->username !== '') && ($d->username === $user)) {
				return array('mysqldb', $d);
			}
		}

		throw new lxException($login->getThrow("mysql_invalid_user_name"), '', $user);
	}

	function setPassword($user, $pass, $pass2, &$msg)
	{
		$this->passwordsMatch($pass, $pass2);
		list($type, $o) = $this->userObject($user);

		if ($type === 'mysqluser') {
			do_desc_update($o, 'password', array('dbpassword' => $pass));
		} else {
			validate_password($pass);
			do_desc_update($o, 'update', array('dbpassword' => $pass));
		}

		$o->was();

		$msg = array('ok', "Password of {$user} changed.");
	}

	function deleteUser($user, &$msg)
	{
		global $login;

		list($type, $o) = $this->userObject($user);

		if ($type !== 'mysqluser') {
			throw new lxException('The user of a database goes with its database', '', $user);
		}

		do_desc_delete_single($o);
		$o->was();

		$msg = array('ok', "User {$user} deleted.");
	}

	function grant($user, $db, $privs, &$msg)
	{
		global $login;

		$users = $this->users();

		if (!isset($users[$user])) {
			throw new lxException($login->getThrow("mysql_invalid_user_name"), '', $user);
		}

		$this->dbObject($db);
		$u = $users[$user];
		$privs = Mysqlgrant::knPrivileges($privs);
		$key = "{$user}___{$db}";
		$given = (array)$u->getList('mysqlgrant');

		if (isset($given[$key])) {
			do_desc_update($given[$key], 'update', array('privileges' => $privs));
			$given[$key]->was();
			$msg = array('ok', "Privileges of {$user} on {$db} changed: " . str_replace(',', ', ', $privs) . '.');
		} else {
			do_desc_add($u, 'mysqlgrant', array('dbname' => $db, 'privileges' => $privs));
			$u->was();
			$msg = array('ok', "{$user} added to {$db}: " . str_replace(',', ', ', $privs) . '.');
		}
	}

	function revoke($user, $db, &$msg)
	{
		global $login;

		$users = $this->users();

		if (!isset($users[$user])) {
			throw new lxException($login->getThrow("mysql_invalid_user_name"), '', $user);
		}

		$given = (array)$users[$user]->getList('mysqlgrant');
		$key = "{$user}___{$db}";

		if (!isset($given[$key])) {
			throw new lxException($login->getThrow("mysql_invalid_database"), '', $db);
		}

		do_desc_delete_single($given[$key]);
		$given[$key]->was();

		$msg = array('ok', "{$user} removed from {$db}.");
	}

	// ------------------------------------------------------------ page

	function showRawPrint($subaction = null)
	{
		$msg = null;
		$out = null;

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->handlePost($msg, $out);
		}

		$token = self::token();
		$ptoken = (string)getCSRFToken();
		$dbs = $this->databases();
		$users = $this->users();
		$grants = $this->grants($users);
		$sizes = $this->sizes($dbs);
		$dbp = $this->dbPrefix();
		$up = $this->userPrefix();
		$privlist = Mysqlgrant::$privlist;

		// users with access, per database: own user + MySQL Users
		$access = array();

		foreach ($dbs as $name => $d) {
			if ((string)$d->username !== '') {
				$access[$name][(string)$d->username] = 'own';
			}
		}

		foreach ($grants as $u => $list) {
			foreach ($list as $db => $pr) {
				$access[$db][$u] = $pr;
			}
		}

		$hidden = function ($action) use ($token, $ptoken) {
			return '<input type="hidden" name="frm_token" value="' . self::h($ptoken) . '">'
				. '<input type="hidden" name="kn_my_token" value="' . self::h($token) . '">'
				. '<input type="hidden" name="kn_my_action" value="' . self::h($action) . '">';
		};

		$this->printStyle();
?>
<div class="kn-my">
	<p class="kn-my-intro">Databases hold the data of web applications (WordPress, shops, forums ...).
		A database can have its own user, or users created below can be given access to one or more databases.</p>

	<?php if ($msg) { ?>
	<div class="kn-my-msg <?= ($msg[0] === 'ok') ? 'ok' : 'err' ?>" role="status"><?= self::h($msg[1]) ?></div>
	<?php } ?>
	<?php if ($out !== null && $out !== '') { ?>
	<pre class="kn-my-out"><?= self::h($out) ?></pre>
	<?php } ?>

	<p class="kn-my-jump"><a href="#kn-my-users">&darr; Jump to Database Users</a></p>

	<h2 id="kn-my-create">Create New Database</h2>
	<form method="post" action="<?= self::h(self::url('kn-my-create')) ?>" class="kn-my-form" autocomplete="off">
		<?= $hidden('createdb') ?>
		<label for="kn-my-db">New Database</label>
		<div class="kn-my-prefixed"><?php if ($dbp !== '') { ?><span><?= self::h($dbp) ?></span><?php } ?><input id="kn-my-db" name="db" required maxlength="48" pattern="[A-Za-z0-9_]+" title="letters, digits and _"></div>
		<label class="kn-my-check"><input type="checkbox" name="with_user" id="kn-my-withuser" checked> Create a user with the same name</label>
		<div class="kn-my-userpass" id="kn-my-dbpass">
			<label for="kn-my-dbp1">Password of the user</label>
			<input type="password" id="kn-my-dbp1" name="pass" class="kn-my-pw" autocomplete="new-password">
			<label for="kn-my-dbp2">Password (Again)</label>
			<input type="password" id="kn-my-dbp2" name="pass2" autocomplete="new-password">
			<div class="kn-my-pwtools"><span class="kn-my-meter" aria-live="polite"></span><button type="button" class="kn-my-btn light kn-my-gen">Password Generator</button></div>
		</div>
		<button type="submit" class="kn-my-btn">Create Database</button>
	</form>

	<h2>Modify Databases</h2>
	<?php if ($dbs) { ?>
	<div class="kn-my-modify">
		<form method="post" action="<?= self::h(self::url()) ?>" class="kn-my-row">
			<?= $hidden('check') ?>
			<label for="kn-my-chk">Check Database</label>
			<select id="kn-my-chk" name="db"><?php foreach ($dbs as $n => $d) { ?><option><?= self::h($n) ?></option><?php } ?></select>
			<button type="submit" class="kn-my-btn">Check Database</button>
		</form>
		<form method="post" action="<?= self::h(self::url()) ?>" class="kn-my-row">
			<?= $hidden('repair') ?>
			<label for="kn-my-rep">Repair Database</label>
			<select id="kn-my-rep" name="db"><?php foreach ($dbs as $n => $d) { ?><option><?= self::h($n) ?></option><?php } ?></select>
			<button type="submit" class="kn-my-btn">Repair Database</button>
		</form>
	</div>
	<?php } else { ?><p class="kn-my-empty">No databases yet.</p><?php } ?>

	<h2 id="kn-my-dbs">Current Databases</h2>
	<input type="search" class="kn-my-search" data-kn-filter="kn-my-dbtable" placeholder="Search" aria-label="Search databases">
	<table class="kn-my-table" id="kn-my-dbtable">
		<thead><tr><th>Database</th><th>Size</th><th>Privileged Users</th><th>Actions</th></tr></thead>
		<tbody>
		<?php foreach ($dbs as $n => $d) { ?>
			<tr>
				<td><?= self::h($n) ?></td>
				<td><?= self::h(self::size(isset($sizes[$n]) ? $sizes[$n] : 0)) ?></td>
				<td>
				<?php foreach ((isset($access[$n]) ? $access[$n] : array()) as $u => $pr) { ?>
					<span class="kn-my-chip" title="<?= self::h(($pr === 'own') ? 'user of the database (ALL)' : str_replace(',', ', ', $pr)) ?>">
						<a href="#kn-my-user-<?= self::h($u) ?>"><?= self::h($u) ?></a>
						<?php if ($pr !== 'own') { ?>
						<form method="post" action="<?= self::h(self::url('kn-my-dbs')) ?>" class="kn-my-inline" onsubmit="return confirm('Remove <?= self::h($u) ?> from <?= self::h($n) ?>?');">
							<?= $hidden('revoke') ?><input type="hidden" name="user" value="<?= self::h($u) ?>"><input type="hidden" name="db" value="<?= self::h($n) ?>">
							<button type="submit" class="kn-my-x" aria-label="Remove <?= self::h($u) ?> from <?= self::h($n) ?>" title="Remove from the database">&times;</button>
						</form>
						<?php } ?>
					</span>
				<?php } ?>
				<?php if (empty($access[$n])) { ?><span class="kn-my-muted">none</span><?php } ?>
				</td>
				<td class="kn-my-actions">
					<?php if ((string)$d->username !== '') { ?>
					<a href="<?= self::h(self::pma($d->username, $d->dbpassword)) ?>" target="_blank" rel="noopener">phpMyAdmin</a>
					<?php } ?>
					<form method="post" action="<?= self::h(self::url('kn-my-dbs')) ?>" class="kn-my-inline" onsubmit="return confirm('Delete the database <?= self::h($n) ?> and everything in it?');">
						<?= $hidden('deletedb') ?><input type="hidden" name="db" value="<?= self::h($n) ?>">
						<button type="submit" class="kn-my-link danger">Delete</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if (!$dbs) { ?><tr><td colspan="4" class="kn-my-muted">No databases yet.</td></tr><?php } ?>
		</tbody>
	</table>

	<p class="kn-my-jump"><a href="#kn-my-create">&uarr; Jump to Databases</a></p>

	<h2 id="kn-my-users">Database Users</h2>
	<h3>Add New User</h3>
	<form method="post" action="<?= self::h(self::url('kn-my-users')) ?>" class="kn-my-form" autocomplete="off">
		<?= $hidden('adduser') ?>
		<label for="kn-my-un">Username</label>
		<div class="kn-my-prefixed"><?php if ($up !== '') { ?><span><?= self::h($up) ?></span><?php } ?><input id="kn-my-un" name="user" required maxlength="<?= Mysqluser::MAXLEN - strlen($up) ?>" pattern="[A-Za-z0-9_]+" title="letters, digits and _"></div>
		<label for="kn-my-up1">Password</label>
		<input type="password" id="kn-my-up1" name="pass" class="kn-my-pw" required autocomplete="new-password">
		<label for="kn-my-up2">Password (Again)</label>
		<input type="password" id="kn-my-up2" name="pass2" required autocomplete="new-password">
		<div class="kn-my-pwtools"><span class="kn-my-meter" aria-live="polite"></span><button type="button" class="kn-my-btn light kn-my-gen">Password Generator</button></div>
		<button type="submit" class="kn-my-btn">Create User</button>
	</form>

	<h3>Add User To Database</h3>
	<?php if ($users && $dbs) { ?>
	<form method="post" action="<?= self::h(self::url('kn-my-users')) ?>" class="kn-my-form">
		<?= $hidden('grant') ?>
		<label for="kn-my-gu">User</label>
		<select id="kn-my-gu" name="user"><?php foreach ($users as $u => $o) { ?><option><?= self::h($u) ?></option><?php } ?></select>
		<label for="kn-my-gd">Database</label>
		<select id="kn-my-gd" name="db"><?php foreach ($dbs as $n => $d) { ?><option><?= self::h($n) ?></option><?php } ?></select>
		<fieldset class="kn-my-privs">
			<legend>Privileges</legend>
			<?php foreach ($privlist as $pr) { ?>
			<label class="<?= ($pr === 'ALL') ? 'all' : '' ?>"><input type="checkbox" name="priv[]" value="<?= self::h($pr) ?>"<?= ($pr === 'ALL') ? ' checked' : '' ?>> <?= self::h(($pr === 'ALL') ? 'ALL PRIVILEGES' : $pr) ?></label>
			<?php } ?>
		</fieldset>
		<button type="submit" class="kn-my-btn">Add</button>
		<p class="kn-my-muted">A user already on the database gets the privileges chosen here.</p>
	</form>
	<?php } else { ?>
	<p class="kn-my-empty"><?= $users ? 'Create a database first.' : 'Create a user first.' ?></p>
	<?php } ?>

	<h3>Current Users</h3>
	<table class="kn-my-table">
		<thead><tr><th>Users</th><th>Databases</th><th>Actions</th></tr></thead>
		<tbody>
		<?php
		$rows = array();

		foreach ($users as $u => $o) {
			$rows[$u] = array('type' => 'user', 'pass' => $o->dbpassword, 'dbs' => isset($grants[$u]) ? $grants[$u] : array());
		}

		foreach ($dbs as $n => $d) {
			if (((string)$d->username !== '') && !isset($rows[$d->username])) {
				$rows[(string)$d->username] = array('type' => 'own', 'pass' => $d->dbpassword, 'dbs' => array($n => 'ALL'));
			}
		}

		ksort($rows);

		foreach ($rows as $u => $r) {
		?>
			<tr id="kn-my-user-<?= self::h($u) ?>">
				<td><?= self::h($u) ?><?php if ($r['type'] === 'own') { ?> <span class="kn-my-muted">(user of the database)</span><?php } ?></td>
				<td><?php
				$list = array();
				foreach ($r['dbs'] as $db => $pr) {
					$list[] = '<span class="kn-my-chip" title="' . self::h(str_replace(',', ', ', $pr)) . '">' . self::h($db) . ' <small>' . self::h(Mysqlgrant::knShort($pr)) . '</small></span>';
				}
				print($list ? implode(' ', $list) : '<span class="kn-my-muted">none</span>');
				?></td>
				<td class="kn-my-actions">
					<details class="kn-my-pwedit">
						<summary class="kn-my-link">Change Password</summary>
						<form method="post" action="<?= self::h(self::url('kn-my-users')) ?>" autocomplete="off">
							<?= $hidden('passwd') ?><input type="hidden" name="user" value="<?= self::h($u) ?>">
							<input type="password" name="pass" class="kn-my-pw" placeholder="New password" required autocomplete="new-password" aria-label="New password for <?= self::h($u) ?>">
							<input type="password" name="pass2" placeholder="Again" required autocomplete="new-password" aria-label="New password again">
							<div class="kn-my-pwtools"><span class="kn-my-meter" aria-live="polite"></span><button type="button" class="kn-my-btn light kn-my-gen">Generate</button></div>
							<button type="submit" class="kn-my-btn">Change</button>
						</form>
					</details>
					<a href="<?= self::h(self::pma($u, $r['pass'])) ?>" target="_blank" rel="noopener">phpMyAdmin</a>
					<?php if ($r['type'] === 'user') { ?>
					<form method="post" action="<?= self::h(self::url('kn-my-users')) ?>" class="kn-my-inline" onsubmit="return confirm('Delete the user <?= self::h($u) ?>?');">
						<?= $hidden('deluser') ?><input type="hidden" name="user" value="<?= self::h($u) ?>">
						<button type="submit" class="kn-my-link danger">Delete</button>
					</form>
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if (!$rows) { ?><tr><td colspan="3" class="kn-my-muted">No users yet.</td></tr><?php } ?>
		</tbody>
	</table>
</div>
<?php
		$this->printScript();
	}

	function printScript()
	{
?>
<script>
(function () {
	// a user for the new database: its password fields only then
	var wu = document.getElementById('kn-my-withuser'), box = document.getElementById('kn-my-dbpass');

	function syncUser() {
		box.style.display = wu.checked ? '' : 'none';
		box.querySelectorAll('input').forEach(function (i) { i.required = wu.checked; });
	}

	if (wu) { wu.addEventListener('change', syncUser); syncUser(); }

	// strength 0-100: length, kinds of characters
	function score(p) {
		var s = Math.min(p.length, 20) * 3, k = 0;
		[/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].forEach(function (r) { if (r.test(p)) { k++; } });
		s += k * 10;
		return Math.max(0, Math.min(100, p.length < 6 ? Math.min(s, 20) : s));
	}

	function label(n) {
		return (n < 30 ? 'Very Weak' : n < 50 ? 'Weak' : n < 70 ? 'Good' : n < 90 ? 'Strong' : 'Very Strong') + ' (' + n + '/100)';
	}

	document.querySelectorAll('.kn-my-pw').forEach(function (pw) {
		var form = pw.form, meter = form.querySelector('.kn-my-meter');

		function show() {
			var n = score(pw.value);
			if (meter) { meter.textContent = pw.value ? label(n) : ''; meter.dataset.level = n < 50 ? 'low' : n < 70 ? 'mid' : 'high'; }
		}

		pw.addEventListener('input', show);
	});

	document.querySelectorAll('.kn-my-gen').forEach(function (b) {
		b.addEventListener('click', function () {
			var c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^*-_+=', a = new Uint32Array(16), p = '';
			crypto.getRandomValues(a);
			a.forEach(function (x) { p += c[x % c.length]; });
			var pws = b.form.querySelectorAll('input[type=password]');
			pws.forEach(function (i) { i.value = p; i.type = 'text'; });
			pws[0].dispatchEvent(new Event('input'));
			pws[0].select();
		});
	});

	// ALL excludes the single privileges
	document.querySelectorAll('.kn-my-privs').forEach(function (fs) {
		var all = fs.querySelector('input[value=ALL]'), rest = fs.querySelectorAll('input:not([value=ALL])');
		all.addEventListener('change', function () { if (all.checked) { rest.forEach(function (i) { i.checked = false; }); } });
		rest.forEach(function (i) { i.addEventListener('change', function () { if (i.checked) { all.checked = false; } }); });
	});

	document.querySelectorAll('[data-kn-filter]').forEach(function (s) {
		var rows = document.querySelectorAll('#' + s.dataset.knFilter + ' tbody tr');
		s.addEventListener('input', function () {
			var q = s.value.toLowerCase();
			rows.forEach(function (r) { r.style.display = r.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none'; });
		});
	});
})();
</script>
<?php
	}

	function printStyle()
	{
?>
<style>
.kn-my { max-width: 1100px; color: var(--kn-text, #0f172a); }
.kn-my h2 { font-size: 1.45rem; font-weight: 600; margin: 28px 0 10px; }
.kn-my h3 { font-size: 1.15rem; font-weight: 600; margin: 22px 0 8px; }
.kn-my-intro { color: var(--kn-text-muted, #64748b); margin: 0 0 8px; max-width: 75ch; }
.kn-my-jump { text-align: right; margin: 6px 0; }
.kn-my-jump a, .kn-my a { color: var(--kn-primary, #2563eb); text-decoration: none; }
.kn-my a:hover { text-decoration: underline; }
.kn-my-form { display: grid; gap: 6px; max-width: 520px; }
.kn-my-form label, .kn-my-row label { font-weight: 600; font-size: .9rem; margin-top: 6px; }
.kn-my input[type=text], .kn-my input[type=password], .kn-my input:not([type]), .kn-my input[type=search], .kn-my select {
	width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--kn-border-strong, #cfd7e3);
	border-radius: var(--kn-radius-sm, 7px); background: var(--kn-surface, #fff); color: inherit; font: inherit; }
.kn-my-prefixed { display: flex; }
.kn-my-prefixed span { padding: 8px 10px; border: 1px solid var(--kn-border-strong, #cfd7e3); border-right: 0;
	border-radius: var(--kn-radius-sm, 7px) 0 0 var(--kn-radius-sm, 7px); background: var(--kn-bg, #f4f6fb); color: var(--kn-text-muted, #64748b); white-space: nowrap; }
.kn-my-prefixed span + input { border-radius: 0 var(--kn-radius-sm, 7px) var(--kn-radius-sm, 7px) 0; }
.kn-my-check { font-weight: 400 !important; display: flex; gap: 6px; align-items: center; }
.kn-my-userpass { display: grid; gap: 6px; padding: 10px; border: 1px dashed var(--kn-border, #e3e8f0); border-radius: var(--kn-radius-sm, 7px); }
.kn-my-btn { justify-self: start; padding: 8px 14px; border: 0; border-radius: var(--kn-radius-sm, 7px); background: var(--kn-primary, #2563eb);
	color: var(--kn-on-primary, #fff); font: inherit; font-weight: 600; cursor: pointer; }
.kn-my-btn:hover { background: var(--kn-primary-hover, #1d4ed8); }
.kn-my-btn.light { background: var(--kn-surface, #fff); color: inherit; border: 1px solid var(--kn-border-strong, #cfd7e3); font-weight: 500; }
.kn-my-pwtools { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.kn-my-meter { flex: 1 1 200px; font-size: .85rem; }
.kn-my-meter[data-level=low] { color: var(--kn-danger, #c62828); }
.kn-my-meter[data-level=mid] { color: var(--kn-warning, #b45309); }
.kn-my-meter[data-level=high] { color: var(--kn-success, #15803d); }
.kn-my-modify { display: grid; gap: 10px; max-width: 760px; }
.kn-my-row { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; align-items: end; }
.kn-my-row label { grid-column: 1 / -1; }
.kn-my-search { max-width: 520px; margin-bottom: 8px; }
.kn-my-table { width: 100%; border-collapse: collapse; background: var(--kn-surface, #fff); border: 1px solid var(--kn-border, #e3e8f0); border-radius: var(--kn-radius, 10px); overflow: hidden; }
.kn-my-table th { text-align: left; font-size: .85rem; padding: 10px; border-bottom: 2px solid var(--kn-border-strong, #cfd7e3); }
.kn-my-table td { padding: 10px; border-top: 1px solid var(--kn-border, #e3e8f0); vertical-align: top; }
.kn-my-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; margin: 2px 4px 2px 0; border-radius: 999px;
	background: var(--kn-primary-soft, #e8efff); font-size: .85rem; }
.kn-my-chip small { color: var(--kn-text-muted, #64748b); }
.kn-my-inline { display: inline; margin: 0; }
.kn-my-x { border: 0; background: none; color: var(--kn-danger, #c62828); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 2px; }
.kn-my-actions { white-space: nowrap; }
.kn-my-actions > * { margin-right: 12px; }
.kn-my-link { border: 0; background: none; color: var(--kn-primary, #2563eb); cursor: pointer; font: inherit; padding: 0; }
.kn-my-link.danger { color: var(--kn-danger, #c62828); }
.kn-my-pwedit { display: inline-block; vertical-align: top; }
.kn-my-pwedit[open] { display: block; margin-bottom: 8px; }
.kn-my-pwedit form { display: grid; gap: 6px; max-width: 320px; margin-top: 6px; white-space: normal; }
.kn-my-privs { border: 1px solid var(--kn-border, #e3e8f0); border-radius: var(--kn-radius-sm, 7px); padding: 8px 12px;
	display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 4px 12px; }
.kn-my-privs legend { font-weight: 600; font-size: .9rem; padding: 0 4px; }
.kn-my-privs label { font-weight: 400; display: flex; gap: 6px; align-items: center; margin: 0; font-size: .88rem; }
.kn-my-privs label.all { grid-column: 1 / -1; font-weight: 600; }
.kn-my-muted { color: var(--kn-text-muted, #64748b); font-size: .9rem; }
/* the panel styles every button: links and the remove cross stay plain */
.kn-my button.kn-my-link, .kn-my summary.kn-my-link, .kn-my button.kn-my-x {
	background: none !important; border: 0 !important; box-shadow: none !important; min-width: 0 !important; height: auto !important;
	padding: 0 2px !important; margin: 0 !important; font: inherit !important; font-weight: 500 !important; text-transform: none !important; }
.kn-my button.kn-my-link { color: var(--kn-primary, #2563eb) !important; cursor: pointer; }
.kn-my button.kn-my-link.danger, .kn-my button.kn-my-x { color: var(--kn-danger, #c62828) !important; }
.kn-my button.kn-my-x { font-size: 1.05rem !important; line-height: 1 !important; }
.kn-my button.kn-my-link:hover { text-decoration: underline; }
.kn-my summary.kn-my-link { color: var(--kn-primary, #2563eb) !important; cursor: pointer; list-style: none; display: inline; }
.kn-my summary.kn-my-link::-webkit-details-marker { display: none; }
.kn-my-empty { color: var(--kn-text-muted, #64748b); }
.kn-my-msg { padding: 10px 12px; border-radius: var(--kn-radius-sm, 7px); margin: 8px 0; }
.kn-my-msg.ok { background: var(--kn-success-soft, #e7f6ec); color: var(--kn-success, #15803d); }
.kn-my-msg.err { background: var(--kn-danger-soft, #fdecec); color: var(--kn-danger, #c62828); }
.kn-my-out { max-height: 320px; overflow: auto; padding: 10px; background: var(--kn-bg, #f4f6fb); border: 1px solid var(--kn-border, #e3e8f0); border-radius: var(--kn-radius-sm, 7px); font-size: .82rem; }
@media (max-width: 700px) {
	.kn-my-row { grid-template-columns: 1fr; }
	.kn-my-table thead { display: none; }
	.kn-my-table, .kn-my-table tbody, .kn-my-table tr, .kn-my-table td { display: block; width: 100%; box-sizing: border-box; }
	.kn-my-table tr { border-top: 1px solid var(--kn-border, #e3e8f0); }
	.kn-my-table td { border: 0; padding: 6px 10px; }
	.kn-my-actions { white-space: normal; }
}
</style>
<?php
	}
}
