<?php

class Service__Linux extends Lxlclass
{
	static function getServiceList()
	{
		global $gbl, $sgbl, $login, $ghtml;
		
	//	$val = lscandir_without_dot("{$sgbl->__path_real_etc_root}/init.d");
	/*
		$val = array_remove($val, $sgbl->__var_programname_web);
		$val = array_remove($val, $sgbl->__var_programname_dns);
		$val = array_remove($val, $sgbl->__var_programname_imap);
		$val = array_remove($val, $sgbl->__var_programname_mmail);
	*/
		// MR not use option '--type=sysv' because trouble in CentOS 5
		exec("chkconfig --list 2>/dev/null|awk '{print $1}'|grep -v ':'", $val1);

		$val2 = array();

	//	exec("command -v systemctl", $test);

	//	if (lx_count($test) > 0) {
		if (getServiceType() === 'systemd') {
			exec("systemctl list-unit-files --type=service|awk '{print $1}'|sed 's/\.service//g'", $val2);
		}

		$val = lx_array_merge($val1, $val2);

		$nval = self::getMainServiceList();
		$nval = lx_array_merge(array($nval, $val));

		array_unique($nval);
		
		return $nval;
	}

	/**
	 * KloxoNext - services shown on Server > Services: name => description.
	 * Only the ones installed on the server are listed (see pserver::getandWriteService).
	 */
	static function getServiceDescriptions()
	{
		$d = array(
			'kloxo-web'       => 'KloxoNext panel web server (nginx)',
			'kloxo-php'       => 'KloxoNext panel PHP-FPM',
			'kloxo-apps'      => 'KloxoNext PHP-FPM for phpMyAdmin / webmail',
			'kloxo-wrap'      => 'KloxoNext backend (applies queued changes)',
			'kloxo-fcgiwrap'  => 'CGI gateway (AWStats, cgi-bin)',
			'httpd'           => 'Apache web server',
			'nginx'           => 'Nginx web server',
			'lighttpd'        => 'Lighttpd web server',
			'hiawatha'        => 'Hiawatha web server',
			'varnish'         => 'Varnish web cache',
			'squid'           => 'Squid web cache',
			'trafficserver'   => 'Apache Traffic Server web cache',
			'php-fpm'         => 'PHP-FPM (default PHP branch of the websites)',
		);

		// one PHP-FPM per installed PHP branch: php84m-fpm, php85m-fpm ...
		foreach ((array)glob('/opt/php[0-9]*m', GLOB_ONLYDIR) as $dir) {
			$b = basename($dir);
			$v = substr($b, 3, 1) . '.' . substr($b, 4, -1);
			$d["{$b}-fpm"] = "PHP {$v} FPM (websites using PHP {$v})";
		}

		$d += array(
			'mariadb'         => 'MariaDB database server',
			'named'           => 'BIND DNS server',
			'postfix'         => 'Postfix mail server (SMTP)',
			'dovecot'         => 'Dovecot IMAP / POP3 server',
			'opendkim'        => 'OpenDKIM mail signing',
			'spamassassin'    => 'SpamAssassin spam filter',
			'pure-ftpd'       => 'Pure-FTPd FTP server',
			'csf'             => 'ConfigServer Security & Firewall',
			'lfd'             => 'CSF login failure daemon',
			'firewalld'       => 'firewalld firewall',
			'ufw'             => 'ufw firewall',
			'fail2ban'        => 'Fail2Ban intrusion prevention',
			'crond'           => 'Cron scheduler',
		);

		return $d;
	}

	static function getMainServiceList()
	{
		// name => name (the old 'grep string' column is not used any more)
		$nval = array();

		foreach (array_keys(self::getServiceDescriptions()) as $n) {
			$nval[$n] = $n;
		}

		return $nval;
	}

	static function checkService($name)
	{
		global $gbl, $sgbl, $login, $ghtml;
	/*
		if ($name === 'qmail') {
			$ret = lxshell_return("qmailctl", "stat");
		} else {
			$ret = lxshell_return("service". $name, "status");
		}
	*/
		exec("pgrep ^{$name}", $out);

	//	$state = ($ret) ? "off" : "on";
		$state = (lx_count($out) > 0) ? "off" : "on";

		return $state;
	}

	static function getRunLevel()
	{
		$v = trim(lxshell_output("runlevel"));
		$v = explode(" ", $v);
		
		return $v[1];
	}
}
