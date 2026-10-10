<?php

chdir("..");
include_once "lib/html/displayinclude.php";

main_main();

// KloxoNext - the panel has no frames any more (nexus is the only skin)
function main_main()
{
	global $gbl, $login, $ghtml;

	initProgram();

	header('Location: /display.php?frm_action=show');
}
