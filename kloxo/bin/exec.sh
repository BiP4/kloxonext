#!/bin/bash
[ -z "$BASH_VERSION" ] && exec /bin/bash "$0" "$@"

cd /usr/local/lxlabs/kloxo/httpdocs/

lxphp.exe "$@"
