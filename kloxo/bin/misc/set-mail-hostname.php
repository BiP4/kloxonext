<?php

// KloxoNext - give the mail server its identity (servermail 'myname') when it is
// not set yet: Postfix myhostname/HELO, used by remote servers for anti-spam checks.

include_once "lib/html/include.php";

initProgram('admin');

$server = $login->getFromList('pserver', 'localhost');
$sm = $server->getObject('servermail');

if (!empty($sm->myname)) {
	print("- Mail server name already set to '{$sm->myname}'\n");
	exit;
}

$name = trim((string)shell_exec("hostname -f 2>/dev/null"));

if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) {
	print("- Hostname '{$name}' is not a FQDN; set the mail server name in the panel\n");
	exit;
}

$sm->myname = $name;
$sm->setUpdateSubaction('update');
$sm->was();

print("- Mail server name set to '{$name}'\n");
