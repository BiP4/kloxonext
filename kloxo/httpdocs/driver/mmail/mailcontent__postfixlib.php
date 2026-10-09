<?php

include_once "driver/mmail/mailcontent__qmaillib.php";

// same Maildir parser as qmail; mailboxes live under /home/vmail/<domain>/<user>
class mailcontent__postfix extends mailcontent__qmail
{
	static function getMailContent($mailbox)
	{
		list($user, $dom) = explode("@", $mailbox);

		$path = KnMail::mailboxDir($dom, $user) . "/Maildir";
		$ret = null;

		foreach (array("new", "cur", ".Junk/new", ".Junk/cur", ".Spam/new", ".Spam/cur") as $d) {
			self::parseDir($ret, "{$path}/{$d}");
		}

		return $ret;
	}
}
