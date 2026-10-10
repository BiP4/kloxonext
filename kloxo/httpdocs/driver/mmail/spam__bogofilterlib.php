<?php

// KloxoNext - Bogofilter for the Postfix + Dovecot mail (see /script/setup-spam).
// The per-account settings end up in the account's Sieve script (KnMail).
class Spam__bogofilter extends lxDriverClass
{
	static function installMe()
	{
		Spam::setupEngine('bogofilter');
	}

	static function unInstallMe()
	{
		// the next engine's installMe reconfigures everything; the package stays
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
