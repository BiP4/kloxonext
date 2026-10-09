<?php
	$page = 'Webmail';

	$apps = array();

	foreach (glob("*", GLOB_ONLYDIR) as $dir) {
		if (in_array($dir, array('img', 'images', 'disabled'), true) || !file_exists("{$dir}/index.php")) {
			continue;
		}

		$apps[] = $dir;
	}

	$labels = array('roundcube' => 'Roundcube', 'snappymail' => 'SnappyMail', 'rainloop' => 'RainLoop');
?>
	<section class="card" aria-labelledby="wm-title">
		<h1 id="wm-title">Webmail</h1>
		<p class="sub">Choose the webmail application to read your mail.</p>
<?php if (empty($apps)) { ?>
		<p>No webmail application is installed on this server.</p>
<?php } ?>
<?php foreach ($apps as $dir) { ?>
		<p><a class="btn" href="/<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>/"><?= htmlspecialchars(isset($labels[$dir]) ? $labels[$dir] : ucfirst($dir), ENT_QUOTES, 'UTF-8') ?></a></p>
<?php } ?>
	</section>
