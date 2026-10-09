<?php

// KloxoNext - Admin > Security > Firewall
//
// Front end for /script/firewall, which drives ConfigServer Security & Firewall
// when it is installed, otherwise firewalld (EL) or ufw (Ubuntu). SSH and the
// panel ports are always kept open by the script.

class Firewall extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "", "firewall");

	static $__desc_nname = array("n", "", "firewall", "a=show");

	static $__desc_backend_f = array("", "", "firewall_backend");
	static $__desc_active_f = array("", "", "firewall_active");
	static $__desc_required_f = array("", "", "firewall_required_ports");
	static $__desc_csfinfo_f = array("", "", "firewall_csf");

	static $__desc_firewall_state_flag = array("f", "", "firewall_state");
	static $__desc_csf_testing_flag = array("f", "", "firewall_csf_testing");

	static $__desc_tcp_in_f = array("", "", "firewall_tcp_in");
	static $__desc_udp_in_f = array("", "", "firewall_udp_in");

	static $__desc_allowlist_f = array("t", "", "firewall_allow_list");
	static $__desc_denylist_f = array("t", "", "firewall_deny_list");

	static $__desc_csf_url_f = array("", "", "firewall_csf_archive_url");

	static $__acdesc_update_state = array("", "", "firewall_status");
	static $__acdesc_update_ports = array("", "", "firewall_ports");
	static $__acdesc_update_allow = array("", "", "firewall_allow_list");
	static $__acdesc_update_deny = array("", "", "firewall_deny_list");
	static $__acdesc_update_csfinstall = array("", "", "firewall_csf_install");

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'firewall';
	}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
	}

	function createShowUpdateform()
	{
		$uflist['state'] = null;
		$uflist['ports'] = null;
		$uflist['allow'] = null;
		$uflist['deny'] = null;

		if (!$this->isCsf()) {
			$uflist['csfinstall'] = null;
		}

		return $uflist;
	}

	// ------------------------------------------------------------ helper script

	static function run($args, &$ok = null)
	{
		$cmd = "sh /script/firewall";

		foreach ((array)$args as $a) {
			$cmd .= " " . escapeshellarg((string)$a);
		}

		$out = array();
		exec("{$cmd} 2>&1", $out, $rc);

		$ok = ($rc === 0);

		return $out;
	}

	static function status()
	{
		static $st = null;

		if ($st !== null) {
			return $st;
		}

		$st = array();

		foreach (self::run('status') as $l) {
			$p = strpos($l, '=');

			if ($p !== false) {
				$st[substr($l, 0, $p)] = trim(substr($l, $p + 1));
			}
		}

		return $st;
	}

	function isCsf()
	{
		$st = self::status();

		return (isset($st['BACKEND']) && ($st['BACKEND'] === 'csf'));
	}

	static function checkAccess()
	{
		global $login;

		if (!$login->isAdmin()) {
			throw new lxException($login->getThrow("no_permission"));
		}
	}

	function checkLocal()
	{
		global $login;

		$p = $this->getParentO();

		if ($p && isset($p->nname) && ($p->nname !== 'localhost')) {
			throw new lxException($login->getThrow("firewall_error"), '', "only the local server is managed here");
		}
	}

	static function fail($out)
	{
		global $login;

		$msg = '';

		foreach ((array)$out as $l) {
			if (strpos($l, 'ERROR=') === 0) {
				$msg = substr($l, 6);
			}
		}

		throw new lxException($login->getThrow("firewall_error"), '', $msg ? $msg : implode(' ', (array)$out));
	}

	// ------------------------------------------------------------ forms

	function updateform($subaction, $param)
	{
		self::checkAccess();

		$st = self::status();
		$backend = isset($st['BACKEND']) ? $st['BACKEND'] : 'none';

		$names = array('csf' => 'ConfigServer Security & Firewall (CSF)', 'firewalld' => 'firewalld',
			'ufw' => 'ufw (Uncomplicated Firewall)', 'ufw-missing' => 'ufw (not installed yet)',
			'firewalld-missing' => 'firewalld (not installed yet)');

		switch ($subaction) {
			case "state":
				$vlist['backend_f'] = array('M', isset($names[$backend]) ? $names[$backend] : $backend);
				$vlist['active_f'] = array('M', (isset($st['ACTIVE']) && $st['ACTIVE'] === 'yes') ? 'Active' : 'Inactive');
				$vlist['required_f'] = array('M', 'TCP ' . trim(isset($st['REQUIRED_TCP']) ? $st['REQUIRED_TCP'] : '')
					. ' (SSH and the panel - always open)');

				if ($backend === 'csf') {
					$vlist['csfinfo_f'] = array('M', trim((isset($st['CSF_VERSION']) ? $st['CSF_VERSION'] : 'csf')
						. ' - lfd: ' . (isset($st['LFD']) ? $st['LFD'] : '-')));
				}

				$this->firewall_state_flag = (isset($st['ACTIVE']) && $st['ACTIVE'] === 'yes') ? 'on' : 'off';
				$vlist['firewall_state_flag'] = null;

				if ($backend === 'csf') {
					$this->csf_testing_flag = (isset($st['CSF_TESTING']) && $st['CSF_TESTING'] === 'on') ? 'on' : 'off';
					$vlist['csf_testing_flag'] = null;
				}

				return $vlist;

			case "ports":
				$vlist['tcp_in_f'] = array('m', array('value' => str_replace(' ', ',', trim(isset($st['TCP_IN']) ? $st['TCP_IN'] : ''))));
				$vlist['udp_in_f'] = array('m', array('value' => str_replace(' ', ',', trim(isset($st['UDP_IN']) ? $st['UDP_IN'] : ''))));

				return $vlist;

			case "allow":
				$vlist['allowlist_f'] = array('t', implode("\n", self::run(array('list', 'allow'))));

				return $vlist;

			case "deny":
				$vlist['denylist_f'] = array('t', implode("\n", self::run(array('list', 'deny'))));

				return $vlist;

			case "csfinstall":
				$vlist['csf_url_f'] = array('m', array('value' => ''));

				return $vlist;
		}
	}

	function updateState($param)
	{
		self::checkAccess();
		$this->checkLocal();

		$want = (isset($param['firewall_state_flag']) && $param['firewall_state_flag'] === 'on') ? 'enable' : 'disable';
		$out = self::run($want, $ok);

		if (!$ok) {
			self::fail($out);
		}

		if ($this->isCsf() && isset($param['csf_testing_flag'])) {
			$out = self::run(array('csf-testing', ($param['csf_testing_flag'] === 'on') ? 'on' : 'off'), $ok);

			if (!$ok) {
				self::fail($out);
			}
		}

		return null;
	}

	function updatePorts($param)
	{
		self::checkAccess();
		$this->checkLocal();

		$tcp = isset($param['tcp_in_f']) ? $param['tcp_in_f'] : '';
		$udp = isset($param['udp_in_f']) ? $param['udp_in_f'] : '';

		$out = self::run(array('set-ports', $tcp, $udp), $ok);

		if (!$ok) {
			self::fail($out);
		}

		return null;
	}

	function updateAllow($param)
	{
		return $this->syncList('allow', isset($param['allowlist_f']) ? $param['allowlist_f'] : '');
	}

	function updateDeny($param)
	{
		return $this->syncList('deny', isset($param['denylist_f']) ? $param['denylist_f'] : '');
	}

	function syncList($kind, $text)
	{
		self::checkAccess();
		$this->checkLocal();

		$tmp = tempnam(sys_get_temp_dir(), 'knfw');
		file_put_contents($tmp, str_replace("\r", '', (string)$text) . "\n");

		$out = self::run(array('sync', $kind, $tmp), $ok);
		@unlink($tmp);

		if (!$ok) {
			self::fail($out);
		}

		return null;
	}

	function updateCsfinstall($param)
	{
		global $login;

		self::checkAccess();
		$this->checkLocal();

		$url = trim(isset($param['csf_url_f']) ? $param['csf_url_f'] : '');

		if (!preg_match('#^https://[A-Za-z0-9._/~%+=?&:-]+$#', $url)) {
			throw new lxException($login->getThrow("firewall_error"), '', "the archive URL must start with https://");
		}

		// a few minutes (packages + CSF installer): own unit, outside the panel processes
		$unit = "kloxo-csf-install-" . time();
		$job = "/bin/bash /script/firewall csf-install " . escapeshellarg($url)
			. " >> /usr/local/lxlabs/kloxo/log/csf-install.log 2>&1";
		exec("systemd-run --quiet --collect --unit={$unit} /bin/bash -c " . escapeshellarg($job) . " >/dev/null 2>&1", $o, $rc);

		if ($rc !== 0) {
			$out = self::run(array('csf-install', $url), $ok);

			if (!$ok) {
				self::fail($out);
			}
		}

		throw new lxException($login->getThrow("firewall_csf_install_started"));
	}
}
