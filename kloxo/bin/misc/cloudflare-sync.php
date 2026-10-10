<?php

// KloxoNext - push the panel DNS records of a domain to its Cloudflare zone
// usage: lxphp.exe ../bin/misc/cloudflare-sync.php <domain>|--all

include_once "lib/html/include.php";

initProgram('admin');

$arg = isset($argv[1]) ? $argv[1] : '';
$list = array();

if ($arg === '--all') {
	foreach ((array)glob(KN_CF_DIR . '/domains/*.json') as $f) {
		$list[] = basename($f, '.json');
	}
} elseif (kn_cf_valid_name($arg)) {
	$list[] = $arg;
} else {
	print("usage: cloudflare-sync.php <domain>|--all\n");
	exit(2);
}

// several DNS rebuilds in a row: let the last one settle
usleep(500000);

foreach ($list as $d) {
	if (!kn_cf_uses_cloudflare($d)) {
		continue;
	}

	print(date('Y-m-d H:i:s') . " {$d}: " . kn_cf_sync_domain($d) . "\n");
}
