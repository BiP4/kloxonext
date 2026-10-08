# KloxoNext - core configuration, sourced by install/setup.sh
#
# Expects: ppath, installtype, kloxostate, admin_password, os.inc loaded

echo ">>> step2.inc <<<"

kloxopath="${ppath}"
lxlabspath="/usr/local/lxlabs"

dbroot='root'
dbpass="$(os_random_password 24)"

kloxo_prepare_dirs() {
	step "Prepare Kloxo directories"

	mkdir -p "${kloxopath}/etc/conf" "${kloxopath}/etc/slavedb" "${kloxopath}/etc/flag" \
		"${kloxopath}/session" "${kloxopath}/log" "${kloxopath}/pid"

	mkdir -p /home/kloxo/httpd /home/kloxo/httpd/cp /var/log/kloxo /var/run/acme/acme-challenge
	chown -R apache:apache /home/kloxo/httpd
	rm -f /home/kloxo/httpd/skeleton-disable.zip

	chmod 777 "${kloxopath}/session"

	if [ ! -f "${kloxopath}/etc/conf/kloxo.pass" ] ; then
		os_random_password 16 > "${kloxopath}/etc/conf/kloxo.pass"
		chmod 600 "${kloxopath}/etc/conf/kloxo.pass"
	fi

	if [ ! -f "${kloxopath}/etc/slavedb/dbadmin" ] ; then
		echo 'O:6:"Remote":1:{s:4:"data";a:1:{s:5:"mysql";a:1:{s:10:"dbpassword";s:0:"";}}}' \
			> "${kloxopath}/etc/slavedb/dbadmin"
	fi

	if [ ! -f "${kloxopath}/etc/slavedb/driver" ] ; then
		if os_is_el ; then
			webdrv='s:3:"web";s:6:"apache";'
		else
			webdrv='s:3:"web";s:5:"nginx";'
		fi

		# smtp keeps the historical 'qmail' driver id until the Postfix driver lands (phase 4)
		echo 'O:6:"Remote":1:{s:4:"data";a:5:{'"${webdrv}"'s:4:"spam";s:10:"bogofilter";s:3:"dns";s:4:"bind";s:4:"pop3";s:7:"dovecot";s:4:"smtp";s:5:"qmail";}}' \
			> "${kloxopath}/etc/slavedb/driver"
	fi

	# cexe helpers must be setuid lxlabs
	if [ -d "${kloxopath}/cexe" ] ; then
		chown -R lxlabs:lxlabs "${kloxopath}/cexe"
		chmod -R 755 "${kloxopath}/cexe"
		chmod -R ug+s "${kloxopath}/cexe"
	fi

	chown -R lxlabs:lxlabs "${lxlabspath}"
	chown -R lxlabs:lxlabs "${kloxopath}/httpdocs"
}

kloxo_create_database() {
	step "Create Kloxo database"

	svc_active mariadb || svc_start mariadb

	sh /script/reset-mysql-root-password "${dbpass}"

	svc_restart mariadb

	cd "${kloxopath}/httpdocs" || exit 1

	lxphp.exe "${kloxopath}/bin/install/create.php" \
		--install-type="${installtype}" \
		--db-rootuser="${dbroot}" \
		--db-rootpassword="${dbpass}" \
		--admin-password="${admin_password}"

	cd / || exit 1
}

kloxo_install_bye() {
	local this_ip
	this_ip=$(ip route get 1 2>/dev/null | sed -n 's/^.*src \([0-9.]*\) .*$/\1/p')

	echo
	echo "======================================================================"
	echo "  KloxoNext has been installed as '${installtype^^}' on ${OS_ID} ${OS_VERSION}"
	echo "======================================================================"

	if [ "${installtype}" == 'master' ] ; then
		echo "  Panel:     https://${this_ip}:7777   (http://${this_ip}:7778)"

		if [ "${kloxostate}" == 'none' ] ; then
			echo "  Login:     admin"
			echo "  Password:  ${admin_password}"
			echo
			echo "  The password is shown only once - store it now."
		fi
	else
		echo "  Open port 7779 on this server (master <-> slave communication)"
		echo "  Then on the master: admin -> servers -> add server"
	fi

	echo
	echo "  Panel PHP:        $(php_dotted "${PHP_PANEL_BRANCH}")"
	echo "  Domain PHP:       $(for d in /opt/php[0-9][0-9]m ; do [ -d "$d" ] && echo -n "$(php_dotted "$(basename "$d" | cut -c4-5)") " ; done)"
	echo "  Third-party PHP:  $(php_dotted "$(cat "${kloxopath}/init/kloxo_apps_php_active" 2>/dev/null)")"
	echo "======================================================================"
	echo
}

kloxo_prepare_dirs

if [ "${kloxostate}" == 'none' ] ; then
	kloxo_create_database
fi
