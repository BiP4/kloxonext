#!/bin/bash
case "${BASH_VERSION:-}" in "") exec /bin/bash "$0" "$@" ;; esac

cd /usr/local/lxlabs/kloxo/httpdocs/

lxphp.exe "$@"
