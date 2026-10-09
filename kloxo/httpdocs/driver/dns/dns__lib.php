<?php

class dns__ extends lxDriverClass
{
	function __construct()
	{
	}

	static function unInstallMeTrue($drivertype = null)
	{

		setAllInactivateDnsServer();
	}

	static function installMeTrue($drivertype = null)
	{
		if ($drivertype === 'none') { return; }

		$list = getDnsDriverList($drivertype);

		foreach ($list as $k => $v) {
			if ($v === 'none') { continue; }

			if ($v === 'bind') {
				$a = 'named';
			} elseif ($v === 'yadifa') {
				$a = 'yadifad';
			} else {
				$a = $v;
			}

			exec("chkconfig {$a} on >/dev/null 2>&1");
		}
	
		createRestartFile("restart-dns");
	}

	static function getActiveDriver()
	{
		return slave_get_driver('dns');
	}

	function createConfFile()
	{
		global $sgbl;

		// KloxoNext - a subdomain has no zone of its own: its records live in the parent zone
		$parent = $this->getSubdomainParent();

		if ($parent) {
			foreach (kn_dns_render_drivers() as $v) {
				@unlink("/opt/configs/{$v}/conf/master/{$this->main->nname}");
			}

			return;
		}

		$input = array();

		$domains[] = $this->main->nname;

		foreach ((array)$this->main->__var_addonlist as $d) {
			$domains[] = $d->nname;
		}

		$input['ttl'] = $this->main->ttl;
		$input['nameduser'] = $sgbl->__var_programuser_dns;
		$input['soanameserver'] = $this->main->soanameserver;
	//	$input['email'] = $this->main->__var_email;
		$input['email'] = ($this->main->hostmaster) ? $this->main->hostmaster : "admin@{$this->main->nname}";
		$input['serial'] = $this->main->__var_ddate;
		$input['dns_records'] = $this->main->dns_record_a;

		// KloxoNext - records of the subdomains (blog.example.com -> 'blog', 'www.blog', ...)
		$subrecords = rl_exec_get('localhost', 'localhost', 'kn_dns_get_subdomain_records', array($this->main->nname));
		$input['dns_records'] = kn_dns_merge_records($input['dns_records'], $subrecords);

		// MR -- not work and not implementing yet!
	//	$input['account'] = $this->parent->getRealClientParentO()->getPathFromName();

		$input['rootpass'] = slave_get_db_pass();

		$dnsdrvlist = kn_dns_render_drivers();

		foreach ($dnsdrvlist as $v) {
			if ($v === 'none') { continue; }

			if (($v === 'bind') || ($v === 'yadifa')) { continue; }

			$tplsource = getLinkCustomfile("/opt/configs/{$v}/tpl", "domains.conf.tpl");
			$tpl = file_get_contents($tplsource);

			foreach ($domains as $d) {
				$input['domainname'] = $d;

				$tpltarget = "/opt/configs/{$v}/conf/master/{$d}";

				if (($v === 'pdns') || (($v === 'mydns'))) {
					getParseInlinePhp($tpl, $input);
				} else {
					$tplparse = getParseInlinePhp($tpl, $input);

					file_put_contents($tpltarget, $tplparse);
				}
			}
		}
	}

	/** KloxoNext - parent domain when this zone belongs to a subdomain (object first: on add the row is not saved yet). */
	function getSubdomainParent()
	{
		if (!empty($this->main->__var_subdomain_parent)) {
			return $this->main->__var_subdomain_parent;
		}

		return rl_exec_get('localhost', 'localhost', 'kn_dns_get_subdomain_parent', array($this->main->nname));
	}

