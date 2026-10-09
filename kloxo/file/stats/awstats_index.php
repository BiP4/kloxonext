<?php
	// stats.<domain>/ -> AWStats report of <domain>
	$d = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));

	if (!preg_match('/^[a-z0-9.-]+$/', $d)) {
		header('HTTP/1.1 400 Bad Request');

		exit;
	}

	$c = preg_replace('/^stats\./', '', $d);

	$s = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';

	header("Location: {$s}://{$d}/awstats.pl?config={$c}");

	exit;
