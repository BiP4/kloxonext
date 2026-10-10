<?php

// KloxoNext - Admin > Mail > Spam Filter
//
// Front end for /script/setup-spam: which engine filters the mail (SpamAssassin,
// Bogofilter or none), its server-wide settings, rule updates and a test.
// Per account / domain settings stay on the Spam page of the account or domain.

class SpamFilter extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "", "spam_filter");

	static $__desc_nname = array("n", "", "spam_filter", "a=show");

	// status
	static $__desc_sf_engine_f = array("", "", "spam_engine");
	static $__desc_sf_delivery_f = array("", "", "spam_delivery");
	static $__desc_sf_service_f = array("", "", "spam_service");
	static $__desc_sf_rules_f = array("", "", "spam_rules");
	static $__desc_sf_training_f = array("", "", "spam_training");
	static $__desc_sf_accounts_f = array("", "", "spam_accounts_on");
	static $__desc_sf_engine_use = array("", "", "spam_engine_use");

	// settings
	static $__desc_sf_learn_junk_flag = array("f", "", "spam_learn_from_junk");
	static $__desc_sf_max_kb = array("", "", "spam_max_kb");
	static $__desc_sf_network_flag = array("f", "", "spam_sa_network");
	static $__desc_sf_autolearn_flag = array("f", "", "spam_sa_autolearn");
	static $__desc_sf_autoupdate_flag = array("f", "", "spam_sa_autoupdate");
	static $__desc_sf_bogo_spam = array("", "", "spam_bogo_spam_cutoff");
	static $__desc_sf_bogo_ham = array("", "", "spam_bogo_ham_cutoff");

	// tools
	static $__desc_sf_tool = array("", "", "spam_tool");
	static $__desc_sf_result_f = array("t", "", "spam_tool_result");

	static $__acdesc_update_status = array("", "", "spam_filter_status");
	static $__acdesc_update_settings = array("", "", "spam_filter_settings");
	static $__acdesc_update_tools = array("", "", "spam_filter_tools");

	const RESULT_FILE = '/usr/local/lxlabs/kloxo/log/spam-tools.log';

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'spamfilter';
	}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
	}

	function createShowUpdateform()
	{
		$uflist['status'] = null;
		$uflist['settings'] = null;
		$uflist['tools'] = null;

		return $uflist;
	}

	// ------------------------------------------------------------ helper script

	static function run($args, &$ok = null)
	{
		$cmd = "sh /script/setup-spam";

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
			throw new lxException($login->getThrow("spam_filter_error"), '', "only the local server is managed here");
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

		throw new lxException($login->getThrow("spam_filter_error"), '', $msg ? $msg : implode(' ', array_slice((array)$out, -3)));
	}

	static function v($st, $k, $def = '')
	{
		return (isset($st[$k]) && ($st[$k] !== '')) ? $st[$k] : $def;
	}

	// ------------------------------------------------------------ forms

	function updateform($subaction, $param)
	{
		self::checkAccess();

		$st = self::status();
		$engine = self::v($st, 'ENGINE', 'none');

		switch ($subaction) {
			case "status":
				$names = array('spamassassin' => 'SpamAssassin', 'bogofilter' => 'Bogofilter', 'none' => 'None (no spam filtering)');
				$vlist['sf_engine_f'] = array('M', isset($names[$engine]) ? $names[$engine] : $engine);

				if ($engine !== 'none') {
					$vlist['sf_delivery_f'] = array('M', (self::v($st, 'DOVECOT') === 'on')
						? 'Active - every delivered message is checked (Dovecot Sieve)'
						: 'Not active - run: sh /script/setup-spam');
				}

				if ($engine === 'spamassassin') {
					$vlist['sf_service_f'] = array('M', 'SpamAssassin ' . self::v($st, 'SA_VERSION', '?')
						. ' - spamd: ' . self::v($st, 'SPAMD', 'unknown'));
					$rules = self::v($st, 'SA_RULES', 'package');
					$vlist['sf_rules_f'] = array('M', ($rules === 'package') ? 'Rules of the package (never updated yet)' : "Updated {$rules}");
					$vlist['sf_training_f'] = array('M', self::v($st, 'SA_BAYES_SPAM', '0') . ' spam / '
						. self::v($st, 'SA_BAYES_HAM', '0') . ' ham messages learned (Bayes is used from 200 of each)');
				} elseif ($engine === 'bogofilter') {
					$vlist['sf_service_f'] = array('M', 'Bogofilter ' . self::v($st, 'BOGO_VERSION', '?'));
					$vlist['sf_training_f'] = array('M', self::v($st, 'BOGO_SPAM', '0') . ' spam / '
						. self::v($st, 'BOGO_HAM', '0') . ' ham messages learned (Bogofilter needs training before it marks spam)');
				}

				$vlist['sf_accounts_f'] = array('M', self::v($st, 'ACCOUNTS_ON', '0'));

				$this->sf_engine_use = $engine;
				$vlist['sf_engine_use'] = array('A', array('spamassassin' => 'SpamAssassin', 'bogofilter' => 'Bogofilter', 'none' => 'None (off)'));

				return $vlist;

			case "settings":
				$this->sf_learn_junk_flag = self::v($st, 'LEARN_FROM_JUNK', 'on');
				$vlist['sf_learn_junk_flag'] = null;

				$this->sf_max_kb = self::v($st, 'MAX_KB', '1024');
				$vlist['sf_max_kb'] = null;

				$this->sf_network_flag = self::v($st, 'SA_NETWORK', 'on');
				$vlist['sf_network_flag'] = null;
				$this->sf_autolearn_flag = self::v($st, 'SA_AUTOLEARN', 'on');
				$vlist['sf_autolearn_flag'] = null;
				$this->sf_autoupdate_flag = self::v($st, 'SA_AUTOUPDATE', 'on');
				$vlist['sf_autoupdate_flag'] = null;

				$this->sf_bogo_spam = self::v($st, 'BOGO_SPAM_CUTOFF', '0.99');
				$vlist['sf_bogo_spam'] = null;
				$this->sf_bogo_ham = self::v($st, 'BOGO_HAM_CUTOFF', '0.45');
				$vlist['sf_bogo_ham'] = null;

				$vlist['__v_updateall_button'] = array();

				return $vlist;

			case "tools":
				$tools = array('test' => 'Test with a spam test message (GTUBE)');

				if ($engine === 'spamassassin') {
					$tools['update_rules'] = 'Update the SpamAssassin rules now';
				}

				$this->sf_tool = 'test';
				$vlist['sf_tool'] = array('A', $tools);

				$last = is_readable(self::RESULT_FILE) ? (string)file_get_contents(self::RESULT_FILE) : '';
				$vlist['sf_result_f'] = array('t', ($last !== '') ? $last : '(nothing run yet)');

				return $vlist;
		}
	}

	function updateStatus($param)
	{
		global $login;

		self::checkAccess();
		$this->checkLocal();

		$want = isset($param['sf_engine_use']) ? (string)$param['sf_engine_use'] : '';

		if (!in_array($want, array('spamassassin', 'bogofilter', 'none'), true)) {
			throw new lxException($login->getThrow("spam_filter_error"), '', "unknown engine");
		}

		$out = self::run(array('apply', $want), $ok);

		if (!$ok) {
			self::fail($out);
		}

		// the panel's spam driver (Switch Programs) follows; its output is not for the page
		ob_start();
		changeDriverFunc('localhost', 'spam', $want);
		ob_end_clean();

		return null;
	}

	function updateSettings($param)
	{
		global $login;

		self::checkAccess();
		$this->checkLocal();

		$onoff = function ($k) use ($param) {
			return (isset($param[$k]) && ($param[$k] === 'on')) ? 'on' : 'off';
		};

		$kb = trim(isset($param['sf_max_kb']) ? (string)$param['sf_max_kb'] : '');
		$sc = trim(isset($param['sf_bogo_spam']) ? (string)$param['sf_bogo_spam'] : '');
		$hc = trim(isset($param['sf_bogo_ham']) ? (string)$param['sf_bogo_ham'] : '');

		if (!preg_match('/^[0-9]{2,6}$/', $kb) || ((int)$kb < 64) || ((int)$kb > 102400)) {
			throw new lxException($login->getThrow("spam_filter_error"), '', "maximum size: 64 - 102400 KB");
		}

		foreach (array($sc, $hc) as $c) {
			if (!preg_match('/^(0(\.[0-9]{1,4})?|1(\.0{1,4})?)$/', $c)) {
				throw new lxException($login->getThrow("spam_filter_error"), '', "Bogofilter cutoffs are numbers from 0 to 1 (for example 0.99)");
			}
		}

		if ((float)$hc > (float)$sc) {
			throw new lxException($login->getThrow("spam_filter_error"), '', "the ham cutoff cannot be above the spam cutoff");
		}

		$out = self::run(array('set',
			'LEARN_FROM_JUNK=' . $onoff('sf_learn_junk_flag'),
			"MAX_KB={$kb}",
			'SA_NETWORK=' . $onoff('sf_network_flag'),
			'SA_AUTOLEARN=' . $onoff('sf_autolearn_flag'),
			'SA_AUTOUPDATE=' . $onoff('sf_autoupdate_flag'),
			"BOGO_SPAM_CUTOFF={$sc}",
			"BOGO_HAM_CUTOFF={$hc}"), $ok);

		if (!$ok) {
			self::fail($out);
		}

		return null;
	}

	function updateTools($param)
	{
		global $login;

		self::checkAccess();
		$this->checkLocal();

		$tool = isset($param['sf_tool']) ? (string)$param['sf_tool'] : '';

		if ($tool === 'test') {
			$out = self::run('test', $ok);
			$title = "Test message (GTUBE) through the filter";
		} elseif ($tool === 'update_rules') {
			$out = self::run('update-rules', $ok);
			$title = "SpamAssassin rule update (sa-update)";
		} else {
			throw new lxException($login->getThrow("spam_filter_error"), '', "unknown tool");
		}

		$text = date('Y-m-d H:i:s') . " - {$title}\n\n" . implode("\n", $out) . "\n";
		@file_put_contents(self::RESULT_FILE, $text);

		if (!$ok) {
			self::fail($out);
		}

		return null;
	}
}
