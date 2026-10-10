<?php 

include_once "lib/html/include.php"; 

//initProgram('admin');
$sq = new Sqlite(null, 'lxguardhit');

//$list = $sq->getRowsWhere("syncserver = '*'");
$list = $sq->getTable();

foreach($list as $l) {
	$old_nname = $l['nname'];
	$old_ip = $l['ipaddress'];

	if (strpos($old_nname, "\n") !== false) {

		$new_nname = str_replace("\n", "", $old_nname);
		$new_ipaddress = trim($old_ip);

		$sq->rawQuery("UPDATE lxguardhit SET nname = '" . kn_sql_escape($new_nname) . "', ipaddress = '" . kn_sql_escape($new_ipaddress) . "' WHERE nname = '" . kn_sql_escape($old_nname) . "';");
	}
}
