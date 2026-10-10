<?php

class Spam__none extends lxDriverClass
{
	// KloxoNext - no spam filter: setup-spam removes it from Dovecot and stops spamd
	static function installMe()
	{
		Spam::setupEngine('none');
	}

	static function uninstallMe()
	{
	}

	function dbactionAdd()
	{
		KnMail::scheduleRebuild();
	}

	function dbactionDelete()
	{
	}

	function dbactionUpdate($subaction)
	{
		KnMail::scheduleRebuild();
	}
}