	function syncCreateConf()
	{
		$input = array();

		// MR -- need to make sure for dnsnotify
		$input['driver'] = self::getActiveDriver();

		$ip_dns = $this->getIps();
	//	$ip_hostname = array(gethostbyname(php_uname('n')));
		// MR -- IP list without hostname IP
	//	$input['ips'] = array_diff($ip_dns, $ip_hostname);
		$input['ips'] = $ip_dns;

		$input['serverips'] = $this->getServerIps();

		$input['rootpass'] = slave_get_db_pass();

		$dnsdrvlist = getAllDnsDriverList();

		$mlist = $this->getMasterList();
		$slist = $this->getSlaveList();
		$rlist = $this->getReverseList();

		foreach ($dnsdrvlist as $v) {
			if ($v === 'none') { continue; }

			$input['domains'] = $mlist;
			$tplsource = getLinkCustomfile("/opt/configs/{$v}/tpl", "list.master.conf.tpl");
			$tpl = file_get_contents($tplsource);
			getParseInlinePhp($tpl, $input);

			$input['domains'] = $slist;
			$tplsource = getLinkCustomfile("/opt/configs/{$v}/tpl", "list.slave.conf.tpl");
			$tpl = file_get_contents($tplsource);
			getParseInlinePhp($tpl, $input);

			$input['arpas'] = $rlist;
			$tplsource = getLinkCustomfile("/opt/configs/{$v}/tpl", "list.reverse.conf.tpl");
			$tpl = file_get_contents($tplsource);
			getParseInlinePhp($tpl, $input);
		}
	}

	function createAllowTransferIps()
	{
		$input = array();
	/*
		$ip_dns = $this->getIps();
		$ip_hostname = array(gethostbyname(php_uname('n')));
		// MR -- IP list without hostname IP
		$input['ips'] = array_diff($ip_dns, $ip_hostname);
	*/
		// MR -- need to make sure for dnsnotify
		$input['driver'] = self::getActiveDriver();

		$input['ips'] = $this->getIps();
		$input['serverips'] = $this->getServerIps();

		$input['rootpass'] = slave_get_db_pass();

		$dnsdrvlist = getAllDnsDriverList();

		foreach ($dnsdrvlist as $v) {
			if ($v === 'none') { continue; }

			$tplsource = getLinkCustomfile("/opt/configs/{$v}/tpl", "list.transfered.conf.tpl");

			$tpl = file_get_contents($tplsource);

			getParseInlinePhp($tpl, $input);
		}
	}

	function getIps()
	{
		$nobase = true;

		$ret = rl_exec_get('localhost', 'localhost', 'getIpfromARecord', array($this->main->syncserver, $nobase));

		return $ret;
	}

	function getServerIps()
	{
		return os_get_allips();
	}

	function getMasterList()
	{
		$ret = rl_exec_get('localhost', 'localhost', 'getDnsMasters', array($this->main->syncserver));

		return $ret;
	}

	function getSlaveList()
	{
		$ret = rl_exec_get('localhost', 'localhost', 'getDnsSlaves', array($this->main->syncserver));

		return $ret;
	}

	function getReverseList()
	{
		$ret = rl_exec_get('localhost', 'localhost', 'getDnsReverses', array($this->main->syncserver));

		return $ret;
	}

	function dbactionAdd()
	{
		$this->main->write();

		$this->createConfFile();
		$this->createAllowTransferIps();
		$this->syncCreateConf();
	}

	function dbactionDelete()
	{
		// KloxoNext - before the row goes: a deleted subdomain must leave its parent zone
		$parent = $this->getSubdomainParent();

		$this->main->write();

		if ($parent) {
			// the master side scheduled the refresh, unless the delete came straight here
			kn_dns_schedule_parent_refresh($parent);
		}

		$this->createAllowTransferIps();
		$this->syncCreateConf();
	}

	function dbactionUpdate($subaction)
	{
		switch ($subaction) {
			case "allowed_transfer":
				$this->createAllowTransferIps();

				break;
			case "synchronize":
				$this->syncCreateConf();

				break;
			case "synchronize_fix":
				$this->syncCreateConf();

				break;
			case "domain":
				$this->createConfFile();

				break;
			case "full_update":
			default:
				$this->createConfFile();
				$this->createAllowTransferIps();
				$this->syncCreateConf();

				break;
		}
	}

	function dosyncToSystemPost()
	{
		$driver = self::getActiveDriver();

		if (($driver !== 'pdns') && ($driver !== 'mydns')) {
			createRestartFile("restart-dns");
		}
	}
}