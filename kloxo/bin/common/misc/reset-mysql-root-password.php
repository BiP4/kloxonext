<?php

include_once "lib/html/include.php";

$tpath = "/usr/local/lxlabs/kloxo/serverfile";

if (isset($argv[1])) {
	$pass = $argv[1];
} else {
	$pass = randomString(9);
}

$text = "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('PWORD123') OR unix_socket;";

$text = str_replace("'USER'", "'root'", $text);
$text = str_replace("'PWORD123'", "'{$pass}'", $text);
if(!is_dir($tpath)) mkdir($tpath);
file_put_contents("{$tpath}/reset-mysql-password.sql", $text);

chmod("{$tpath}/reset-mysql-password.sql", 0600);

$svc = isServiceExists('mariadb') ? 'mariadb' : (isServiceExists('mysqld') ? 'mysqld' : 'mysql');
$daemon = file_exists('/usr/sbin/mariadbd') ? '/usr/sbin/mariadbd' : '/usr/sbin/mysqld';

print("Stop MySQL/mariadb service...\n");
exec("systemctl stop {$svc}");
system("killall -q mariadbd mysqld");
print("MySQL ROOT password reset...\n");
sleep(5);

// Ubuntu removes the socket directory when the unit stops
exec("mkdir -p /run/mysqld /var/run/mysqld; chown mysql:mysql /run/mysqld");

system("{$daemon} --user=mysql --init-file={$tpath}/reset-mysql-password.sql >/dev/null 2>&1 &");

sleep(15);

print("Start MySQL service...\n");
system("killall -q mariadbd mysqld");
sleep(5);
exec("'rm' -f {$tpath}/reset-mysql-password.sql");
exec("systemctl start {$svc}");



$conn = new mysqli('localhost', 'root', $pass, 'mysql');

if ($conn->connect_errno) {
	printf("Connect failed: %s\n", $conn->connect_error);

	exit();
}

$cmd = "UPDATE kloxo.dbadmin SET dbpassword = '$pass' WHERE dbadmin_name = 'root'";



$result = $conn->query($cmd);


$conn->close();

$a['mysql']['dbpassword'] = $pass;

slave_save_db("dbadmin", $a);