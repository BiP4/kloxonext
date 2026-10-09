<?php

// KloxoNext - regenerate Postfix maps and Dovecot users from the Kloxo database
// (sh /script/setup-mail runs it; safe to run any time)

include_once "lib/html/include.php";

initProgram('admin');

KnMail::rebuild();

print("- Mail maps rebuilt\n");
