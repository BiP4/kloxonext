<?php

// KloxoNext - Cloudflare account of a client (API token), see lib/html/cloudflarelib.php

class Cfaccount extends lxClass
{
	static $__ttype = "permanent";
	static $__desc = array("S", "", "cloudflare_account");

	static $__desc_nname = array("n", "", "cloudflare_account", "a=show");

	static $__desc_cf_status_f = array("", "", "cloudflare_status");
	static $__desc_cf_token_f = array("", "", "cloudflare_api_token");
	static $__desc_cf_account_id_f = array("", "", "cloudflare_account_id");
	static $__desc_cf_disconnect_flag = array("f", "", "cloudflare_disconnect");

	static $__acdesc_update_connect = array("", "", "cloudflare_connect");

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'cfaccount';
	}

	function createShowPropertyList(&$alist)
	{
		$alist['property'][] = 'a=show';
	}

	function createShowUpdateform()
	{
		$uflist['connect'] = null;

		return $uflist;
	}

	function clientName()
	{
		return $this->getParentO()->nname;
	}

	function updateform($subaction, $param)
	{
		$acc = kn_cf_read('accounts', $this->clientName());

		if (!empty($acc['token'])) {
			$status = 'Connected (' . (isset($acc['verified']) ? $acc['verified'] : 'token valid')
				. ', token ****' . substr($acc['token'], -4) . ')';
		} else {
			$status = 'Not connected';
		}

		$vlist['cf_status_f'] = array('M', $status);
		$vlist['cf_token_f'] = array('m', array('value' => ''));
		$vlist['cf_account_id_f'] = array('m', array('value' => isset($acc['account_id']) ? $acc['account_id'] : ''));

		if (!empty($acc['token'])) {
			$this->cf_disconnect_flag = 'off';
			$vlist['cf_disconnect_flag'] = null;
		}

		return $vlist;
	}

	function updateConnect($param)
	{
		global $login;

		$client = $this->clientName();
		$acc = kn_cf_read('accounts', $client);

		if (isset($param['cf_disconnect_flag']) && ($param['cf_disconnect_flag'] === 'on')) {
			kn_cf_delete('accounts', $client);

			return null;
		}

		$token = trim(isset($param['cf_token_f']) ? (string)$param['cf_token_f'] : '');
		$accountId = trim(isset($param['cf_account_id_f']) ? (string)$param['cf_account_id_f'] : '');

		if (($accountId !== '') && !preg_match('/^[a-f0-9]{32}$/i', $accountId)) {
			throw new lxException($login->getThrow("cloudflare_error"), '', 'the account ID has 32 hexadecimal characters');
		}

		// an empty token keeps the stored one (only the account ID changes)
		if ($token === '') {
			if (empty($acc['token'])) {
				throw new lxException($login->getThrow("cloudflare_error"), '', 'enter an API token');
			}

			$token = $acc['token'];
		}

		if (!preg_match('/^[A-Za-z0-9_-]{20,200}$/', $token)) {
			throw new lxException($login->getThrow("cloudflare_error"), '', 'this does not look like a Cloudflare API token');
		}

		$api = new KnCloudflare($token);
		$v = $api->verify();

		if ($v === null) {
			throw new lxException($login->getThrow("cloudflare_error"), '', 'Cloudflare refused the token: ' . $api->error);
		}

		kn_cf_write('accounts', $client, array('token' => $token, 'account_id' => $accountId,
			'verified' => $v . ', ' . date('Y-m-d H:i')));

		return null;
	}
}
