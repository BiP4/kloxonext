<?php 

$path = __FILE__;
$dir = dirname(dirname(dirname($path)));
include_once "$dir/lib/html/displayinclude.php";

// print($dir . "<br>");

function parse_etc_mime()
{
	$list = lfile_trim("/etc/mime.types");

	foreach($list as $s) {
		if (!$s) {
			continue;
		}

		if ($s[0] === '#') {
			continue;
		}

		$s = trimSpaces($s);
		$s = explode(" ", $s);
		$type = array_shift($s);

		foreach($s as $ss) {
			$res[$ss] = $type;
		}
	}

	return $res;
}

$mime = parse_etc_mime();

$request = $_SERVER['REQUEST_URI'];

if (!csa($request, "sitepreview/")) {
	header("HTTP/1.0 404 Not Found");

	print("404--- <br> ");

	exit;
}

$request = strfrom($request, "sitepreview/");

$domain = strtolower(strtilfirst($request, "/"));

// print("request: " . $request . "<br>");
// print("domain: " . $domain . "<br>");

// KloxoNext - a preview is a panel feature: logged in (redirects to the login page
// otherwise), a valid domain name, and a domain of this account (or the administrator)
if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
	header("HTTP/1.0 404 Not Found");
	print("404--- <br> ");

	exit;
}

initProgram();

$sq = new Sqlite(null, 'web');
$res = $sq->getRowsWhere("nname = '" . kn_sql_escape($domain) . "'");

if (!$res) {
	http_response_code(404);
	print("Domain Doesn't exist\n");

	exit;
}

if (!$login->isAdmin()) {
	// web.customer_name is the client that owns the domain
	$owner = isset($res[0]['customer_name']) ? (string)$res[0]['customer_name'] : '';

	if ($owner === '' || $owner !== (string)$login->nname) {
		header("HTTP/1.0 403 Forbidden");
		print("Not your domain\n");

		exit;
	}
}

$server = $res[0]['syncserver'];
$ip = getOneIPForServer($server);

// MR -- hiawatha need remove /
if (substr($request, -5, 5) === ".php/") {
	$requestclean = rtrim($request, "/");
} else {
	$requestclean = $request;
}

// the host is the validated domain, the rest is its path
$path = kn_preview_path(substr($requestclean, strlen($domain)));

$fetched = kn_preview_fetch($domain, $ip, $path);

// KloxoNext - the previewed site is someone else's content: it runs in a sandbox (an
// opaque origin), never as a page of the panel with the visitor's panel session
header("Content-Security-Policy: sandbox allow-forms allow-scripts allow-popups");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

if ($fetched === null) {
	http_response_code(502);
	header("Content-Type: text/html; charset=utf-8");
	print("<p>The site <b>" . htmlspecialchars($domain) . "</b> did not answer from this server ("
		. htmlspecialchars((string)$ip) . "). Check that its web server is running.</p>");

	exit;
}

http_response_code($fetched['code']);

$ctype = $fetched['type'];
$pinfo = pathinfo($path);
$ext = isset($pinfo['extension']) ? strtolower($pinfo['extension']) : '';

if ($ctype === '' && isset($mime[$ext])) {
	$ctype = $mime[$ext];
}

if (($ctype !== '') && (stripos($ctype, 'text/html') === false)) {
	header("Content-Type: {$ctype}");

	print($fetched['body']);

	exit;
}

header("Content-Type: " . ($ctype !== '' ? $ctype : "text/html; charset=utf-8"));

include "lib/urlrewrite/hn_urlrewrite.class.php";

$rewrite = new hn_urlrewrite();

$page = $rewrite->_rewrite_page($domain, $fetched['body']);

// links of a site that is served over https (or as www.) stay inside the preview too
// (full forms first: "//domain/" alone would cut "http://domain/" into "http:/sitepreview/...")
$page = str_replace(array("https://www.{$domain}/", "https://{$domain}/", "http://www.{$domain}/", "http://{$domain}/"),
	"/sitepreview/{$domain}/", $page);
// protocol-relative links ("//domain/...") in attributes and CSS url()
$page = preg_replace('#(["\'(=\s])//(?:www\.)?' . preg_quote($domain, '#') . '/#i', '$1/sitepreview/' . $domain . '/', $page);

print($page);

/**
 * KloxoNext - the path of the previewed page: absolute, '.' / '..' segments resolved
 * (never above the site root), no control characters, query string kept.
 */
function kn_preview_path($path)
{
	$path = preg_replace('/[\x00-\x20\x7f]/', '', (string)$path);
	$q = '';

	if (($p = strpos($path, '?')) !== false) {
		$q = substr($path, $p);
		$path = substr($path, 0, $p);
	}

	$out = array();

	foreach (explode('/', str_replace('\\', '/', $path)) as $seg) {
		if ($seg === '..') {
			array_pop($out);
		} elseif (($seg !== '') && ($seg !== '.')) {
			$out[] = rawurlencode(rawurldecode($seg));
		}
	}

	$clean = '/' . implode('/', $out);

	if (($clean !== '/') && (substr($path, -1) === '/')) {
		$clean .= '/';
	}

	return $clean . $q;
}

/**
 * KloxoNext - the page of a hosted domain, from this server whatever its DNS says
 * (curl connects to the server's IP for the domain and www.): follows redirects of
 * the site itself (http -> https, -> www.) but never to another host.
 */
function kn_preview_fetch($domain, $ip, $path)
{
	$hosts = array($domain, "www.{$domain}");
	$resolve = array();

	if ($ip) {
		foreach ($hosts as $h) {
			$resolve[] = "{$h}:80:{$ip}";
			$resolve[] = "{$h}:443:{$ip}";
		}
	}

	$url = "http://{$domain}{$path}";
	$verify = true;

	for ($hop = 0; $hop < 6; $hop++) {
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		// the certificate is checked; a site of this server whose certificate does not
		// verify (self-signed, not issued yet) is shown anyway: CURLOPT_RESOLVE pins the
		// connection to this server's own IP, so nothing else can answer for the name
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify); // NOSONAR - see above: own server, pinned IP
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0); // NOSONAR - see above
		curl_setopt($ch, CURLOPT_USERAGENT, 'KloxoNext site preview');

		if ($resolve) {
			curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
		}

		$body = curl_exec($ch);
		$errno = curl_errno($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		$next = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
		curl_close($ch);

		// CURLE_PEER_FAILED_VERIFICATION (60, 51 on old curl): retry without the check
		if ($verify && $resolve && in_array($errno, array(51, 60), true)) {
			$verify = false;

			continue;
		}

		if ($body === false || $code === 0) {
			return null;
		}

		if (($code >= 300) && ($code < 400) && ($next !== '')) {
			$p = parse_url($next);
			$h = isset($p['host']) ? strtolower($p['host']) : '';

			// a redirect away from the site: shown, not followed
			if (!in_array($h, $hosts, true) || !in_array(strtolower($p['scheme'] ?? ''), array('http', 'https'), true)) {
				return array('code' => 200, 'type' => 'text/html; charset=utf-8',
					'body' => '<p>The site redirects to <b>' . htmlspecialchars($next) . '</b> (another host: not shown in the preview).</p>');
			}

			$url = $next;

			continue;
		}

		return array('code' => $code, 'type' => $type, 'body' => (string)$body);
	}

	return null;
}
