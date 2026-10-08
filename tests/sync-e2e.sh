#!/bin/bash
# copy the working tree into the kn-e2e container (keeps runtime state)
docker exec "${1:-kn-e2e}" bash -c '
K=/usr/local/lxlabs/kloxo
rsync -a --exclude=/etc/conf/ --exclude=/etc/slavedb/ --exclude=/etc/flag/ --exclude="/etc/list/*.lst" \
	--exclude="/init/*_active" --exclude=/init/kloxo-nginx.conf --exclude=/init/tmp/ --exclude=/log/ \
	--exclude=/session/ --exclude=/httpdocs/thirdparty/ --exclude=/etc/ssl-default/ --exclude="/etc/program.*" \
	/src/kloxo/ $K/
rsync -a /src/kloxo/etc/list/set.*.lst $K/etc/list/
chmod -R go-w $K; chown -R lxlabs:lxlabs $K/httpdocs
'
