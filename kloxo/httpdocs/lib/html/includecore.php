<?php 

// KloxoNext - PHP 7 semantics of count(): null => 0, scalar => 1 (PHP 8 throws TypeError).
// Kloxo objects keep lists as null until they are loaded, so the core relies on it.
function lx_count($value, $mode = COUNT_NORMAL)
{
	if (is_array($value) || $value instanceof Countable) {
		return count($value, $mode);
	}

	return ($value === null) ? 0 : 1;
}

// KloxoNext - PHP 8.1+ makes mysqli throw on every SQL error; Kloxo checks return
// values (and relies on failing queries while it migrates its schema).
// KloxoNext - SHA-512 crypt ($6$) for every stored password. glibc crypt() verifies it, so
// Pure-FTPd, htpasswd (Apache/nginx/lighttpd), Dovecot and Kloxo logins all accept it.
// Replaces MD5-crypt ($1$) and the salt-less crypt() call removed in PHP 8.
function lx_password_hash($password)
{
	$salt = substr(strtr(base64_encode(random_bytes(12)), '+', '.'), 0, 16);

	return crypt((string)$password, '$6$rounds=5000$' . $salt . '$');
}

// PHP 7 array_map() returned null (with a warning) for a non-array argument.
// The callback (trim ...) is applied to the scalar values only, nested lists are
// walked (lxguard access.info is ip => list of hits; trim(array) is a TypeError on PHP 8)
function lx_array_map_safe($callback, $value)
{
	if (!is_array($value)) {
		return null;
	}

	foreach ($value as $k => $v) {
		if (is_array($v)) {
			$value[$k] = lx_array_map_safe($callback, $v);
		} elseif (is_scalar($v)) {
			$value[$k] = call_user_func($callback, (string)$v);
		}
	}

	return $value;
}

// KloxoNext - PHP_SELF carries any path after the script name (/display.php/"><x>) and is
// printed in forms and links: keep it to the script itself
if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] !== '') {
	$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
}

if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}

function print_time($var, $mess = null, $dbg = 2) 
{
	static $last;

	$now = microtime(true);
	if (!isset($last[$var])) {
		$last[$var] = $now;
		return;
	}
	$diff = round($now - $last[$var], 7);
	$now = round($now, 7);
	$last[$var] = $now;
	if (!$mess) {
		return;
	}
	$diff = round($diff, 2);

	if ($dbg <= -1) {
	} else {
		dprint("$mess: $diff <br> \n", $dbg);
	}

	return "$mess: $diff seconds";
}

print_time('full');

/*
function windowsOs() 
{
	if (getOs() == "Windows") {
		return true;
	}
	return false;
}
*/

function getOs()
{
	return "Linux";
}

if(!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['SCRIPT_NAME'])) {
		$n = $_SERVER['SCRIPT_NAME'];
		$f = preg_replace('\\\\', '/',$_SERVER['SCRIPT_FILENAME']);
		$f = str_replace('//','/',$f);
		$_SERVER['DOCUMENT_ROOT'] = preg_replace("/".$n."/i", "", $f);
	}
}

if (!$_SERVER['DOCUMENT_ROOT']) {
	$_SERVER['DOCUMENT_ROOT'] = $dir;
}

ini_set("include_path", "{$_SERVER['DOCUMENT_ROOT']}");

function getreal($vpath)
{
     return  $_SERVER["DOCUMENT_ROOT"] . "/". $vpath; 
}

function readvirtual($vpath)
{
     readfile($_SERVER["DOCUMENT_ROOT"] . $vpath);
}
