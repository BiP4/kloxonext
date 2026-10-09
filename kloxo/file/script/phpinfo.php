<?php
main();

function main()
{
	// KloxoNext: the panel talks to the domain's PHP-FPM pool directly and sets this
	// FastCGI param; HTTP clients cannot (their headers arrive as HTTP_*).
	if (!empty($_SERVER['KLOXO_PHPINFO']) && !isset($_SERVER['HTTP_KLOXO_PHPINFO'])) {
		phpinfo();

		exit;
	}

	// legacy flow (remote servers): session created by the panel for the visitor's IP
	$v = isset($_REQUEST['session']) ? (string)$_REQUEST['session'] : '';
	$v = @unserialize(base64_decode($v), array('allowed_classes' => false));
	$s = (is_array($v) && isset($v['session'])) ? (string)$v['session'] : '';

	$r = false;

	if (preg_match('/^[A-Za-z0-9]{8,128}$/', $s)) {
		$f = "/home/kloxo/httpd/script/sess_{$s}";
		$r = is_file($f) ? @unserialize(file_get_contents($f), array('allowed_classes' => false)) : false;
	}

	if (!is_array($r) || !isset($r['ip_address']) || ($r['ip_address'] !== $_SERVER['REMOTE_ADDR'])) {
		header('HTTP/1.1 403 Forbidden');

		print("No Session. You can access this only through kloxo and needs proper authentication. " .
			"If you are indeed accessing from Inside Kloxo, then please logout and login again, " .
			"so that a new session is created properly.\n");

		exit;
	}

	phpinfo();
}
