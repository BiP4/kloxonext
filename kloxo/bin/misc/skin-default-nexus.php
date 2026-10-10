<?php
// KloxoNext - nexus is the skin of every account
//
//   skin-default-nexus.php            once per server (flag etc/flag/skin-default-nexus-v2.flg; v2: the feather and simplicity skins are removed):
//                                     every account and every "skin for sub-accounts" -> nexus
//   skin-default-nexus.php --force    the same, again (overrides choices made since)
//
// New accounts copy the "skin for sub-accounts" of their parent, which is nexus after
// this; a client can still pick another skin in Appearance afterwards.

include_once "lib/html/include.php";

initProgram('admin');

$flag = "../etc/flag/skin-default-nexus-v2.flg";
$force = in_array('--force', (array)$argv, true);

if (!$force && file_exists($flag)) {
	exit(0);
}

$done = 0;

foreach (array('sp_specialplay', 'sp_childspecialplay') as $class) {
	$sq = new Sqlite(null, $class);
	$rows = $sq->getTable(array('nname'));

	foreach ((array)$rows as $r) {
		try {
			$o = new $class(null, 'localhost', $r['nname']);
			$o->get();

			if (!isset($o->specialplay_b) || !is_object($o->specialplay_b)) {
				continue;
			}

			$b = $o->specialplay_b;

			if ($b->skin_name === 'nexus' && $b->skin_color === 'default') {
				continue;
			}

			$b->skin_name = 'nexus';
			$b->skin_color = 'default';
			$b->button_type = 'font';
			$b->skin_background = '';
			$o->setUpdateSubaction();
			$o->write();
			$done++;
		} catch (Exception $e) {
			print("- {$class} {$r['nname']}: {$e->getMessage()}\n");
		}
	}
}

@touch($flag);

print("- nexus skin set on {$done} account settings\n");
