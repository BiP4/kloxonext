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
// PHP 7 array_map() returned null (with a warning) for a non-array argument
function lx_array_map_safe($callback, $value)
{
	return is_array($value) ? array_map($callback, $value) : null;
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
