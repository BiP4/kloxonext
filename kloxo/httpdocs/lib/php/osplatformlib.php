<?php

/**
 * KloxoNext - OS abstraction layer (PHP side of pscript/os.inc).
 *
 * Kloxo historically assumed RHEL (yum/rpm, httpd, /etc/sysconfig...).
 * Every package/service operation done by the panel goes through this class
 * so the same code runs on AlmaLinux/Rocky 9-10 (dnf) and Ubuntu 26.04 (apt).
 *
 * Package names used across Kloxo are RPM names; mapPackage() translates them
 * for Debian based systems.
 */
final class OsPlatform
{
	private static $info = null;

	/** RPM name => Debian/Ubuntu name ('' = not needed / part of another package) */
	private static $debianNames = array(
		'httpd'               => 'apache2',
		'httpd-tools'         => 'apache2-utils',
		'httpd-filesystem'    => '',
		'mod_ssl'             => '',
		'mod_fcgid'           => 'libapache2-mod-fcgid',
		'mod_security'        => 'libapache2-mod-security2',
		'mod_evasive'         => 'libapache2-mod-evasive',
		'mod_ruid2'           => '',
		'mod_suphp'           => '',
		'mod_fastcgi'         => '',
		'mod_session'         => '',
		'mariadb'             => 'mariadb-client',
		'MariaDB'             => 'mariadb-server',
		'MariaDB-server'      => 'mariadb-server',
		'MariaDB-client'      => 'mariadb-client',
		'MariaDB-common'      => 'mariadb-common',
		'MariaDB-shared'      => '',
		'mariadb-libs'        => '',
		'bind'                => 'bind9',
		'bind-utils'          => 'bind9-utils',
		'cronie'              => 'cron',
		'cronie-anacron'      => 'anacron',
		'crontabs'            => '',
		'procps-ng'           => 'procps',
		'which'               => 'debianutils',
		'net-snmp'            => 'snmpd',
		'tmpwatch'            => 'tmpreaper',
		'xz-libs'             => '',
		'p7zip'               => 'p7zip-full',
		'p7zip-plugins'       => '',
		'yum-utils'           => '',
		'dnf-utils'           => '',
		'rpmdevtools'         => '',
		'yum-protectbase'     => '',
		'glibc-static'        => 'libc6-dev',
		'openssl-devel'       => 'libssl-dev',
		'curl-devel'          => 'libcurl4-openssl-dev',
		'libcurl-devel'       => 'libcurl4-openssl-dev',
		'gcc-c++'             => 'g++',
		'compat-openssl11'    => '',
		'clamd'               => 'clamav-daemon',
		'clamav-update'       => 'clamav-freshclam',
		'spamassassin'        => 'spamassassin',
		'GeoIP'               => '',
		'fcgiwrap'            => 'fcgiwrap',
		'lighttpd-fastcgi'    => '',
		'initscripts'         => '',
		'chkconfig'           => '',
		'vim-minimal'         => 'vim-tiny',
		'ImageMagick'         => 'imagemagick',
		'nginx-module*'       => '',
		'mod-pagespeed-stable' => '',
		'pdns'                => 'pdns-server',
		'pdns-backend-mysql'  => 'pdns-backend-mysql',
		'nsd'                 => 'nsd',
		'yadifa-tools'        => '',
		'lxjailshell'         => '',
	);

	/** Logical/RHEL unit name => Ubuntu unit name */
	private static $debianServices = array(
		'httpd'   => 'apache2',
		'crond'   => 'cron',
		'mysqld'  => 'mariadb',
		'mysql'   => 'mariadb',
		'clamd'   => 'clamav-daemon',
		'freshclam' => 'clamav-freshclam',
		'spamd'   => 'spamd',
		'spamassassin' => 'spamd',
		'named'   => 'named',
		'snmpd'   => 'snmpd',
	);

