<?php

// KloxoNext - install/refresh the maintained webmail applications

include_once "lib/html/include.php";

initProgram('admin');

system("sh /script/thirdparty-update --install --only=roundcube,snappymail");
system("sh /script/add-rainloop-domains");

installChooser();
