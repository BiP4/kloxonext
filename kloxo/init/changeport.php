<?php

// KloxoNext - render the panel nginx configuration (runs before kloxo-web starts)

if (!file_exists("/var/run/acme/acme-challenge")) {
	exec("mkdir -p /var/run/acme/acme-challenge");
}

$kpath = "/usr/local/lxlabs/kloxo";
$hpath = "/home/kloxo/httpd";

chdir("{$kpath}/httpdocs");

include_once "{$kpath}/httpdocs/lib/html/include.php";

initProgram('admin');

$sslport = $sgbl->__var_prog_ssl_port;
$nonsslport = $sgbl->__var_prog_port;

$gen = $login->getObject('general')->portconfig_b;

if (isset($gen)) {
	if (isset($gen->sslport) && ($gen->sslport !== '')) {
		$sslport = $gen->sslport;
	}

	if (isset($gen->nonsslport) && ($gen->nonsslport !== '')) {
		$nonsslport = $gen->nonsslport;
	}
}

$panelphp = trim(file_get_contents(getLinkCustomfile("{$kpath}/init", "kloxo_php_active")));

// 'http2 on;' exists since nginx 1.25.1, older versions use 'listen ... http2'
$nginxver = '0';

if (preg_match('#nginx/([0-9.]+)#', (string)shell_exec("nginx -v 2>&1"), $m)) {
	$nginxver = $m[1];
}

if (version_compare($nginxver, '1.25.1', '>=')) {
	$http2listen = '';
	$http2directive = 'http2 on;';
} else {
	$http2listen = 'http2 ';
	$http2directive = '';
}

$content = file_get_contents(getLinkCustomfile("{$kpath}/init", "kloxo-nginx.conf.base"));

$content = str_replace(
	array("__nonssl_port__", "__ssl_port__", "__panel_php__", "__http2_listen__", "__http2_directive__"),
	array($nonsslport, $sslport, $panelphp, $http2listen, $http2directive),
	$content
);

foreach (array('body', 'fastcgi', 'proxy', 'uwsgi', 'scgi') as $t) {
	if (!is_dir("{$kpath}/init/tmp/{$t}")) {
		mkdir("{$kpath}/init/tmp/{$t}", 0700, true);
	}
}

exec("chown -R lxlabs:lxlabs {$kpath}/init/tmp");

file_put_contents("{$kpath}/init/kloxo-nginx.conf", $content);

file_put_contents("{$kpath}/init/port-nonssl", $nonsslport);
file_put_contents("{$kpath}/init/port-ssl", $sslport);

file_put_contents("{$hpath}/cp/.nonssl.port", $nonsslport);
file_put_contents("{$hpath}/cp/.ssl.port", $sslport);