	public static function info()
	{
		if (self::$info !== null) {
			return self::$info;
		}

		$i = array('id' => '', 'version' => '', 'major' => '', 'like' => '', 'family' => 'unknown');

		if (is_readable('/etc/os-release')) {
			$ini = @parse_ini_file('/etc/os-release');

			if (is_array($ini)) {
				$i['id'] = isset($ini['ID']) ? strtolower($ini['ID']) : '';
				$i['version'] = isset($ini['VERSION_ID']) ? $ini['VERSION_ID'] : '';
				$i['like'] = isset($ini['ID_LIKE']) ? strtolower($ini['ID_LIKE']) : '';
				$i['major'] = explode('.', $i['version'])[0];
			}
		}

		$all = "{$i['id']} {$i['like']}";

		if (preg_match('/rhel|fedora|centos|almalinux|rocky/', $all)) {
			$i['family'] = 'el';
		} elseif (preg_match('/debian|ubuntu/', $all)) {
			$i['family'] = 'debian';
		}

		self::$info = $i;

		return $i;
	}

	public static function family() { return self::info()['family']; }
	public static function isEl()     { return self::family() === 'el'; }
	public static function isDebian() { return self::family() === 'debian'; }

	public static function prettyName()
	{
		$i = self::info();

		return trim(ucfirst($i['id']) . ' ' . $i['version']);
	}

	/** Translate one RPM package name (wildcards kept) for the running OS. */
	public static function mapPackage($name)
	{
		if (!self::isDebian()) {
			return $name;
		}

		if (array_key_exists($name, self::$debianNames)) {
			return self::$debianNames[$name];
		}

		// Remi SCL style 'php84-php-gd' / IUS style 'php84u-gd' => 'php8.4-gd'
		if (preg_match('/^php(\d)(\d)(?:u|w|-php)?-(?:pecl-)?(.+)$/', $name, $m)) {
			$ext = ($m[3] === 'mysqlnd') ? 'mysql' : $m[3];

			return "php{$m[1]}.{$m[2]}-{$ext}";
		}

		return $name;
	}

	/** @return string[] */
	public static function mapPackages($names)
	{
		if (!is_array($names)) {
			$names = preg_split('/\s+/', trim((string)$names));
		}

		$out = array();

		foreach ($names as $n) {
			if ($n === '' || $n[0] === '-') {
				// drop yum-only options like --disablerepo=...
				continue;
			}

			$m = self::mapPackage($n);

			if ($m !== '') {
				$out[] = $m;
			}
		}

		return array_values(array_unique($out));
	}

	public static function serviceName($name)
	{
		if (self::isDebian() && isset(self::$debianServices[$name])) {
			return self::$debianServices[$name];
		}

		return $name;
	}

	public static function systemdDirs()
	{
		return array('/etc/systemd/system', '/usr/lib/systemd/system', '/lib/systemd/system');
	}

	private static function run($cmd, &$out = null)
	{
		$out = array();
		exec($cmd . ' 2>&1', $out, $ret);

		return $ret;
	}

	private static function aptEnv()
	{
		return 'DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a ';
	}

	/** @return int exit code (0 = success) */
	public static function install($names)
	{
		$list = self::mapPackages($names);

		if (empty($list)) {
			return 0;
		}

		$args = implode(' ', array_map('escapeshellarg', $list));

		if (self::isDebian()) {
			// apt refuses the whole transaction on one unknown name; filter first
			$ok = array();

			foreach ($list as $p) {
				if (self::run('apt-cache show ' . escapeshellarg($p)) === 0) {
					$ok[] = escapeshellarg($p);
				}
			}

			if (empty($ok)) {
				return 0;
			}

			// RHEL semantics: never start/enable a service just because it was installed
			// (Kloxo installs every alternative driver: pdns, nsd, lighttpd, ...)
			self::run('systemctl list-unit-files --type=service --state=enabled --no-legend', $before);
			$before = array_map(function ($l) { return strtok(trim($l), ' '); }, $before);

			$ownPolicy = !file_exists('/usr/sbin/policy-rc.d');

			if ($ownPolicy) {
				file_put_contents('/usr/sbin/policy-rc.d', "#!/bin/sh\nexit 101\n");
				chmod('/usr/sbin/policy-rc.d', 0755);
			}

			$ret = self::run(self::aptEnv() . 'apt-get -y -q -o Dpkg::Options::=--force-confold install ' . implode(' ', $ok));

			if ($ownPolicy) {
				@unlink('/usr/sbin/policy-rc.d');
			}

			self::run('systemctl list-unit-files --type=service --state=enabled --no-legend', $after);

			foreach ($after as $l) {
				$u = strtok(trim($l), ' ');

				if ($u && !in_array($u, $before, true)) {
					self::run('systemctl disable ' . escapeshellarg($u));
				}
			}

			return $ret;
		}

		return self::run("dnf -y install --setopt=strict=0 --skip-broken {$args}");
	}

