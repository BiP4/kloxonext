<?php

class Smtp__postfix extends lxDriverClass
{
	static function installMe()
	{
		exec("sh /script/setup-mail >/dev/null 2>&1");
		exec("systemctl enable --now postfix dovecot >/dev/null 2>&1");
	}

	static function unInstallMe()
	{
		exec("systemctl disable --now postfix dovecot >/dev/null 2>&1");
	}
}
