<?php
/**
 * KloxoNext - single signon for phpMyAdmin
 *
 *   POST user/password (sent by the panel)  -> store them in 'SignonSession', open phpMyAdmin
 *   GET  ?logout=1 (phpMyAdmin LogoutURL)   -> destroy 'SignonSession', show "logged out"
 *   GET                                     -> manual login form
 */

declare(strict_types=1);

$https = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off'));

ini_set('session.use_cookies', 'true');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_name('SignonSession');
@session_start();

function kn_page(string $title, string $body): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="../favicon.ico" type="image/x-icon">
<title>{$t} · phpMyAdmin</title>
<style>
:root{--bg:#f4f6fb;--card:#fff;--fg:#1d2433;--mut:#5b6476;--line:#dfe3ec;--acc:#4f46e5;--err:#b42318}
@media (prefers-color-scheme:dark){:root{--bg:#0f1320;--card:#171c2b;--fg:#e6e9f2;--mut:#9aa3b8;--line:#2a3147;--acc:#8b85ff;--err:#f97066}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.card{width:min(380px,100%);background:var(--card);border:1px solid var(--line);border-radius:12px;padding:28px}
h1{font-size:20px;margin:0 0 8px}p{color:var(--mut);margin:0 0 18px}
label{display:block;font-size:13px;font-weight:600;margin:12px 0 4px}
input{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;background:transparent;color:inherit;font:inherit}
button,.btn{display:inline-block;margin-top:18px;padding:9px 16px;border:0;border-radius:8px;background:var(--acc);color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.err{color:var(--err);margin-bottom:12px}
</style>
</head>
<body><main class="card">{$body}</main></body>
</html>
HTML;
}

/* logout: drop the credentials kept for phpMyAdmin */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    @session_destroy();

    $p = session_get_cookie_params();
    setcookie('SignonSession', '', ['expires' => time() - 3600, 'path' => $p['path'] ?: '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);

    kn_page('Logged out', '<h1>Logged out</h1><p>You have been logged out of phpMyAdmin. '
        . 'Open it again from the control panel, or sign in with a database user.</p>'
        . '<a class="btn" href="signon.php">Sign in</a>');
    exit;
}

/* login: credentials posted by the panel (or the form below) */
if (isset($_POST['user'])) {
    session_regenerate_id(true);

    $_SESSION['PMA_single_signon_user'] = (string)$_POST['user'];
    $_SESSION['PMA_single_signon_password'] = (string)($_POST['password'] ?? '');
    $_SESSION['PMA_single_signon_cfgupdate'] = ['verbose' => 'KloxoNext'];
    $_SESSION['PMA_single_signon_HMAC_secret'] = bin2hex(random_bytes(16));

    @session_write_close();

    header('Location: ../index.php');
    exit;
}

$err = '';

if (isset($_SESSION['PMA_single_signon_error_message'])) {
    $err = '<div class="err">' . htmlspecialchars((string)$_SESSION['PMA_single_signon_error_message'], ENT_QUOTES, 'UTF-8') . '</div>';
}

kn_page('Sign in', '<h1>phpMyAdmin</h1><p>Sign in with a database user.</p>' . $err
    . '<form action="signon.php" method="post">'
    . '<label for="u">Username</label><input id="u" type="text" name="user" autocomplete="username" spellcheck="false" required>'
    . '<label for="p">Password</label><input id="p" type="password" name="password" autocomplete="current-password">'
    . '<button type="submit">Sign in</button></form>');