	public static function remove($names, $nodeps = false)
	{
		$list = self::mapPackages($names);

		if (empty($list)) {
			return 0;
		}

		$args = implode(' ', array_map('escapeshellarg', $list));

		if (self::isDebian()) {
			return self::run(self::aptEnv() . "apt-get -y -q remove {$args}");
		}

		if ($nodeps) {
			return self::run("rpm -e --nodeps {$args}");
		}

		return self::run("dnf -y remove {$args}");
	}

	public static function upgrade($names = '')
	{
		$list = self::mapPackages($names);
		$args = implode(' ', array_map('escapeshellarg', $list));

		if (self::isDebian()) {
			self::run(self::aptEnv() . 'apt-get -q update');

			if ($args === '') {
				return self::run(self::aptEnv() . 'apt-get -y -q -o Dpkg::Options::=--force-confold upgrade');
			}

			return self::run(self::aptEnv() . "apt-get -y -q -o Dpkg::Options::=--force-confold install --only-upgrade {$args}");
		}

		return self::run("dnf -y upgrade {$args}");
	}

	/** Swap one package for another (yum 'replace'/'swap'). */
	public static function replace($from, $to)
	{
		if (self::isDebian()) {
			$ret = self::install($to);

			return ($ret === 0) ? self::remove($from) : $ret;
		}

		$f = escapeshellarg($from);
		$t = escapeshellarg($to);

		return self::run("dnf -y swap {$f} {$t}");
	}

	/** Exact or glob package name. */
	public static function isInstalled($name)
	{
		$name = self::mapPackage($name);

		if ($name === '') {
			return true;
		}

		if (self::isDebian()) {
			self::run("dpkg-query -W -f='\${Package} \${Status}\\n' " . escapeshellarg($name), $out);

			foreach ($out as $l) {
				if (strpos($l, 'install ok installed') !== false) {
					return true;
				}
			}

			return false;
		}

		self::run('rpm -qa ' . escapeshellarg($name), $out);

		return lx_count(array_filter($out)) > 0;
	}

	/** Installed version, '0.0.0' when absent (same contract as getRpmVersion()). */
	public static function installedVersion($name)
	{
		$name = self::mapPackage($name);

		if (self::isDebian()) {
			self::run("dpkg-query -W -f='\${Status}|\${Version}\\n' " . escapeshellarg($name), $out);

			foreach ($out as $l) {
				if (strpos($l, 'install ok installed|') === 0) {
					$v = substr($l, strlen('install ok installed|'));
					// strip epoch and debian revision: 1:10.11.6-0ubuntu1 => 10.11.6
					$v = preg_replace('/^\d+:/', '', $v);

					return preg_replace('/[-+~].*$/', '', $v);
				}
			}

			return '0.0.0';
		}

		self::run("rpm -qa --qf '%{VERSION}\\n' " . escapeshellarg($name), $out);
		$out = array_values(array_filter($out));

		return (lx_count($out) > 0) ? $out[0] : '0.0.0';
	}

	/** Candidate version from the repositories ('' when not available). */
	public static function availableVersion($name, $field = 'version')
	{
		$name = self::mapPackage($name);

		if (self::isDebian()) {
			self::run('apt-cache policy ' . escapeshellarg($name), $out);

			foreach ($out as $l) {
				if (preg_match('/Candidate:\s+(\S+)/', $l, $m) && $m[1] !== '(none)') {
					$v = preg_replace('/^\d+:/', '', $m[1]);
					$parts = explode('-', $v, 2);

					return ($field === 'release') ? (isset($parts[1]) ? $parts[1] : '') : $parts[0];
				}
			}

			return '';
		}

		$qf = ($field === 'release') ? '%{release}' : '%{version}';
		$ret = self::run("dnf -q repoquery --latest-limit 1 --qf '{$qf}\\n' " . escapeshellarg($name), $out);
		$out = array_values(array_filter($out));

		return (($ret === 0) && lx_count($out) > 0) ? $out[0] : '';
	}

	public static function serviceExists($name)
	{
		$name = self::serviceName($name);

		foreach (self::systemdDirs() as $d) {
			if (file_exists("{$d}/{$name}.service")) {
				return true;
			}
		}

		return file_exists("/etc/rc.d/init.d/{$name}") || file_exists("/etc/init.d/{$name}");
	}
}
