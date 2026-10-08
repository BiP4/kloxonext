<?php

class Ipaddress__Redhat extends LxDriverclass
{
	function IpaddressEdit($action)
	{
		global $gbl, $sgbl, $login;

		if ($this->main->devname === 'NAT') { return; }
		$this->checkForEthBase();

		if ($sgbl->dbg > 1 && $this->main->devname === 'eth0') {
			return 1;
		}

		$ipaddr = $this->main->ipaddr;
		$netmask = $this->main->netmask;
		$temp_ipaddr = explode(".", $ipaddr);
		$temp_netmask = explode(".", $netmask);

		$i = 0;

		foreach ($temp_ipaddr as $row) {
			$ipaddr_binary[$i] = str_pad(base_convert($row, 10, 2), 8, '0', STR_PAD_LEFT);
			$i++;
		}

		$i = 0;

		foreach ($temp_netmask as $row) {
			$netmask_binary[$i] = str_pad(base_convert($row, 10, 2), 8, '0', STR_PAD_LEFT);
			$networkip[$i] = ($netmask_binary[$i] & $ipaddr_binary[$i]);
			$converted[$i] = base_convert($networkip[$i], 2, 10);
			$i++;
		}

		$networkaddress = implode(".", $converted);
		$dev = explode("-", $this->main->devname);

		if (lx_count($dev) >= 2) {
			$actualname = implode(":", $dev);
		} else {
			$actualname = $this->main->devname;
		}

		$ipaddrfile = "$sgbl->__path_real_etc_root/sysconfig/network-scripts/ifcfg-" . $actualname;

		$fdata = null;

		$fdata .= "DEVICE=" . $actualname . "\n";

		$status = "yes";

		$fdata .= "ONBOOT=$status \n";

		if (isset($this->main->bproto)) {
			$fdata .= "BOOTPROTO=" . $this->main->bproto . "\n";
		} else {
			$fdata .= "BOOTPROTO=" . "static" . "\n";
		}

		$fdata .= "IPADDR=" . $this->main->ipaddr . "\n";
		$fdata .= "NETMASK=" . $this->main->netmask . "\n";
		$fdata .= "NETWORK=" . $networkaddress . "\n";
		$fdata .= "GATEWAY=" . $this->main->gateway . "\n";

		if (isset($this->main->userctl)) {
			$fdata .= "USERCTL=" . $this->main->userctl . "\n";
		}

		if (isset($this->main->peerdns)) {
			$fdata .= "PEERDNS=" . $this->main->peerdns . "\n";
		}

		if (isset($this->main->itype)) {
			$fdata .= "TYPE=" . $this->main->itype . "\n";
		}

		if (isset($this->main->ipv6init)) {
			$fdata .= "IPV6INIT=" . $this->main->ipv6init . "\n";
		}

		lfile_put_contents($ipaddrfile, "$fdata");

		ipaddress::copyCertificate($this->main->devname, $this->main->getParentName());

		lxshell_return("ifdown", $actualname);
		lxshell_return("ifup", $actualname);
	}

	function checkForEthBase()
	{
		global $login;

		if (ipaddress::checkIfBaseAddress($this->main->devname)) {
			throw new lxException($login->getThrow("modifying_eth_not_permitted"), '', $this->main->devname);

			return;
		}
	}

	function dbactionAdd()
	{
		$this->IpaddressEdit('add');

	//	createRestartFile($this->main->__var_dnsdriver);
		createRestartFile("restart-dns");
	

		// MR -- not needed because Kloxo-MR use *:port instead existing ip for webconfig
	//	exec("sh /script/fixweb --target=defaults");
	}

	function dbactionUpdate($subaction)
	{
		global $login;

		throw new lxException($login->getThrow("modifying_not_permitted"), '', $subaction);
	}

	function dbactionDelete()
	{
		global $gbl, $sgbl, $login, $ghtml;

		$this->checkForEthBase();
		$dev = explode("-", $this->main->devname);

		if (lx_count($dev) >= 2) {
			$actualname = implode(":", $dev);
		} else {
			$actualname = $this->main->devname;
		}

		$ipaddrfile = "$sgbl->__path_real_etc_root/sysconfig/network-scripts/ifcfg-" . $actualname;
		lxshell_return("ifdown", $actualname);
		lxfile_rm($ipaddrfile);

	//	createRestartFile($this->main->__var_dnsdriver);
		createRestartFile("restart-dns");
	}

	static function getCurrentIps()
	{
		// KloxoNext - devices come from the running kernel ('ip -j addr'), so it
		// works with ifcfg files, NetworkManager keyfiles and Ubuntu netplan alike.
		$result = array();

		foreach (self::ip_json('addr show scope global') as $if) {
			if (!isset($if['ifname']) || $if['ifname'] === 'lo') {
				continue;
			}

			foreach ((array)$if['addr_info'] as $ai) {
				if (($ai['family'] ?? '') !== 'inet') {
					continue;
				}

				// secondary addresses keep their label (eth0:1) -> 'eth0-1'
				$dev = !empty($ai['label']) ? $ai['label'] : $if['ifname'];
				$res = self::get_network_data($dev);
				$res['devname'] = str_replace(':', '-', $res['devname']);

				$result[] = $res;
			}
		}

		return $result;
	}

