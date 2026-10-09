<?php
/*
 * KloxoNext 'nexus' skin - page shell (included by HtmlLib::print_real_beginning).
 * Closed by layout_end.php (do_display_exec).
 *
 * Scope: method of HtmlLib ($this), globals $login, $ghtml, $gbl, $sgbl.
 */

include_once __DIR__ . "/nav.php";

$kn_h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$kn_skin = rtrim($login->getSkinDir(), '/');
$kn_user = $login->nname;
$kn_is_mailuser = (strpos($kn_user, '@') !== false);

// --- legacy menu -> sidebar sections --------------------------------------
$kn_sections = array();

if (!$kn_is_mailuser) {
	ob_start();
	include getLinkCustomfile(getcwd() . "/theme/skin/simplicity/default", "menu.php");
	$kn_menu_html = ob_get_clean();
	$kn_sections = kn_nav_sections($kn_menu_html, $login);
	$kn_sections = kn_nav_curate($kn_sections);
}

// --- counters (same queries as the simplicity top bar) ---------------------
$kn_msg_total = db_get_count("smessage", "text_sent_to_cmlist LIKE '%client-{$kn_user}%'");
$kn_msg_read = db_get_count("smessage", "text_readby_cmlist LIKE '%client-{$kn_user}%'");
$kn_msg_unread = max(0, (int)$kn_msg_total - (int)$kn_msg_read);
$kn_ticket_open = db_get_count("ticket", "sent_to='client-{$kn_user}' AND state='open'");

// --- status message of the last action (success / error) -------------------
$kn_status = trim(strip_tags((string)$this->print_message('nexus')));
$kn_status_is_error = ($kn_status !== '') && (stripos($kn_status, 'alert') === 0);

$kn_logo = file_exists("./login/images/user-logo.png") ? "/login/images/user-logo.png" : null;
?>
<body class="kn">
<a class="kn-skip" href="#mmm">Skip to content</a>

<div class="kn-shell">
	<aside class="kn-sidebar" id="kn-sidebar" aria-label="Navigation">
		<div class="kn-brand">
			<a href="/display.php?frm_action=show" class="kn-brand-link">
<?php if ($kn_logo) { ?>
				<img src="<?= $kn_h($kn_logo) ?>" alt="" class="kn-brand-logo">
<?php } else { ?>
				<span class="kn-brand-mark" aria-hidden="true">K</span>
<?php } ?>
				<span class="kn-brand-name">Kloxo<b>Next</b></span>
			</a>
		</div>

		<?= kn_nav_render($kn_sections) ?>

		<div class="kn-sidebar-foot">
			<span class="kn-version">v<?= $kn_h($sgbl->__ver_full) ?></span>
		</div>
	</aside>

	<div class="kn-overlay" data-kn-close-sidebar></div>

	<div class="kn-body">
		<header class="kn-topbar">
			<button type="button" class="kn-iconbtn kn-burger" data-kn-toggle-sidebar aria-controls="kn-sidebar" aria-label="Menu">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
			</button>

			<div class="kn-topbar-title" id="kn-page-title"></div>

			<!-- legacy forms write "Wait..." here while submitting -->
			<div id="div_status" class="kn-busy" aria-live="polite"></div>

			<div class="kn-topbar-actions">
<?php if (!$kn_is_mailuser) { ?>
				<a class="kn-pill" href="/display.php?frm_action=list&amp;frm_o_cname=smessage" title="<?= $kn_h($login->getKeywordUc('message_title')) ?>">
					<?= kn_nav_icon('mail') ?>
					<span class="kn-pill-text"><?= $kn_h($login->getKeywordUc('message')) ?></span>
<?php if ($kn_msg_unread > 0) { ?>
					<span class="kn-badge"><?= (int)$kn_msg_unread ?></span>
<?php } ?>
				</a>
				<a class="kn-pill" href="/display.php?frm_action=list&amp;frm_o_cname=ticket" title="<?= $kn_h($login->getKeywordUc('ticket_title')) ?>">
					<?= kn_nav_icon('task') ?>
					<span class="kn-pill-text"><?= $kn_h($login->getKeywordUc('ticket')) ?></span>
<?php if ((int)$kn_ticket_open > 0) { ?>
					<span class="kn-badge"><?= (int)$kn_ticket_open ?></span>
<?php } ?>
				</a>
<?php } ?>

				<button type="button" class="kn-iconbtn" data-kn-theme-toggle aria-label="Toggle dark mode" title="Light / dark">
					<svg class="kn-theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
					<svg class="kn-theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
				</button>

				<details class="kn-user">
					<summary class="kn-user-btn" aria-label="Account">
						<span class="kn-avatar" aria-hidden="true"><?= $kn_h(mb_strtoupper(mb_substr($kn_user, 0, 1))) ?></span>
						<span class="kn-user-name"><?= $kn_h($kn_user) ?></span>
					</summary>
					<div class="kn-user-menu">
						<div class="kn-user-head">
							<strong><?= $kn_h($kn_user) ?></strong>
							<span><?= $kn_h($login->isAdmin() ? 'Administrator' : ucfirst((string)$login->cttype)) ?></span>
						</div>
						<a href="javascript:void(0)" onclick="toggleVisibilityById('infomsg');"><?= kn_nav_icon('help') ?><?= $kn_h($login->getKeywordUc('help')) ?></a>
						<a href="/lib/php/logout.php" data-kn-confirm="<?= $kn_h($login->getKeywordUc('is_want_logout')) ?>"><?= kn_nav_icon('logout') ?><?= $kn_h($login->getKeywordUc('logout')) ?></a>
					</div>
				</details>
			</div>
		</header>

<?php if ($kn_status !== '') { ?>
		<div class="kn-toast <?= $kn_status_is_error ? 'kn-toast-error' : 'kn-toast-ok' ?>" role="status" data-kn-toast>
			<span><?= $kn_h($kn_status) ?></span>
			<button type="button" class="kn-toast-close" aria-label="Close" data-kn-toast-close>&times;</button>
		</div>
<?php } ?>

		<main id="mmm" class="kn-main" tabindex="-1">
