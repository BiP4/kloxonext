<?php

// KloxoNext - Admin > Resource > Reverse DNS (custom zones)
//
// Hand-written BIND zones (typically reverse zones with $GENERATE) that the panel
// keeps permanently: stored in etc/conf/dns-custom, validated with named-checkzone,
// re-rendered on every DNS rebuild (see /script/dns-custom).

class Dnscustom extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "", "dnscustom");

	static $__desc_nname = array("n", "", "dnscustom", "a=show");

	static $__desc_rzone_select_f = array("", "", "dnscustom_select");
	static $__desc_rzone_name_f = array("", "", "dnscustom_zone");
	static $__desc_rzone_content_f = array("t", "", "dnscustom_content");
	static $__desc_rzone_delete_flag = array("f", "", "dnscustom_delete");

	static $__acdesc_update_zones = array("", "", "dnscustom_zones");
	static $__acdesc_update_edit = array("", "", "dnscustom_edit");

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'dnscustom';
	}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
	}

	function createShowUpdateform()
	{
		$uflist['zones'] = null;
		$uflist['edit'] = null;

		return $uflist;
	}

	static function run($args, &$ok = null)
	{
		$cmd = "sh /script/dns-custom";

		foreach ((array)$args as $a) {
			$cmd .= " " . escapeshellarg((string)$a);
		}

		$out = array();
		exec("{$cmd} 2>&1", $out, $rc);
		$ok = ($rc === 0);

		return $out;
	}

	static function zones()
	{
		$ret = array();

		foreach (self::run('list') as $z) {
			if (self::validZone($z)) {
				$ret[] = $z;
			}
		}

		return $ret;
	}

	static function validZone($z)
	{
		return (bool)preg_match('/^([0-9a-f]+(-[0-9]+)?\.)+(in-addr|ip6)\.arpa$/i', (string)$z);
	}

	/** Zone being edited, from ?frm_rzone= */
	static function current()
	{
		$z = isset($_REQUEST['frm_rzone']) ? strtolower(trim((string)$_REQUEST['frm_rzone'])) : '';

		return self::validZone($z) ? $z : '';
	}

	static function checkAccess()
	{
		global $login;

		if (!$login->isAdmin()) {
			throw new lxException($login->getThrow("no_permission"));
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

		throw new lxException($login->getThrow("dnscustom_error"), '', $msg ? $msg : implode(' ', (array)$out));
	}

	function redirectTo($zone)
	{
		global $gbl, $ghtml;

		$gbl->__this_redirect = $ghtml->getFullUrl("a=show") . ($zone ? "&frm_rzone=" . urlencode($zone) : "");
	}

	function updateform($subaction, $param)
	{
		self::checkAccess();

		switch ($subaction) {
			case "zones":
				$vlist['rzone_select_f'] = array('s', array_merge(array('-- new zone --'), self::zones()));

				return $vlist;

			case "edit":
				$z = self::current();
				$content = '';

				if ($z && in_array($z, self::zones(), true)) {
					$content = (string)@file_get_contents("../etc/conf/dns-custom/{$z}.zone");
				} else {
					$z = '';
				}

				$vlist['rzone_name_f'] = array('m', array('value' => $z));
				$vlist['rzone_content_f'] = array('t', $content);

				// always declared: on submit the panel only keeps fields of this form
				$this->rzone_delete_flag = 'off';
				$vlist['rzone_delete_flag'] = null;

				return $vlist;
		}
	}

	function updateZones($param)
	{
		self::checkAccess();

		$z = isset($param['rzone_select_f']) ? $param['rzone_select_f'] : '';
		$this->redirectTo(self::validZone($z) ? $z : '');

		return null;
	}

	function updateEdit($param)
	{
		global $login;

		self::checkAccess();

		$z = strtolower(trim(isset($param['rzone_name_f']) ? $param['rzone_name_f'] : ''));

		if (!self::validZone($z)) {
			throw new lxException($login->getThrow("dnscustom_error"), '', "the zone must be a reverse zone, e.g. 207.180.81.in-addr.arpa");
		}

		if (isset($param['rzone_delete_flag']) && ($param['rzone_delete_flag'] === 'on')) {
			$out = self::run(array('delete', $z), $ok);

			if (!$ok) {
				self::fail($out);
			}

			$this->redirectTo('');

			return null;
		}

		$content = str_replace("\r", '', isset($param['rzone_content_f']) ? (string)$param['rzone_content_f'] : '');

		if (trim($content) === '') {
			throw new lxException($login->getThrow("dnscustom_error"), '', "the zone content is empty");
		}

		$tmp = tempnam(sys_get_temp_dir(), 'kndz');
		file_put_contents($tmp, rtrim($content) . "\n");

		$out = self::run(array('save', $z, $tmp), $ok);
		@unlink($tmp);

		if (!$ok) {
			self::fail($out);
		}

		$this->redirectTo($z);

		return null;
	}
}
