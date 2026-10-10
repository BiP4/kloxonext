#!/bin/bash
[ -z "$BASH_VERSION" ] && exec /bin/bash "$0" "$@"

#    KloxoNext - backend daemon of the panel (run by kloxo-wrap.service).
#    It applies the queued service restarts (etc/.restart) and the background
#    jobs of the panel. systemd restarts it when it exits.

root="/usr/local/lxlabs/kloxo"

if [[ -f "${root}/sbin/custom.kloxo.php" ]] ; then
	server="${root}/sbin/custom.kloxo.php"
else
	server="${root}/sbin/kloxo.php"
fi

if [[ -f "${root}/etc/conf/slave-db.db" ]] ; then
	mode="slave"
else
	mode="master"
fi

mkdir -p "${root}/log" "${root}/pid"
echo $$ > "${root}/pid/wrapper.pid"

cd "${root}/httpdocs" || exit 1

exec /usr/bin/lxphp.exe -f "${server}" "${mode}"
