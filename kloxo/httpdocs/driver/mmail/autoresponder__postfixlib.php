<?php

// KloxoNext - autoresponder on Postfix + Dovecot: regenerate the maps/sieve scripts
class Autoresponder__Postfix extends lxDriverClass
{
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
