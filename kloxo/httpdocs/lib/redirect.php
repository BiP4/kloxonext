<?php
	$kloxopath = "/usr/local/lxlabs/kloxo";
	$initpath = "{$kloxopath}/init";
	$loginpath = "{$kloxopath}/httpdocs/login";

	$a = $_SERVER;

	$state = 0;

	// KloxoNext - every part of the redirect is checked: it is printed into a script
	$host = (string)($a["HTTP_HOST"] ?? '');
	$splitter = explode(":", $host);
	$domain = strtolower($splitter[0]);
	$port = (isset($splitter[1]) && ctype_digit($splitter[1])) ? $splitter[1] : '7778';
	$requesturi = (string)($a["REQUEST_URI"] ?? '/');
	$scheme = (($a["HTTP_SCHEME"] ?? '') === 'https') ? 'https' : 'http';

	if (!preg_match('/^[a-z0-9.-]{1,253}$/', $domain)) {
		$domain = '';
	}

	if ($requesturi === '' || $requesturi[0] !== '/' || strpos($requesturi, '//') === 0) {
		$requesturi = '/';
	}

	$domain_pure = preg_replace('/(cp\.|webmail\.|www\.|mail\.)(.*)/i', "$2", $domain);

	if ($domain_pure !== $domain) {
		$state += 1;
		$domain = $domain_pure;
	}

	if (file_exists("{$loginpath}/redirect-to-ssl")) {
	//	if ($a["HTTPS"] === "off") {
		if ($scheme === "http") {
			$state += 2;
			$port = trim(file_get_contents("{$initpath}/port-ssl"));
			$scheme = 'https';
		}
	}

	if (file_exists("{$loginpath}/redirect-to-domain")) {
		// MR -- this domain always without ':port'
		$domain = trim(file_get_contents("{$loginpath}/redirect-to-domain"));

		if ($domain.':'.$port !== $host) {
			$state += 4;
		}
	}

	if ($state !== 0 && $domain !== '' && preg_match('/^[a-z0-9.-]{1,253}$/', $domain) && ctype_digit((string)$port)) {
	/*
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: {$scheme}://{$domain}:{$port}{$requesturi}");
		exit();
	*/
		$target = json_encode("{$scheme}://{$domain}:{$port}{$requesturi}", JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
		$s = "<script> location.replace({$target}); </script>";
		echo $s;
	}