<?php

chdir("..");
include_once "lib/html/displayinclude.php";

$kloxo_version = $sgbl->__ver_full;

init_language();

$cgi_forgotpwd = $ghtml->frm_forgotpwd;

if ($sgbl->is_this_slave()) {
	print("<section class=\"card\"><h1>Slave server</h1><p class=\"sub\">Manage this server from its master.</p></section>");

	exit;
}

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// message of the previous request (frm_emessage / frm_smessage), as plain text
$kn_msg = '';
$kn_msg_err = false;

if ($ghtml->frm_emessage) {
	$kn_msg_err = true;
	$k = $ghtml->frm_emessage;
	$kn_msg = isset($g_language_mes->__emessage[$k]) ? $g_language_mes->__emessage[$k] : $k;
} elseif ($ghtml->frm_smessage) {
	$k = $ghtml->frm_smessage;
	$kn_msg = isset($g_language_mes->__smessage[$k]) ? $g_language_mes->__smessage[$k] : $k;
}

$kn_msg = trim(strip_tags((string)$kn_msg));

$logfo = db_get_value("general", "admin", "login_pre");
$logfo = str_replace("<%programname%>", $sgbl->__var_program_name, (string)$logfo);

if (!$cgi_forgotpwd) {
	if (!isset($_SESSION)) {
		session_start();
	}

	// KloxoNext - unpredictable CSRF token (was mt_rand())
	$_SESSION['frm_token'] = bin2hex(random_bytes(16));

	$blocked_for = 0;

	if (isset($_SESSION['last_login_time'], $_SESSION['num_login_fail'])) {
		$elapsed = time() - (int)$_SESSION['last_login_time'];

		if ((int)$_SESSION['num_login_fail'] >= 5) {
			if ($elapsed < 600) {
				$blocked_for = 600 - $elapsed;
			} else {
				$_SESSION['num_login_fail'] = 0;
			}
		} else {
			$_SESSION['last_login_time'] = time();
		}
	}
?>
	<section class="card" aria-labelledby="login-title">
		<h1 id="login-title">Sign in</h1>
		<p class="sub">Use your panel account to continue.</p>

<?php if ($kn_msg !== '') { ?>
		<div class="alert <?= $kn_msg_err ? 'alert-error' : 'alert-ok' ?>" role="alert"><?= $h($kn_msg) ?></div>
<?php } ?>

<?php if ($blocked_for > 0) { ?>
		<div class="alert alert-error" role="alert">
			<?= $h($g_language_mes->__emessage['blocked']) ?>
			<?= $h($g_language_mes->__emessage['blocked_remaining']) ?>: <b id="kn-countdown" data-left="<?= (int)$blocked_for ?>"></b>
		</div>
		<script>
			(function () {
				var el = document.getElementById('kn-countdown'), left = +el.getAttribute('data-left');
				function tick() {
					if (left < 0) { location.href = '/login/'; return; }
					el.textContent = Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2);
					left--; setTimeout(tick, 1000);
				}
				tick();
			})();
		</script>
<?php } ?>

		<form name="loginform" action="/lib/php/" method="post"
			onsubmit="return fieldcheck(this)" autocomplete="on">
			<div class="field">
				<label for="frm_clientname">Username</label>
				<input id="frm_clientname" name="frm_clientname" type="text" autocomplete="username"
					autocapitalize="none" spellcheck="false" required autofocus>
			</div>
			<div class="field">
				<label for="frm_password">Password</label>
				<input id="frm_password" name="frm_password" type="password" autocomplete="current-password" required>
			</div>
			<input type="hidden" name="frm_token" value="<?= $h($_SESSION['frm_token']) ?>">
			<button type="submit" class="btn" name="login" value="Login"<?= ($blocked_for > 0) ? ' disabled' : '' ?>>Sign in</button>
		</form>

		<div class="row">
			<a href="/login/?frm_forgotpwd=1">Forgot password?</a>
			<span>v<?= $h($kloxo_version) ?></span>
		</div>

		<div class="note"><?= $logfo ?></div>
	</section>
<?php
	if (if_demo()) {
		print("<div>");
		include_once "lib/demologins.php";
		print("</div>");
	}
} elseif ($cgi_forgotpwd == 1) {
	$page = 'Forgot password';
?>
	<section class="card" aria-labelledby="forgot-title">
		<h1 id="forgot-title">Reset password</h1>
		<p class="sub">Enter your username and the contact e-mail of the account. A new password will be sent to that address.</p>

		<form name="sendmail" action="/login/" method="post">
			<div class="field">
				<label for="frm_clientname">Username</label>
				<input id="frm_clientname" name="frm_clientname" type="text" autocomplete="username" required autofocus>
			</div>
			<div class="field">
				<label for="frm_email">Contact e-mail</label>
				<input id="frm_email" name="frm_email" type="email" autocomplete="email" required>
			</div>
			<input type="hidden" name="frm_forgotpwd" value="2">
			<button type="submit" class="btn" name="forgot" value="Send">Send new password</button>
		</form>

		<div class="row"><a href="/login/">&larr; Back to sign in</a><span></span></div>
	</section>
<?php
} elseif ($cgi_forgotpwd == 2) {
	$progname = $sgbl->__var_program_name;
	$cprogname = ucfirst($progname);

	$cgi_clientname = trim((string)$ghtml->frm_clientname);
	$cgi_email = trim((string)$ghtml->frm_email);

	// KloxoNext - both values reach SQL below: accept only well-formed input
	// (the original code interpolated raw request data -> SQL injection).
	$name_ok = (bool)preg_match('/^[A-Za-z0-9._@-]{1,128}$/', $cgi_clientname);
	$mail_ok = (filter_var($cgi_email, FILTER_VALIDATE_EMAIL) !== false);

	$classname = getClassFromName($cgi_clientname);

	if (!in_array($classname, array('client', 'domain', 'mailaccount'), true)) {
		$classname = 'client';
	}

	if ($name_ok && $mail_ok) {
		$rawdb = new Sqlite(null, $classname);
		$email = $rawdb->rawQuery("select contactemail from {$classname} where nname = '" . kn_sql_escape($cgi_clientname) . "';");

		if ($email && hash_equals((string)$email[0]['contactemail'], $cgi_email)) {
			$rndstring = randomString(12);
			$pass = lx_password_hash($rndstring);

			$rawdb->rawQuery("update {$classname} set password = '" . kn_sql_escape($pass) . "' where nname = '" . kn_sql_escape($cgi_clientname) . "'");

			$subject = "{$cprogname} password reset";
			$message = "\n\nYour {$cprogname} password has been reset.\n";
			$message .= "Requested from IP address: {$_SERVER['REMOTE_ADDR']}\n";
			$message .= "Username: {$cgi_clientname}\n";
			$message .= "New password: {$rndstring}\n";

			lx_mail(null, $email[0]['contactemail'], $subject, $message);
		}
	}

	// same answer whether or not the account exists (no user enumeration)
	$ghtml->print_redirect("/login/?frm_smessage=password_sent");
}
