<?php

// KloxoNext - server wide mail settings mapped to Postfix parameters
class Servermail__Postfix extends lxDriverClass
{
	function dbactionAdd()
	{
	}

	function dbactionDelete()
	{
	}

	private static function postconf($param, $value)
	{
		exec("postconf -e " . escapeshellarg("{$param} = {$value}") . " 2>&1");
	}

	function dbactionUpdate($subaction)
	{
		$m = $this->main;

		if (!empty($m->myname)) {
			validate_domain_name($m->myname);
			self::postconf('myhostname', $m->myname);
		}

		if (is_numeric($m->queuelifetime ?? null) && (int)$m->queuelifetime > 0) {
			// Kloxo stores seconds
			self::postconf('maximal_queue_lifetime', ((int)$m->queuelifetime) . 's');
			self::postconf('bounce_queue_lifetime', ((int)$m->queuelifetime) . 's');
		}

		if (is_numeric($m->concurrencyremote ?? null) && (int)$m->concurrencyremote > 0) {
			self::postconf('default_destination_concurrency_limit', (int)$m->concurrencyremote);
		}

		if (is_numeric($m->max_size ?? null) && (int)$m->max_size > 0) {
			self::postconf('message_size_limit', (int)$m->max_size);
		}

		if (is_numeric($m->max_rcpnts ?? null) && (int)$m->max_rcpnts > 0) {
			self::postconf('smtpd_recipient_limit', (int)$m->max_rcpnts);
		}

		if (!empty($m->smtp_relay)) {
			self::postconf('relayhost', $m->smtp_relay);
		}

		// RBLs + basic sender/client checks
		$restr = array('permit_mynetworks', 'permit_sasl_authenticated', 'reject_unauth_destination');

		if ($m->isOn('reject_unresolvable_rdns_flag')) {
			$restr[] = 'reject_unknown_reverse_client_hostname';
		}

		if ($m->isOn('reject_missing_sender_mx_flag')) {
			$restr[] = 'reject_unknown_sender_domain';
		}

		if ($m->isOn('enable_maps')) {
			$lists = trim((string)$m->dns_blacklists) !== '' ? preg_split('/[\s,]+/', trim($m->dns_blacklists)) : array('zen.spamhaus.org');

			foreach ($lists as $bl) {
				if (preg_match('/^[a-z0-9.-]+$/i', $bl)) {
					$restr[] = "reject_rbl_client {$bl}";
				}
			}
		}

		self::postconf('smtpd_recipient_restrictions', implode(', ', $restr));

		if (is_numeric($m->greet_delay ?? null) && (int)$m->greet_delay > 0) {
			self::postconf('postscreen_greet_wait', ((int)$m->greet_delay) . 's');
		}

		exec("postfix reload >/dev/null 2>&1");
	}
}
