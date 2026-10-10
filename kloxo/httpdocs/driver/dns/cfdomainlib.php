<?php

// KloxoNext - DNS service of a domain: local BIND or Cloudflare (records synchronised
// through the API, proxy per host name), see lib/html/cloudflarelib.php

class Cfdomain extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "", "cloudflare_dns");

	static $__desc_nname = array("n", "", "cloudflare_dns", "a=show");

	static $__desc_cf_use_flag = array("f", "", "cloudflare_use");
	static $__desc_cf_proxied_f = array("t", "", "cloudflare_proxied");
	static $__desc_cf_account_f = array("", "", "cloudflare_account");
	static $__desc_cf_zone_f = array("", "", "cloudflare_zone");
	static $__desc_cf_ns_f = array("", "", "cloudflare_nameservers");
	static $__desc_cf_sync_f = array("", "", "cloudflare_last_sync");

	static $__acdesc_update_service = array("", "", "cloudflare_dns_service");
	static $__acdesc_update_sync = array("", "", "cloudflare_sync");

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'cfdomain';
	}

	function createShowPropertyList(&$alist)
	{
		// no relative 'o=dns' link here: it would open a DNS object *under* this page and
		// saving it stored a zone named 'cfdomain' (BIND then refused to start)
		$alist['property'][] = 'a=show';
	}

	function createShowUpdateform()
	{
		$uflist['service'] = null;

		if (kn_cf_uses_cloudflare($this->domainName())) {
			$uflist['sync'] = null;
		}

		return $uflist;
	}

	function domainName()
	{
		return $this->getParentO()->nname;
	}

	/** the client whose Cloudflare account is used: the owner of the domain */
	function clientName()
	{
		$p = $this->getParentO()->getParentO();

		return $p ? $p->nname : '';
	}

	function updateform($subaction, $param)
	{
		$domain = $this->domainName();
		$cfg = kn_cf_domain($domain);
		$client = $this->clientName();
		$acc = kn_cf_read('accounts', $client);

		switch ($subaction) {
			case "service":
				$vlist['cf_account_f'] = array('M', empty($acc['token'])
					? "Not connected - open the Cloudflare page of {$client} and add an API token first"
					: "Cloudflare account of {$client} (connected)");

				$this->cf_use_flag = ($cfg['provider'] === 'cloudflare') ? 'on' : 'off';
				$vlist['cf_use_flag'] = null;
				$vlist['cf_proxied_f'] = array('t', implode("\n", (array)$cfg['proxied']));

				return $vlist;

			case "sync":
				$vlist['cf_zone_f'] = array('M', $cfg['zone_id']
					? "{$cfg['zone_id']}" . (!empty($cfg['zone_status']) ? " ({$cfg['zone_status']})" : '') : '-');
				$vlist['cf_ns_f'] = array('M', $cfg['nameservers']
					? implode("\n", $cfg['nameservers']) . "\n(set these at the domain registrar)" : '-');
				$vlist['cf_sync_f'] = array('M', ($cfg['synced'] ? "{$cfg['synced']}: " : '') . ($cfg['status'] ? $cfg['status'] : '-'));

				return $vlist;
		}
	}

	function updateService($param)
	{
		global $login;

		$domain = $this->domainName();
		$client = $this->clientName();
		$cfg = kn_cf_domain($domain);

		// proxied names: '@', 'www', 'shop', '*.app' ...
		$names = array();

		foreach (preg_split('/[\s,]+/', isset($param['cf_proxied_f']) ? (string)$param['cf_proxied_f'] : '', -1, PREG_SPLIT_NO_EMPTY) as $n) {
			$n = strtolower(trim($n));
			$n = preg_replace('/\.' . preg_quote($domain, '/') . '\.?$/', '', $n);

			if (($n === $domain) || ($n === '__base__')) {
				$n = '@';
			}

			if (($n === '@') || preg_match('/^(\*\.)?[a-z0-9_]([a-z0-9_.-]*[a-z0-9_])?$/', $n)) {
				$names[$n] = true;
			}
		}

		$cfg['proxied'] = array_keys($names);
		$use = isset($param['cf_use_flag']) && ($param['cf_use_flag'] === 'on');

		if ($use) {
			$acc = kn_cf_read('accounts', $client);

			if (empty($acc['token'])) {
				throw new lxException($login->getThrow("cloudflare_error"), '',
					"connect the Cloudflare account of {$client} first (Cloudflare page of the client)");
			}

			$cfg['provider'] = 'cloudflare';
			$cfg['client'] = $client;
			kn_cf_write('domains', $domain, $cfg);

			// first sync right away: it creates the zone and returns the nameservers
			$r = kn_cf_sync_domain($domain);

			if (strpos($r, 'ERROR') === 0) {
				throw new lxException($login->getThrow("cloudflare_error"), '', substr($r, 7));
			}
		} else {
			// the Cloudflare zone is left untouched; point the registrar back to the server NS
			$cfg['provider'] = 'local';
			kn_cf_write('domains', $domain, $cfg);
		}

		return null;
	}

	function updateSync($param)
	{
		global $login;

		$r = kn_cf_sync_domain($this->domainName());

		if (strpos($r, 'ERROR') === 0) {
			throw new lxException($login->getThrow("cloudflare_error"), '', substr($r, 7));
		}

		return null;
	}
}
