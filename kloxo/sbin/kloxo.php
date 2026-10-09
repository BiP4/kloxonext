<?php
include_once "lib/html/include.php";
include_once "lib/html/lxserverlib.php";

kill_and_save_pid('lxserver');
debug_for_backend();

lxserver_main();

function timed_execution()
{
	global $global_dontlogshell;

	$global_dontlogshell = true;

	// KloxoNext: apply queued restarts within seconds (new domains go live quickly)
	timed_exec(1 / 6, "checkRestart");
	timed_exec(2 * 5, "execSisinfoc");
	$global_dontlogshell = false;
}

function execSisinfoc()
{
	global $sgbl;

	dprint("execing sisinfoc\n");

	lxshell_background("{$sgbl->__path_php_path}", "../bin/sisinfoc.php");
}

