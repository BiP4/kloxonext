if ! getent group lxpopgroup >/dev/null 2>&1; then
	/usr/sbin/groupadd -g 1005 -r lxpopgroup >/dev/null 2>&1 || :
fi

if ! getent passwd lxpopuser >/dev/null 2>&1; then
	/usr/sbin/useradd -u 1005 -r -M -d /home/lxadmin/mail/  -s /sbin/nologin -c "Vpopmail User" -g lxpopgroup lxpopuser 2>&1 || :
fi
