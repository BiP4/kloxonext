<?php
	ini_set("display_errors", "0");

	if (session_id() == "") {
		session_start();
	}

	if (file_exists("./custom-index.php")) {
		include_once "./custom-index.php";
	} elseif (file_exists("./custom.index.php")) {
		include_once "./custom.index.php";
	} else {
		if (file_exists("./custom-inc.php")) {
			$incfile = "./custom-inc.php";
		} elseif (file_exists("./custom.inc.php")) {
			$incfile = "./custom.inc.php";
		} elseif (file_exists("./inc.php")) {
			$incfile = "./inc.php";
		}

		if (file_exists("./custom-inc2.php")) {
			$incfile2 = "./custom-inc2.php";
		} elseif (file_exists("./custom.inc2.php")) {
			$incfile2 = "./custom.inc2.php";
		} elseif (file_exists("./inc2.php")) {
			$incfile2 = "./inc2.php";
		}

		$logo_url = file_exists("./images/user-logo.png") ? "./images/user-logo.png" : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light dark">
	<meta name="robots" content="noindex, nofollow">
<?php
		if (isset($incfile2)) { include_once $incfile2; }

		$title = isset($page) ? "{$page} · KloxoNext" : "KloxoNext";
?>
	<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root {
	--bg: #f4f6fb; --surface: #fff; --border: #e3e8f0; --text: #0f172a; --text-2: #475569; --text-3: #64748b;
	--primary: #2563eb; --primary-hover: #1d4ed8; --on-primary: #fff;
	--danger: #c62828; --danger-soft: #fdecec; --success: #15803d; --success-soft: #e7f6ec;
	--focus: 0 0 0 3px rgba(37, 99, 235, .3);
	--font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
@media (prefers-color-scheme: dark) {
	:root {
		--bg: #0b1120; --surface: #111a2e; --border: #1f2a44; --text: #e2e8f0; --text-2: #b6c2d6; --text-3: #8a97ad;
		--primary: #60a5fa; --primary-hover: #93c5fd; --on-primary: #0b1120;
		--danger: #f87171; --danger-soft: rgba(248, 113, 113, .12); --success: #4ade80; --success-soft: rgba(74, 222, 128, .12);
		--focus: 0 0 0 3px rgba(96, 165, 250, .35);
	}
}
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; }
body {
	font-family: var(--font); font-size: 15px; line-height: 1.5; color: var(--text);
	background:
		radial-gradient(1200px 600px at 10% -10%, rgba(59, 130, 246, .16), transparent 60%),
		radial-gradient(900px 500px at 110% 110%, rgba(139, 92, 246, .14), transparent 60%),
		var(--bg);
	min-height: 100vh; display: flex; flex-direction: column;
}
a { color: var(--primary); text-decoration: none; }
a:hover { color: var(--primary-hover); text-decoration: underline; }
.page-head { display: flex; align-items: center; justify-content: space-between; padding: 20px 28px; }
.brand { display: flex; align-items: center; gap: 10px; color: var(--text); font-weight: 600; font-size: 18px; }
.brand-mark { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: #fff; font-weight: 800; }
.brand b { color: var(--primary); }
.brand img { height: 36px; width: auto; }
.page-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 16px; }
.page-foot { text-align: center; padding: 18px; color: var(--text-3); font-size: 12px; }

/* shared by the included pages (login, default, disabled, webmail, cp) */
.card { width: 100%; max-width: 420px; background: var(--surface); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 23, 42, .12); padding: 32px; }
.card-wide { max-width: 640px; }
.card h1 { font-size: 22px; margin: 0 0 6px; }
.card p.sub { margin: 0 0 24px; color: var(--text-2); }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.field label { font-size: 13px; font-weight: 600; color: var(--text-2); }
.field input { font: inherit; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface); color: var(--text); }
.field input:focus { outline: none; border-color: var(--primary); box-shadow: var(--focus); }
.btn { width: 100%; font: inherit; font-weight: 600; padding: 11px 14px; border-radius: 10px; border: 0; background: var(--primary); color: var(--on-primary); cursor: pointer; }
.btn:hover { background: var(--primary-hover); }
.btn:focus-visible { outline: none; box-shadow: var(--focus); }
.row { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; font-size: 13px; color: var(--text-3); }
.alert { padding: 10px 12px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
.alert-error { background: var(--danger-soft); color: var(--danger); }
.alert-ok { background: var(--success-soft); color: var(--success); }
.note { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); color: var(--text-3); font-size: 13px; }
.note:empty { display: none; }
@media (max-width: 480px) { .card { padding: 24px 20px; border-radius: 14px; } .page-head { padding: 16px; } }
</style>
</head>
<body>
	<header class="page-head">
		<span class="brand">
<?php if ($logo_url) { ?>
			<img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="">
<?php } else { ?>
			<span class="brand-mark" aria-hidden="true">K</span>
			<span>Kloxo<b>Next</b></span>
<?php } ?>
		</span>
	</header>

	<main class="page-main">
<?php if (isset($incfile)) { include_once $incfile; } ?>
	</main>

	<footer class="page-foot">Powered by KloxoNext</footer>
</body>
</html>
<?php
	}
?>
