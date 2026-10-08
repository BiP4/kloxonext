<?php

// KloxoNext - make php-fpm (via mod_proxy_fcgi) the PHP type of the web server on a fresh install,
// so the per-domain 'PHP Selected' option (phpXYm) is effective from day one.
// Usage: lxphp.exe ../bin/misc/set-default-phptype.php [--type=php-fpm_event] [--force]

include_once "lib/html/include.php";

initProgram('admin');

$opt = parse_opt($argv);
$type = isset($opt['type']) ? $opt['type'] : 'proxy_fcgi_event';   // mod_proxy_fcgi: part of httpd 2.4

$server = $login->getFromList('pserver', 'localhost');
$sw = $server->getObject('serverweb');

if (!empty($sw->php_type) && !isset($opt['force'])) {
	print("- PHP type already set to '{$sw->php_type}'\n");
	exit;
}

$sw->php_type = $type;
$sw->setUpdateSubaction('php_type');
$sw->was();

print("- PHP type set to '{$type}'\n");
