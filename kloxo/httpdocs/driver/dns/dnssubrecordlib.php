<?php

// KloxoNext - DNS page of a domain: the records of its subdomains, read only, as they
// are published in the domain's zone (blog.example.com 'www' -> 'www.blog'). They are
// edited on the subdomain's own DNS page (link in the Subdomain column).

class DnsSubrecord extends Lxclass
{
	static $__ttype = "transient";
	static $__desc = array("", "", "dns_subdomain_records");

	static $__desc_nname = array("", "", "dns_record");
	static $__desc_hostname = array("", "", "hostname");
	static $__desc_ttype = array("", "", "type");
	static $__desc_priority = array("", "", "priority");
	static $__desc_param = array("", "", "value");
	static $__desc_subdomain = array("", "", "subdomain", "__stub_subdomain_dns");

	// read only: nothing to load or save, every record on one page, sorted by host name
	function get() {}
	function write() {}
	static function perPage() { return 6000; }
	static function searchVar() { return "hostname"; }
	static function defaultSort() { return "hostname"; }
	static function defaultSortDir() { return "asc"; }

	static function createListNlist($parent, $view)
	{
		return array('hostname' => '20%', 'ttype' => '5%', 'priority' => '5%', 'param' => '50%', 'subdomain' => '20%');
	}

	static function createListAlist($parent, $class)
	{
		$alist[] = "a=list&c=dns_record_a";
		$alist[] = "a=list&c=$class";

		return $alist;
	}

	// read only: no select boxes, no buttons
	static function createListBlist($parent, $class)
	{
		return null;
	}

	function isSelect()
	{
		return false;
	}

	static function initThisListRule($parent, $class) { return null; }

	static function initThisList($parent, $class)
	{
		$res = array();

		if (!$parent || !kn_dns_is_valid_name($parent->nname) || kn_dns_get_subdomain_parent($parent->nname)) {
			return $res;
		}

		foreach (kn_dns_get_subdomain_records($parent->nname) as $k => $o) {
			$res[] = array(
				'nname' => $k,
				'parent_clname' => $parent->getClName(),
				'hostname' => (string)$o->hostname,
				'ttype' => (string)$o->ttype,
				'priority' => isset($o->priority) ? (string)$o->priority : '',
				'param' => (string)$o->param,
				'subdomain' => isset($o->__kn_subdomain) ? $o->__kn_subdomain : '',
			);
		}

		return $res;
	}

	function display($var)
	{
		if ($var === 'ttype') {
			return strtoupper((string)$this->ttype);
		}

		if (($var === 'priority') && ($this->ttype !== 'mx')) {
			return '-';
		}

		if (($var === 'param') && (strlen((string)$this->param) > 75)) {
			return substr($this->param, 0, 75) . "...";
		}

		return parent::display($var);
	}

	// the subdomain's DNS page, in the same context (client / admin) as this page
	function getStubUrl($var)
	{
		global $ghtml;

		$q = array('frm_action' => 'list', 'frm_o_cname' => 'dns_record_a');
		$i = 0;

		foreach ((array)$ghtml->frm_o_o as $o) {
			if (!isset($o['class'])) {
				continue;
			}

			if ($o['class'] === 'dns') {
				break;
			}

			$q["frm_o_o[{$i}][class]"] = $o['class'];

			if (isset($o['nname'])) {
				$q["frm_o_o[{$i}][nname]"] = ($o['class'] === 'domain') ? $this->subdomain : $o['nname'];
			}

			$i++;
		}

		$q["frm_o_o[{$i}][class]"] = 'dns';

		return create_simpleObject(array('url' => '/display.php?' . http_build_query($q),
			'purl' => 'a=list&c=dns_record_a', 'target' => null));
	}
}
