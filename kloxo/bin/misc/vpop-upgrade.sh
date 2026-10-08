#!/bin/bash
[ -z "$BASH_VERSION" ] && exec /bin/bash "$0" "$@"

'cp' -a /home/vpopmail ~/vpopmail-backup
rpm -e courier-imap vpopmail
up2date --nosig vpopmail courier-imap
'cp' ~/vpopmail-backup/etc/vpopmail.mysql /home/vpopmail/etc/
service courier restart
