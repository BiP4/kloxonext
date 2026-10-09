<?php

// KloxoNext - phpMyAdmin single signon links.
//
// The panel used to put the database user and password in the link
// (?pma_username=...&pma_password=...), which phpMyAdmin in 'signon' mode
// ignores - and which leaks the password into history and logs. The link now
// carries a short-lived token encrypted with the per-server phpMyAdmin secret
// (etc/conf/pma.secret); examples/signon.php decrypts it and signs in.

define('KN_PMA_SSO_TTL', 1800);

function kn_pma_sso_key()
{
	$s = trim((string)@file_get_contents('/usr/local/lxlabs/kloxo/etc/conf/pma.secret'));

	if (!preg_match('/^[0-9a-f]{64}$/', $s) || !function_exists('sodium_crypto_secretbox')) {
		return null;
	}

	// own key, derived from the secret phpMyAdmin also uses for its cookies
	return hash_hmac('sha256', 'kloxonext-pma-signon', hex2bin($s), true);
}

/** Signon URL for $dbadminUrl ('/thirdparty/phpMyAdmin/' or an absolute URL to it). */
function kn_pma_sso_url($dbadminUrl, $user, $pass)
{
	$base = (string)$dbadminUrl;

	if (strpos($base, 'signon.php') === false) {
		$base = rtrim($base, '/') . '/examples/signon.php';
	}

	$key = kn_pma_sso_key();

	if (!$key || ($user === null) || ($user === '')) {
		return $base;
	}

	$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
	$data = json_encode(array('u' => (string)$user, 'p' => (string)$pass, 'e' => time() + KN_PMA_SSO_TTL));
	$box = sodium_crypto_secretbox($data, $nonce, $key);

	$token = rtrim(strtr(base64_encode($nonce . $box), '+/', '-_'), '=');

	return "{$base}?kn={$token}";
}
