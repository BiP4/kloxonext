<?php
	$page = 'Not configured';

	// the requested name: a host name or an IP address (no port, no markup)
	$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
	$isip = (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false);

	if (!$isip && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host)) {
		$host = '';
	}

	$sslport = trim((string)@file_get_contents('/home/kloxo/httpd/cp/.ssl.port'));

	if (!preg_match('/^\d{1,5}$/', $sslport)) {
		$sslport = '7777';
	}

	$server_ip = (string)($_SERVER['SERVER_ADDR'] ?? '');

	if (filter_var($server_ip, FILTER_VALIDATE_IP) === false) {
		$server_ip = '';
	}

	// where the panel link points: the requested name, else the server IP ([v6] in a URL)
	$panel_host = ($host !== '' && !$isip) ? $host : ($server_ip !== '' ? $server_ip : trim($host, '[]'));

	if (strpos($panel_host, ':') !== false) {
		$panel_host = '[' . trim($panel_host, '[]') . ']';
	}

	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?>
	<style>
	.kn-state { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--primary); background: rgba(37, 99, 235, .1); padding: 4px 10px; border-radius: 999px; }
	.kn-state::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
	.kn-box { margin-top: 18px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; }
	.kn-box h2 { font-size: 15px; margin: 0 0 8px; }
	.kn-box ol, .kn-box ul { margin: 0; padding-left: 20px; color: var(--text-2); }
	.kn-box li { margin: 5px 0; }
	.kn-box code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; padding: 1px 6px; border: 1px solid var(--border); border-radius: 6px; }
	.kn-meta { margin-top: 18px; font-size: 12.5px; color: var(--text-3); }
	</style>

	<section class="card card-wide" aria-labelledby="def-title">
		<span class="kn-state">Web server is running</span>
		<h1 id="def-title" style="margin-top:12px">No website is configured for <?= ($host !== '') ? $h($host) : 'this address' ?></h1>
		<p class="sub">This server answers, but none of its websites matches the requested name.</p>

		<div class="kn-box">
			<h2>Just added the domain?</h2>
			<ol>
				<li>Wait a minute: the web server reloads its configuration in the background.</li>
				<li>Check the DNS: the domain (and <code>www.</code>) must point to <?= ($server_ip !== '') ? '<code>' . $h($server_ip) . '</code>, ' : '' ?>an IP address of this server
					listed in <b>Admin &rarr; IP Addresses</b>. DNS changes can take a few hours to spread.</li>
				<li>Clear the browser cache or try a private window: an old DNS answer may still be cached.</li>
			</ol>
		</div>

		<div class="kn-box">
			<h2>Opened the server by its IP address?</h2>
			<ul>
				<li>Map the IP address to a website in <b>IP Addresses &rarr; Domain config</b> of the control panel.</li>
				<li>The control panel is at <a href="https://<?= $h($panel_host) ?>:<?= $h($sslport) ?>/">port <?= $h($sslport) ?> (HTTPS)</a>.</li>
			</ul>
		</div>

		<p class="kn-meta"><?= $h(gmdate('Y-m-d H:i')) ?> UTC</p>
	</section>
