<?php

include_once "lib/html/displayinclude.php";

include_once "lib/redirect.php";

main_main();

// KloxoNext - nexus (a skin without frames) is the only skin: the panel always opens
// display.php; the old frameset (top / left / main frames) is gone
function main_main()
{
	global $gbl, $login, $ghtml;

	initProgram();

	if ($login->getSpecialObject('sp_specialplay')->skin_name === 'default') {
		set_login_skin_to_nexus();
	}

	header('Location: /display.php?frm_action=show');
}
