<?php
	$page = 'Website unavailable';

	// the requested site; only a plain host name is shown (no port, no markup)
	$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
	$host = preg_replace('/^(www|webmail|mail|cp|stats)\./', '', $host);

	if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
		$host = '';
	}

	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?>
	<style>
	.kn-state { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #b45309; background: rgba(245, 158, 11, .14); padding: 4px 10px; border-radius: 999px; }
	.kn-state::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
	.kn-box { margin-top: 18px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; }
	.kn-box h2 { font-size: 15px; margin: 0 0 8px; }
	.kn-box ul { margin: 0; padding-left: 20px; color: var(--text-2); }
	.kn-box li { margin: 4px 0; }
	.kn-meta { margin-top: 18px; font-size: 12.5px; color: var(--text-3); }
	</style>

	<section class="card card-wide" aria-labelledby="dis-title">
		<span class="kn-state">Suspended</span>
		<h1 id="dis-title" style="margin-top:12px"><?= ($host !== '') ? $h($host) . ' is temporarily unavailable' : 'This website is temporarily unavailable' ?></h1>
		<p class="sub">The hosting account of this website has been suspended by the hosting provider. The website,
			its email and the other services of the account are paused; no data has been deleted.</p>

		<div class="kn-box">
			<h2>Visiting this website?</h2>
			<ul>
				<li>Please try again later.</li>
				<li>If you need to reach the owner, use another way of contacting them; email sent to this domain may not be delivered while the account is suspended.</li>
			</ul>
		</div>

		<div class="kn-box">
			<h2>Is this your website?</h2>
			<ul>
				<li>Contact your hosting provider to find out why the account was suspended and how to reactivate it.</li>
				<li>Common reasons: an unpaid invoice, an exceeded disk or traffic quota, or a security or terms-of-service issue.</li>
				<li>Once the account is enabled again, the website comes back as it was, immediately.</li>
			</ul>
		</div>

		<p class="kn-meta"><?= $h(gmdate('Y-m-d H:i')) ?> UTC</p>
	</section>
