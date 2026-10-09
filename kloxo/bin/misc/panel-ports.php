<?php

// KloxoNext - print the configured panel ports: "<ssl> <nonssl>" (used by /script/panel-port-apply)

include_once "lib/html/include.php";

initProgram('admin');

$p = $login->getObject('general')->portconfig_b;

$ssl = (isset($p->sslport) && ($p->sslport !== '')) ? (int)$p->sslport : (int)$sgbl->__var_prog_ssl_port;
$nonssl = (isset($p->nonsslport) && ($p->nonsslport !== '')) ? (int)$p->nonsslport : (int)$sgbl->__var_prog_port;

print("{$ssl} {$nonssl}\n");
