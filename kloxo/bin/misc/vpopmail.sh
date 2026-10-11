if ! getent group vchkpw >/dev/null 2>&1; then
	/usr/sbin/groupadd -g 89 -r vchkpw >/dev/null 2>&1 || :
fi

if ! getent passwd vpopmail >/dev/null 2>&1; then
	/usr/sbin/useradd -u 89 -r -M -d /home/vpopmail/  -s /sbin/nologin -c "Vpopmail User" -g vchkpw vpopmail 2>&1 || :
fi
