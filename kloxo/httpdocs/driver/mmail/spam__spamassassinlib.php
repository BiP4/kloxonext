<?php

// KloxoNext - SpamAssassin (spamd) for the Postfix + Dovecot mail (see /script/setup-spam).
// The per-account settings end up in the account's Sieve script (KnMail).
class Spam__Spamassassin extends lxDriverClass
{
	static function installMe()
	{
		Spam::setupEngine('spamassassin');
	}

	static function uninstallMe()
	{
		// the next engine's installMe stops spamd; the package stays
	}

	function dbactionAdd()
	{
		KnMail::scheduleRebuild();
	}

	function dbactionDelete()
	{
		KnMail::scheduleRebuild();
	}

	function dbactionUpdate($subaction)
	{
		KnMail::scheduleRebuild();
	}
}
