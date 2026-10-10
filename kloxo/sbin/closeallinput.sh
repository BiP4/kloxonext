#!/bin/bash
#
#    KloxoNext - replacement for cexe/closeallinput (a static C helper that the
#    legacy 'create-kloxoexe' build deleted when gcc/glibc-static were missing).
#
#    Closes every inherited descriptor above stderr - the backend's listening
#    sockets must not leak into restarted services - then runs the command.
#

for fd in /proc/$$/fd/* ; do
	n="${fd##*/}"
	if [[ "${n}" -gt 2 ]] 2>/dev/null ; then
		eval "exec ${n}>&-" 2>/dev/null
	fi
done

exec /bin/sh -c "$*"