	/** @return array decoded output of 'ip -j <args>' */
	static function ip_json($args)
	{
		$out = shell_exec("ip -j {$args} 2>/dev/null");
		$data = json_decode((string)$out, true);

		return is_array($data) ? $data : array();
	}

	static function listSystemIps($machinename)
	{
		global $gbl, $sgbl, $login, $ghtml;
		$result = self::getCurrentIps();

	//	web__apache::createWebmailConfig($result);
	//	web__apache::createWebDefaultConfig($result);

		// MR -- not needed because Kloxo-MR use *:port instead existing ip for webconfig
	//	exec("sh /script/fixweb --target=defaults");

		$res = ipaddress::fixstatus($result);

		foreach ($res as $r) {
			if ($sgbl->isKloxo()) {
				ipaddress::copyCertificate($r['devname'], $machinename);
			}
		}

		return $res;
	}

	static function get_network_data($devname)
	{
		// MR -- use directly to get_ifconfig_parse because unstandard ifcfg make
		// trouble to reading

		$list = self::get_ifconfig_parse($devname);

		foreach ($list as $key => $value) {
			switch ($key) {
				case "DEVICE":
					$result['devname'] = $value;
					break;

				case "IPADDR":
					$result['ipaddr'] = $value;
					break;

				case "NETMASK":
					$result['netmask'] = $value;
					break;

				case "ONBOOT":
					$result['status'] = $value;
					break;

				case "GATEWAY":
					$result['gateway'] = $value;
					break;

				case "USERCTL":
					$result['userctl'] = $value;
					break;

				case "PEERDNS":
					$result['peerdns'] = $value;
					break;

				case "TYPE":
					$result['itype'] = $value;
					break;

				case "IPV6INIT":
					$result['ipv6init'] = $value;
					break;

				case "BOOTPROTO":
					$result['bproto'] = $value;
					break;
			}
		}

		if (!isset($result['devname'])) {
			$result['devname'] = $devname;
		}

		if (!isset($result['status'])) {
			$result['status'] = "yes";
		}

		if (!isset($result['gateway']))
			$result['gateway'] = null;

		if (!isset($result['userctl'])) {
			$result['userctl'] = null;
		}

		if (!isset($result['netmask'])) {
			$result['netmask'] = null;
		}

		if (!isset($result['peerdns'])) {
			$result['peerdns'] = null;
		}

		if (!isset($result['itype'])) {
			$result['itype'] = null;
		}

		if (!isset($result['ipv6init'])) {
			$result['ipv6init'] = null;
		}

		if (!isset($result['bproto'])) {
			$result['bproto'] = null;
		}

		return ($result);
	}

	static function get_ifconfig_parse($devname)
	{
		// KloxoNext - iproute2 JSON instead of parsing ifconfig output
		$t = explode(":", str_replace('-', ':', $devname));
		$pdevname = $t[0];
		$label = implode(':', $t);

		$list = array('DEVICE' => $devname, 'TYPE' => null, 'NETMASK' => null, 'IPADDR' => null,
			'IPPREFIX' => null, 'IP6ADDR' => null, 'IP6PREFIX' => null, 'GATEWAY' => null,
			'MACADDRESS' => null, 'BROADCAST' => null, 'BOOTPROTO' => null);

		foreach (self::ip_json('addr show dev ' . escapeshellarg($pdevname)) as $if) {
			$list['MACADDRESS'] = $if['address'] ?? null;
			$list['TYPE'] = $if['link_type'] ?? null;

			foreach ((array)$if['addr_info'] as $ai) {
				if (($ai['scope'] ?? '') !== 'global') {
					continue;
				}

				if ($ai['family'] === 'inet' && $list['IPADDR'] === null && (($ai['label'] ?? $pdevname) === $label)) {
					$list['IPADDR'] = $ai['local'];
					$list['IPPREFIX'] = $ai['prefixlen'];
					$list['NETMASK'] = long2ip(-1 << (32 - (int)$ai['prefixlen']));
					$list['BROADCAST'] = $ai['broadcast'] ?? $ai['local'];
					$list['BOOTPROTO'] = !empty($ai['dynamic']) ? 'dhcp' : 'static';
				} elseif ($ai['family'] === 'inet6' && $list['IP6ADDR'] === null) {
					$list['IP6ADDR'] = $ai['local'];
					$list['IP6PREFIX'] = $ai['prefixlen'];
				}
			}
		}

		foreach (self::ip_json('route show default dev ' . escapeshellarg($pdevname)) as $r) {
			if (!empty($r['gateway'])) {
				$list['GATEWAY'] = $r['gateway'];
				break;
			}
		}

		// point-to-point (OpenVZ venet): no gateway, use the address itself
		if ($list['GATEWAY'] === null && $list['IPPREFIX'] == 32) {
			$list['GATEWAY'] = $list['IPADDR'];
		}

		$ifcfg = self::get_ifcfgfile_parse($pdevname);

		if (!empty($ifcfg['BOOTPROTO'])) {
			$list['BOOTPROTO'] = $ifcfg['BOOTPROTO'];
		}

		return $list;
	}

	static function get_ifcfgfile_parse($devname)
	{
		// MR -- must with @ if not want notice message for '#' deprecated in php 5.3+
		$ret = @parse_ini_file("/etc/sysconfig/network-scripts/ifcfg-{$devname}");

		return $ret; 
	}
}
