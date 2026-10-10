#!/bin/bash
[ -z "$BASH_VERSION" ] && exec /bin/bash "$0" "$@"

#	KloxoNext - Control Panel
#
#	Copyright (C) 2018 - KloxoCommunity
#
#	This program is free software: you can redistribute it and/or modify
#	it under the terms of the GNU Affero General Public License as
#	published by the Free Software Foundation, either version 3 of the
#	License, or (at your option) any later version.
#
#	This program is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU Affero General Public License for more details.
#
#	You should have received a copy of the GNU Affero General Public License
#	along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#
# KloxoNext setup - installs every service from the distribution package
# manager (dnf on AlmaLinux/Rocky 9-10, apt on Ubuntu 26.04).
#
# Options:
#   --php="84 85"         PHP branches for domains (default: 8.4 + newest available)
#   --admin-password=XXX  password for 'admin' (default: random, printed at the end)
#   --install-type=slave  install as slave node
#   --no-firewall         do not open the ports / switch on the firewall
#   --yes                 do not ask for confirmation
#

ppath="/usr/local/lxlabs/kloxo"

if [ ! -d "${ppath}/pscript" ] ; then
	echo "KloxoNext files not found in ${ppath}. Use kloxonext-install.sh to bootstrap."
	exit 1
fi

# /script must exist before anything else (every helper lives there)
if [ ! -L /script ] ; then
	rm -rf /script
	ln -sf "${ppath}/pscript" /script
fi

[ -f /script/programname ] || echo 'kloxo' > /script/programname

. /script/os.inc
. /script/php-native.inc

APP_NAME='KloxoNext'
OPT_PHP=""
OPT_ADMIN_PASS=""
OPT_YES=""
OPT_FIREWALL=1

if [ -f "${ppath}/etc/conf/slave-db.db" ] ; then
	APP_TYPE='slave'
else
	APP_TYPE='master'
fi

for arg in "$@" ; do
	case "${arg}" in
		--php=*)            OPT_PHP="${arg#*=}" ;;
		--admin-password=*) OPT_ADMIN_PASS="${arg#*=}" ;;
		--install-type=*)   APP_TYPE="${arg#*=}" ;;
		--yes|-y)           OPT_YES=1 ;;
		--no-firewall)      OPT_FIREWALL="" ;;
	esac
done

C_OK="\e[32m OK \e[0m"
C_NO="\e[31m NO \e[0m"

step() {
	echo
	echo -e "\e[1;34m>>> $* \e[0m"
}

die() {
	echo -e "\e[31m*** $* \e[0m"
	exit 1
}

# ---------------------------------------------------------------------------
# Pre-flight checks
# ---------------------------------------------------------------------------

step "Pre-flight checks"

[ "$(id -u)" -eq 0 ] || die "You must be 'root' to install ${APP_NAME}"
echo -e "Installing as root            ${C_OK}"

if os_is_supported ; then
	echo -e "Operating system              ${C_OK} (${OS_ID} ${OS_VERSION})"
else
	echo -e "Operating system              ${C_NO} (${OS_ID} ${OS_VERSION})"
	echo "  Supported: AlmaLinux/Rocky 9.x and 10.x, Ubuntu 26.04 LTS"
	[ -n "${OPT_YES}" ] || die "Unsupported OS (use --yes to force at your own risk)"
fi

[ "$(uname -m)" == "x86_64" ] || [ "$(uname -m)" == "aarch64" ] || die "Only x86_64 and aarch64 are supported"

command -v hostname >/dev/null 2>&1 || { pkg_install hostname >/dev/null 2>&1 ; }

if [ "$(hostname -f 2>/dev/null)" == "$(hostname -s)" ] ; then
	die "Hostname '$(hostname)' is not a FQDN. Run: hostnamectl set-hostname server1.example.com"
fi
echo -e "FQDN hostname                 ${C_OK} ($(hostname -f))"

if grep -qs '^[^#].*[[:space:]]/tmp[[:space:]].*tmpfs' /etc/fstab ; then
	die "'/tmp' is mounted as tmpfs in /etc/fstab; remove it and reboot (backups need a real /tmp)"
fi

if os_is_el && command -v selinuxenabled >/dev/null 2>&1 && selinuxenabled ; then
	echo "SELinux enabled - switching to permissive/disabled (Kloxo manages many paths outside policy)"
	setenforce 0
	sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
fi

mkdir -p "${ppath}/log" "${ppath}/etc/conf" "${ppath}/etc/flag" "${ppath}/pid" "${ppath}/session"

if [ -d /var/lib/mysql/kloxo ] ; then
	kloxostate='installed'
else
	kloxostate='none'
fi

# ---------------------------------------------------------------------------
# Repositories and base packages
# ---------------------------------------------------------------------------

step "Configure repositories (${OS_PKG})"
pkg_refresh
os_setup_repos

step "Install base packages"
pkg_install_logical base archive sudo cron quota
svc_enable cron
svc_start cron

if os_is_el ; then
	pkg_install chkconfig initscripts-service dnf-utils
fi

# Kloxo scripts call 'chkconfig'; Ubuntu does not ship it
if ! command -v chkconfig >/dev/null 2>&1 || grep -qs KloxoNext /usr/sbin/chkconfig ; then
	install -m 0755 "${ppath}/file/linux/compat/chkconfig" /usr/sbin/chkconfig
