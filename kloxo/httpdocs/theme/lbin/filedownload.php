<?php 

chdir("../../");
include_once "lib/html/displayinclude.php";

// KloxoNext - request data: only the Remote envelope of the file server, no other object
$info = unserialize(base64_decode((string)$ghtml->frm_info), array('allowed_classes' => array('Remote')));

if (!($info instanceof Remote) || !isset($info->filepass) || !is_array($info->filepass)) {
	print("No info");
	exit;
}

$filepass = $info->filepass;


/*
$ip = $_SERVER['REMOTE_ADDR'];

if ($res['ip'] !== $ip) {
	print("You are trying to access this file from a different Ip, " .
		"than the one you accessed the master with, which is prohibited <br> " .
		"Possibly an attempt to hack. \n");

	exit;
}
*/

$size = $filepass['size'];

while (@ob_end_clean());                                 
header("Content-Disposition: attachment; filename={$filepass['realname']}");
header('Content-Type: application/octet-stream');
header("Content-Length: $size");
printFromFileServ('localhost', $filepass);




