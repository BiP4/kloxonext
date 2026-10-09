<?php

// KloxoNext - Admin > Security > Firewall: open ports, read live from the firewall
// (/script/firewall ports). Select rows and Close / Open them; every action is
// applied to the firewall immediately.

class Fwport extends Lxclass
{
	static $__ttype = "transient";
	static $__desc = array("", "", "firewall_port");

	static $__desc_nname = array("", "", "firewall_port");
	static $__desc_port = array("", "", "firewall_port_number");
	static $__desc_proto = array("", "", "firewall_protocol");
	static $__desc_state = array("", "", "firewall_port_state");
	static $__desc_kind = array("", "", "firewall_port_kind");
	static $__desc_program = array("", "", "firewall_port_program");

	static $__acdesc_update_close = array("", "", "firewall_close_port");
	static $__acdesc_update_open = array("", "", "firewall_open_port");

	function get() {}
	function write() {}

	static function perPage() { return 500; }
	static function defaultSort() { return "port"; }
	static function defaultSortDir() { return "asc"; }
	static function searchVar() { return "program"; }

	static function createListNlist($parent, $view)
	{
		$nlist['port'] = '15%';
		$nlist['proto'] = '8%';
		$nlist['state'] = '12%';
		$nlist['kind'] = '25%';
		$nlist['program'] = '40%';

		return $nlist;
	}

	static function createListAlist($parent, $class)
	{
		$alist[] = "a=list&c=$class";

		return $alist;
	}

	static function createListBlist($parent, $class)
	{
		$blist[] = array("a=update&sa=close&c=$class");
		$blist[] = array("a=update&sa=open&c=$class");

		return $blist;
	}

	static function initThisListRule($parent, $class) { return null; }

	/** "80" in "80", "30000-30100" ... */
	static function inSpec($port, $spec)
	{
		if (strpos($spec, '-') === false) {
			return ((int)$port === (int)$spec);
		}

		list($a, $b) = explode('-', $spec, 2);

		return ((int)$port >= (int)$a) && ((int)$port <= (int)$b);
	}

	static function initThisList($parent, $class)
	{
		global $login;

		if (!$login->isAdmin()) {
			return null;
		}

		$rules = $listen = array();

		foreach (Firewall::run('ports') as $l) {
			$f = explode('|', $l);

			if (count($f) < 4) {
				continue;
			}

			if ($f[0] === 'listen') {
				$listen[] = array('proto' => $f[1], 'port' => $f[2], 'name' => $f[3]);
			} else {
				$rules[] = array('type' => $f[0], 'proto' => $f[1], 'port' => $f[2], 'svc' => $f[3]);
			}
		}

		$st = Firewall::status();
		$required = preg_split('/\s+/', trim(isset($st['REQUIRED_TCP']) ? $st['REQUIRED_TCP'] : ''));

		$res = array();
		$covered = array();

		foreach ($rules as $r) {
			$progs = array();

			foreach ($listen as $s) {
				if (($s['proto'] === $r['proto']) && self::inSpec($s['port'], $r['port'])) {
					$progs[$s['name']] = true;
					$covered["{$s['proto']}/{$s['port']}"] = true;
				}
			}

			if ($r['type'] === 'service') {
				$nname = "svc_{$r['svc']}";
				$kind = "firewalld service '{$r['svc']}'";
			} else {
				$nname = "{$r['proto']}_{$r['port']}";
				$kind = 'firewall rule';
			}

			if (isset($res[$nname])) {
				continue;
			}

			$isreq = ($r['proto'] === 'tcp') && in_array($r['port'], $required, true);

			$res[$nname] = array(
				'nname' => $nname, 'parent_clname' => $parent->getClName(),
				'port' => $r['port'], 'proto' => $r['proto'], 'state' => 'open',
				'kind' => $kind . ($isreq ? ' (SSH / panel - always open)' : ''),
				'program' => $progs ? implode(', ', array_keys($progs)) : '-',
				'required' => $isreq ? 'yes' : 'no',
			);
		}

		// listening services the firewall does not let in
		foreach ($listen as $s) {
			$k = "{$s['proto']}/{$s['port']}";

			if (isset($covered[$k])) {
				continue;
			}

			$nname = "{$s['proto']}_{$s['port']}";

			if (isset($res[$nname])) {
				$res[$nname]['program'] .= ", {$s['name']}";

				continue;
			}

			$res[$nname] = array(
				'nname' => $nname, 'parent_clname' => $parent->getClName(),
				'port' => $s['port'], 'proto' => $s['proto'], 'state' => 'closed',
				'kind' => 'listening, not allowed by the firewall',
				'program' => $s['name'], 'required' => 'no',
			);
		}

		return array_values($res);
	}

	function isSelect()
	{
		return !(isset($this->required) && ($this->required === 'yes'));
	}

	/** nname -> argument for /script/firewall: tcp_80 -> 80/tcp, svc_cockpit -> service:cockpit */
	function spec()
	{
		if (strpos($this->nname, 'svc_') === 0) {
			return 'service:' . substr($this->nname, 4);
		}

		list($proto, $port) = explode('_', $this->nname, 2);

		return "{$port}/{$proto}";
	}

	function change($action)
	{
		global $login;

		if (!$login->isAdmin()) {
			throw new lxException($login->getThrow("no_permission"));
		}

		$out = Firewall::run(array("{$action}-port", $this->spec()), $ok);

		if (!$ok) {
			Firewall::fail($out);
		}

		return null;
	}

	function updateClose($param)
	{
		return $this->change('close');
	}

	function updateOpen($param)
	{
		return $this->change('open');
	}
}