fi

step "System accounts"
os_compat_accounts

# ---------------------------------------------------------------------------
# Services
# ---------------------------------------------------------------------------

step "Remove conflicting mail servers"
if os_is_el ; then
	pkg_remove sendmail exim opensmtpd ssmtp 2>/dev/null
else
	pkg_remove exim4 exim4-base exim4-config exim4-daemon-light 2>/dev/null
fi

step "Install database server (MariaDB)"
pkg_install_logical mariadb-server mariadb-client
mkdir -p /var/lib/mysqltmp
chown mysql:mysql /var/lib/mysqltmp
sh /script/set-mysql-default
svc_enable mariadb
svc_start mariadb

step "Install web servers"
pkg_install_logical nginx apache
os_systemd_overrides
# the domain web server is started by Kloxo after its configuration is written
svc_disable apache
svc_stop apache
svc_disable nginx
svc_stop nginx

step "Install DNS server (BIND)"
pkg_install_logical bind
mkdir -p /var/log/named
chown "${OS_NAMED_USER}":root /var/log/named
chmod 755 /var/log/named
rm -f /etc/rndc.conf
os_debian_bind_compat
# zone files of every DNS driver live here; named needs write access (journals)
mkdir -p /opt/configs/nsd/conf
chown -R named /opt/configs/nsd/conf

step "Install mail services (Postfix + Dovecot)"
if os_is_debian ; then
	# preseed postfix so apt does not open a dialog
	echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
	echo "postfix postfix/mailname string $(hostname -f)" | debconf-set-selections
fi
pkg_install_logical postfix dovecot opendkim spamassassin
pkg_install bogofilter

step "Install FTP server (Pure-FTPd)"
pkg_install_logical pure-ftpd

step "Install statistics and security tools"
pkg_install_logical webalizer awstats fail2ban certbot
# network diagnostics used by the panel (Server > Traceroute)
pkg_install traceroute >/dev/null 2>&1

# ---------------------------------------------------------------------------
# PHP
# ---------------------------------------------------------------------------

PHP_PANEL_BRANCH="$(php_panel_branch)"
step "Install PHP for the panel (php${PHP_PANEL_BRANCH}s)"
sh /script/phpm-installer "php${PHP_PANEL_BRANCH}s" -y || die "Cannot install PHP $(php_dotted "${PHP_PANEL_BRANCH}") for the panel"

if [ -z "${OPT_PHP}" ] ; then
	OPT_PHP="${PHP_PANEL_BRANCH} $(php_latest_available)"
fi

step "Install PHP for domains: $(for x in $(echo "${OPT_PHP}" | tr ' ' '\n' | sort -u) ; do echo -n "$(php_dotted "${x}") " ; done)"

# multiple PHP is always on: every phpXYm gets its php-fpm service (per-domain 'PHP Selected')
touch "${ppath}/etc/flag/enablemultiplephp.flg"

for xy in $(echo "${OPT_PHP}" | tr ' ' '\n' | sort -u) ; do
	sh /script/phpm-installer "php${xy}m"
done

php_set_branch "$(php_latest_installed)"
sh /script/enable-php-fpm

sh /script/fixlxphpexe "php${PHP_PANEL_BRANCH}s"
sh /script/set-kloxo-apps-php

# ---------------------------------------------------------------------------
# Kloxo core (database, default objects)
# ---------------------------------------------------------------------------

installtype="${APP_TYPE}"
admin_password="${OPT_ADMIN_PASS:-$(os_random_password 16)}"

. "${ppath}/install/step2.inc"

# ---------------------------------------------------------------------------
# Defaults
# ---------------------------------------------------------------------------

step "Select default drivers"
if os_is_el ; then
	sh /script/setdriver --server=localhost --class=web --driver=apache >/dev/null 2>&1
	chkconfig httpd on >/dev/null 2>&1
	# php-fpm through mod_proxy_fcgi: enables the per-domain 'PHP Selected'
	(cd "${ppath}/httpdocs" && lxphp.exe ../bin/misc/set-default-phptype.php)
else
	# the nginx driver writes to /etc/nginx on every distribution
	sh /script/setdriver --server=localhost --class=web --driver=nginx >/dev/null 2>&1
	chkconfig nginx on >/dev/null 2>&1
fi
sh /script/setdriver --server=localhost --class=webcache --driver=none >/dev/null 2>&1
sh /script/setdriver --server=localhost --class=dns --driver=bind >/dev/null 2>&1
sh /script/setdriver --server=localhost --class=spam --driver=bogofilter >/dev/null 2>&1

sh /script/skin-set-for-all >/dev/null 2>&1
sh /script/set-hosts >/dev/null 2>&1
sh /script/fix-service-list >/dev/null 2>&1

step "Configure mail (Postfix + Dovecot)"
sh /script/setup-mail

step "Install third-party applications (phpMyAdmin, Roundcube, ...)"
sh /script/thirdparty-update --install

if [ -n "${OPT_FIREWALL}" ] ; then
	step "Open the firewall ports (web, FTP, mail, DNS, SSH, panel)"
	sh /script/firewall install-defaults "${installtype}" || echo "- firewall not configured: open the ports in Admin > Security > Firewall"
fi

step "Restart services"
sh /script/restart-all --force >/dev/null 2>&1

kloxo_install_bye
