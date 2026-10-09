<?php 

// KloxoNext - Dovecot from the distribution (systemd); qmail supervise is gone
class Pop3__dovecot extends lxDriverClass
{
	static function installMe()
	{
		exec("sh /script/setup-mail >/dev/null 2>&1");
		exec("systemctl enable --now dovecot >/dev/null 2>&1");
	}

	static function unInstallMe()
	{
		exec("systemctl disable --now dovecot >/dev/null 2>&1");
	}
}
