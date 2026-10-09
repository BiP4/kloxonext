<?php
	$page = 'Control Panel';

	// cp.<domain> -> <domain>; only a plain host name is accepted (no port, no injection)
	$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
	$domain = preg_replace('/^cp\./', '', $host);

	if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
		$domain = '';
	}

	$sslport = trim((string)@file_get_contents('.ssl.port'));

	if (!preg_match('/^\d{1,5}$/', $sslport)) {
		$sslport = '7777';
	}

	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?>
	<section class="card" aria-labelledby="cp-title">
		<h1 id="cp-title">Control Panel</h1>
<?php if ($domain === '') { ?>
		<p class="sub">Open this page as cp.&lt;your domain&gt;.</p>
<?php } else { ?>
		<p class="sub">Manage <b><?= $h($domain) ?></b> or read your mail.</p>
		<p><a class="btn" href="https://<?= $h($domain) ?>:<?= $h($sslport) ?>/">KloxoNext</a></p>
		<p><a class="btn" href="https://webmail.<?= $h($domain) ?>/">Webmail</a></p>
<?php } ?>
	</section>
