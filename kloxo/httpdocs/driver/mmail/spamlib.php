<?php

class Wlist_a extends LxMailClass
{
	static $__desc = array("", "",  "white_list");
	static $__desc_nname = array("n", "",  "white_list_address");

	static function createListAddForm($parent, $class) { return true; }

	static function createListAlist($parent, $class)
	{
		$alist[] = 'a=show';
		$alist[] = 'a=list&c=wlist_a';
		$alist[] = 'a=list&c=blist_a';

		return $alist;
	}

	// KloxoNext - user@domain.com, @domain.com or domain.com ('*' / '?' wildcards)
	static function add($parent, $class, $param)
	{
		global $login;

		$v = strtolower(trim((string)($param['nname'] ?? '')));

		if (($v === '') || !preg_match('/^[a-z0-9._%+*?-]*@?[a-z0-9.*?-]+$/', $v) || (strlen($v) > 255)) {
			throw new lxException($login->getThrow("invalid_email"), '', $v);
		}

		$param['nname'] = $v;

		return $param;
	}
}

class Blist_a extends LxMailClass
{
	static $__desc = array("", "",  "black_list");
	static $__desc_nname = array("n", "",  "black_list_address");
	static function createListAddForm($parent, $class) { return true; }

	static function createListAlist($parent, $class)
	{
		return Wlist_a::createListAlist($parent, $class);
	}

	static function add($parent, $class, $param)
	{
		return Wlist_a::add($parent, $class, $param);
	}
}

class Spam extends Lxdb
{
	static $__desc = array("", "",  "spam");
	static $__desc_status = array("f", "",  "enable_spam_filter");
	static $__desc_status_v_on = array("e", "",  "spam_is_on");
	static $__desc_status_v_off = array("e", "",  "spam_is_off");
	static $__desc_nname = array("", "",  "spam");
	static $__desc_spam_hit = array("n", "",  "score_at_which_mail_is_judged_spam");
	static $__desc_subject_tag = array("", "",  "subject_tag_for_spam");
	static $__desc_wlist_a = array("", "",  "white_list");
	static $__desc_blist_a = array("", "",  "black_list");

	// KloxoNext - stored on the mail account (filter_spam_status), edited here too
	static $__desc_spam_action = array("", "",  "what_to_do_with_spam");
	static $__desc_spam_engine_f = array("", "",  "spam_engine");

	static $__acdesc_update_update = array("", "",  "spam_status");

	function defaultValue($var)
	{
		if ($var === 'spam_hit') {
			return "5";
		}

		if ($var === 'subject_tag') {
			return "******SPAM******";
		}
	}

	function createShowPropertyList(&$alist)
	{
		global $gbl, $sgbl, $login, $ghtml;

		return null;

	}

	function createShowAlist(&$alist, $subaction = null)
	{
		// KloxoNext - the white / black lists of the account or domain
		$alist[] = 'a=list&c=wlist_a';
		$alist[] = 'a=list&c=blist_a';

		return $alist;
	}

	function createShowClist($subaction)
	{
		return null;
	}

	static function removeOtherDriver($driverapp)
	{
		global $gbl, $sgbl, $login, $ghtml;

		removeOtherDrivers($class = 'spam', $nolog = true);
	}

	// KloxoNext - SpamAssassin / Bogofilter / none wired into Postfix + Dovecot
	static function setupEngine($engine)
	{
		global $login;

		if (!in_array($engine, array('spamassassin', 'bogofilter', 'none'), true)) {
			return false;
		}

		$out = array();
		exec("sh /script/setup-spam apply " . escapeshellarg($engine) . " 2>&1", $out, $rc);

		foreach ($out as $l) {
			if (strpos($l, 'ERROR=') === 0) {
				throw new lxException($login->getThrow("spam_filter_error"), '', substr($l, 6));
			}
		}

		return ($rc === 0);
	}

	static function switchProgramPost($old, $new)
	{
		// done by the driver's installMe (setup-spam)
	}

	function createShowUpdateform()
	{
		$ulist['update'] = null;

		return $ulist;
	}

	function updateform($subaction, $param)
	{
		if (!$this->status) {
			$this->status = 'off';
		}

		$vlist['spam_engine_f'] = array('M', self::engineLabel());
		$vlist['status'] = null;
		$vlist['spam_hit'] = null;
		$vlist['subject_tag'] = null;

		$parent = $this->getParentO();

		if ($parent && ($parent->getClass() === 'mailaccount')) {
			$this->spam_action = $parent->filter_spam_status ? $parent->filter_spam_status : 'spambox';
			$vlist['spam_action'] = array('A', self::actionList());
		}

		$vlist['__v_updateall_button'] = array();

		return $vlist;
	}

	static function actionList()
	{
		return array('spambox' => 'Move to the Junk folder', 'mailbox' => 'Keep in the Inbox (subject tag only)',
			'delete' => 'Delete');
	}

	static function engineLabel()
	{
		$e = KnMail::spamEngine();
		$names = array('spamassassin' => 'SpamAssassin', 'bogofilter' => 'Bogofilter',
			'none' => 'none - spam filter off on this server (Mail > Spam Filter)');

		return isset($names[$e]) ? $names[$e] : $e;
	}

	function updateUpdate($param)
	{
		global $login;

		if (isset($param['spam_hit'])) {
			$h = trim((string)$param['spam_hit']);

			if (!preg_match('/^[0-9]{1,2}(\.[0-9])?$/', $h) || ((float)$h < 1) || ((float)$h > 30)) {
				throw new lxException($login->getThrow("spam_filter_error"), '', "the score must be between 1 and 30");
			}
		}

		if (isset($param['subject_tag'])) {
			$param['subject_tag'] = substr(trim(str_replace(array('"', '\\', '$', "\r", "\n"), '', (string)$param['subject_tag'])), 0, 64);
		}

		if (isset($param['spam_action'])) {
			$parent = $this->getParentO();

			if ($parent && ($parent->getClass() === 'mailaccount') && in_array($param['spam_action'], array('spambox', 'mailbox', 'delete'), true)) {
				$parent->filter_spam_status = $param['spam_action'];
				$parent->setUpdateSubaction('change_spam');
			}

			unset($param['spam_action']);
		}

		return $param;
	}

	function postUpdate()
	{
		// We need to write because the fixphpini reads everything from the database.
		$this->write();

		$parent = $this->getTrueParentO();

	//	if ($parent->is__table('mmail')) {
		if ($parent->getClass() === 'mmail') {
			$list = $parent->getList('mailaccount');

			foreach($list as $l) {
				$sp = $l->getObject('spam');
				$sp->spam_hit = $this->spam_hit;
				$sp->status = $this->status;
				$sp->subject_tag = $this->subject_tag;
				$sp->setUpdateSubaction($this->subaction);
				$l->setUpdateSubaction('change_spam');
				$l->was();
				$sp->was();
			}
		} else {
			$parent->setupdateSubaction('change_spam');
		}
	}
}
