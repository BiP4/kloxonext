#!/bin/bash
#
#    KloxoNext - OS abstraction layer
#
#    Source this file from any script that needs to install packages, manage
#    services or know distribution specific paths:
#
#        . /script/os.inc
#
#    Supported targets:
#      - AlmaLinux / Rocky / RHEL 9.x and 10.x  (family: el,     pkg: dnf)
#      - Ubuntu 26.04 LTS                        (family: debian, pkg: apt)
#
#    All public functions are prefixed with 'os_' or 'pkg_' or 'svc_'.
#

[ -n "${KLOXO_OS_INC_LOADED:-}" ] && return 0
KLOXO_OS_INC_LOADED=1

KLOXO_PATH="/usr/local/lxlabs/kloxo"

# ---------------------------------------------------------------------------
# Detection
# ---------------------------------------------------------------------------

os_detect() {
	OS_ID=""
	OS_VERSION=""
	OS_MAJOR=""
	OS_FAMILY=""
	OS_PKG=""

	if [ -f /etc/os-release ] ; then
		# shellcheck disable=SC1091
		. /etc/os-release
		OS_ID="${ID:-}"
		OS_VERSION="${VERSION_ID:-}"
		OS_MAJOR="${VERSION_ID%%.*}"
		OS_LIKE="${ID_LIKE:-}"
	fi

	case "${OS_ID} ${OS_LIKE}" in
		*rhel*|*fedora*|*centos*|*almalinux*|*rocky*)
			OS_FAMILY="el"
			OS_PKG="dnf"
			command -v dnf >/dev/null 2>&1 || OS_PKG="yum"
			;;
		*debian*|*ubuntu*)
			OS_FAMILY="debian"
			OS_PKG="apt"
			;;
		*)
			OS_FAMILY="unknown"
			OS_PKG="unknown"
			;;
	esac

	export OS_ID OS_VERSION OS_MAJOR OS_FAMILY OS_PKG
}

os_is_el()     { [ "${OS_FAMILY}" = "el" ]; }
os_is_debian() { [ "${OS_FAMILY}" = "debian" ]; }

# Returns 0 when the running OS is one KloxoNext officially supports.
os_is_supported() {
	case "${OS_ID}" in
		almalinux|rocky|rhel|centos)
			[ "${OS_MAJOR}" = "9" ] || [ "${OS_MAJOR}" = "10" ]
			;;
		ubuntu)
			[ "${OS_VERSION}" = "26.04" ]
			;;
		*)
			return 1
			;;
	esac
}

# ---------------------------------------------------------------------------
# Package name mapping (logical name -> distribution package names)
# ---------------------------------------------------------------------------

# Usage: pkg_map <logical-name>  -> prints space separated real package names
pkg_map() {
	local n="$1"

	if os_is_el ; then
		case "${n}" in
			apache)          echo "httpd httpd-tools mod_ssl mod_fcgid" ;;
			nginx)           echo "nginx" ;;
			mariadb-server)  echo "mariadb-server" ;;
			mariadb-client)  echo "mariadb" ;;
			bind)            echo "bind bind-utils" ;;
			pure-ftpd)       echo "pure-ftpd" ;;
			postfix)         echo "postfix postfix-mysql" ;;
			dovecot)         echo "dovecot dovecot-mysql dovecot-pigeonhole" ;;
			opendkim)        echo "opendkim opendkim-tools" ;;
			spamassassin)    echo "spamassassin" ;;
			clamav)          echo "clamav clamav-update clamd" ;;
			cron)            echo "cronie cronie-anacron crontabs" ;;
			quota)           echo "quota" ;;
			certbot)         echo "certbot" ;;
			fail2ban)        echo "fail2ban" ;;
			webalizer)       echo "webalizer" ;;
			awstats)         echo "awstats" ;;
			sudo)            echo "sudo" ;;
			pwgen)           echo "pwgen" ;;
			archive)         echo "zip unzip xz p7zip p7zip-plugins tar bzip2" ;;
			build)           echo "gcc gcc-c++ make autoconf automake libtool" ;;
			base)            echo "wget curl ca-certificates net-tools procps-ng psmisc which rsync lsof cronie expect openssl jq git" ;;
			*)               echo "${n}" ;;
		esac
	else
		case "${n}" in
			apache)          echo "apache2 apache2-utils libapache2-mod-fcgid" ;;
			nginx)           echo "nginx" ;;
			mariadb-server)  echo "mariadb-server" ;;
			mariadb-client)  echo "mariadb-client" ;;
			bind)            echo "bind9 bind9-utils bind9-dnsutils" ;;
			pure-ftpd)       echo "pure-ftpd" ;;
			postfix)         echo "postfix postfix-mysql" ;;
			dovecot)         echo "dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved" ;;
			opendkim)        echo "opendkim opendkim-tools" ;;
			spamassassin)    echo "spamassassin spamc" ;;
			clamav)          echo "clamav clamav-daemon clamav-freshclam" ;;
			cron)            echo "cron anacron" ;;
			quota)           echo "quota" ;;
			certbot)         echo "certbot" ;;
			fail2ban)        echo "fail2ban" ;;
			webalizer)       echo "webalizer" ;;
			awstats)         echo "awstats" ;;
			sudo)            echo "sudo" ;;
			pwgen)           echo "pwgen" ;;
			archive)         echo "zip unzip xz-utils p7zip-full tar bzip2" ;;
			build)           echo "build-essential autoconf automake libtool" ;;
			base)            echo "wget curl ca-certificates net-tools procps psmisc debianutils rsync lsof cron expect openssl jq git lsb-release gnupg" ;;
			*)               echo "${n}" ;;
		esac
	fi
}

# ---------------------------------------------------------------------------
# Package manager wrappers
# ---------------------------------------------------------------------------

_pkg_apt_env() {
	export DEBIAN_FRONTEND=noninteractive
	export NEEDRESTART_MODE=a
}

pkg_refresh() {
	if os_is_el ; then
		${OS_PKG} -q makecache -y >/dev/null 2>&1
	else
		_pkg_apt_env
		apt-get -q update >/dev/null 2>&1
	fi
}

# pkg_install <real-pkg>...   (silently skips names the repos do not provide)
pkg_install() {
	[ "$#" -eq 0 ] && return 0

	if os_is_el ; then
		# strict=0: skip names the enabled repositories do not provide
		${OS_PKG} -y install --setopt=strict=0 --skip-broken "$@"
	else
		_pkg_apt_env
		local avail=() p
		for p in "$@" ; do
			# a real installation candidate, not only a name referred to by other packages
			if [ -n "$(apt-cache policy "${p}" 2>/dev/null | awk '/Candidate:/ {print $2}' | grep -v '(none)')" ] ; then
				avail+=("${p}")
			else
				echo "  - package '${p}' not available, skipped"
			fi
		done
		[ "${#avail[@]}" -gt 0 ] && apt-get -y -q \
			-o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
			install "${avail[@]}"
	fi
}

# pkg_install_logical <logical-name>...
pkg_install_logical() {
	local n list=""
	for n in "$@" ; do
		list="${list} $(pkg_map "${n}")"
	done
	# shellcheck disable=SC2086
	pkg_install ${list}
}

pkg_remove() {
	[ "$#" -eq 0 ] && return 0

	if os_is_el ; then
		${OS_PKG} -y remove "$@"
	else
		_pkg_apt_env
		apt-get -y -q purge "$@"
	fi
}

pkg_upgrade() {
	if os_is_el ; then
		${OS_PKG} -y upgrade "$@"
	else
		_pkg_apt_env
		apt-get -q update >/dev/null 2>&1
		if [ "$#" -eq 0 ] ; then
			apt-get -y -q -o Dpkg::Options::=--force-confold upgrade
		else
			apt-get -y -q -o Dpkg::Options::=--force-confold install --only-upgrade "$@"
		fi
	fi
}

# pkg_installed <real-pkg>  -> exit 0 if installed
pkg_installed() {
	if os_is_el ; then
		rpm -q "$1" >/dev/null 2>&1
	else
		[ "$(dpkg-query -W -f='${Status}' "$1" 2>/dev/null)" = "install ok installed" ]
	fi
}

# pkg_available <real-pkg>  -> exit 0 if the repositories provide it
pkg_available() {
	if os_is_el ; then
		${OS_PKG} -q list available "$1" >/dev/null 2>&1 || pkg_installed "$1"
	else
		[ -n "$(apt-cache policy "$1" 2>/dev/null | awk '/Candidate:/ {print $2}' | grep -v '(none)')" ]
	fi
}

# pkg_version <real-pkg>  -> installed version (empty when not installed)
pkg_version() {
	if os_is_el ; then
		rpm -q --qf '%{VERSION}' "$1" 2>/dev/null | grep -v 'not installed'
	else
		dpkg-query -W -f='${Version}' "$1" 2>/dev/null
	fi
}

pkg_clean() {
	if os_is_el ; then
		${OS_PKG} clean all >/dev/null 2>&1
	else
		apt-get clean >/dev/null 2>&1
	fi
}

# ---------------------------------------------------------------------------
# Repositories
# ---------------------------------------------------------------------------

# Enables the extra repositories KloxoNext relies on.
#  EL:     EPEL + CRB + Remi (for parallel PHP versions)
#  Ubuntu: ondrej/php PPA (for parallel PHP versions)
os_setup_repos() {
	if os_is_el ; then
		pkg_install dnf-plugins-core epel-release
		dnf config-manager --set-enabled crb >/dev/null 2>&1

		if ! pkg_installed remi-release ; then
			pkg_install "https://rpms.remirepo.net/enterprise/remi-release-${OS_MAJOR}.rpm"
		fi

		dnf config-manager --set-enabled remi-safe >/dev/null 2>&1
	else
		_pkg_apt_env
		pkg_install software-properties-common ca-certificates gnupg lsb-release

		if ! ls /etc/apt/sources.list.d/ 2>/dev/null | grep -q 'ondrej' ; then
			add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || \
				echo "  - ondrej/php PPA not available for this release, using distribution PHP only"
		fi

		apt-get -q update >/dev/null 2>&1
	fi
}

# ---------------------------------------------------------------------------
# Services (systemd only - both targets are systemd based)
# ---------------------------------------------------------------------------

# svc_name <logical-service> -> real unit name
svc_name() {
	if os_is_el ; then
		case "$1" in
			apache)   echo "httpd" ;;
			cron)     echo "crond" ;;
			named)    echo "named" ;;
			clamd)    echo "clamd@scan" ;;
			*)        echo "$1" ;;
		esac
	else
		case "$1" in
			apache)   echo "apache2" ;;
			cron)     echo "cron" ;;
			named)    echo "named" ;;
			clamd)    echo "clamav-daemon" ;;
			*)        echo "$1" ;;
		esac
	fi
}

svc_enable()  { systemctl enable  "$(svc_name "$1")" >/dev/null 2>&1; }
svc_disable() { systemctl disable "$(svc_name "$1")" >/dev/null 2>&1; }
svc_start()   { systemctl start   "$(svc_name "$1")"; }
svc_stop()    { systemctl stop    "$(svc_name "$1")" >/dev/null 2>&1; }
svc_restart() { systemctl restart "$(svc_name "$1")"; }
svc_reload()  { systemctl reload-or-restart "$(svc_name "$1")"; }
svc_active()  { systemctl is-active --quiet "$(svc_name "$1")"; }

# ---------------------------------------------------------------------------
# Distribution specific paths and accounts
# ---------------------------------------------------------------------------

os_paths() {
	if os_is_el ; then
		OS_APACHE_CONF_DIR="/etc/httpd"
		OS_APACHE_USER="apache"
		OS_NAMED_USER="named"
		OS_NAMED_CONF="/etc/named.conf"
		OS_SYSTEMD_DIR="/usr/lib/systemd/system"
		OS_CA_BUNDLE="/etc/pki/tls/certs/ca-bundle.crt"
		OS_SYSCONFIG_DIR="/etc/sysconfig"
	else
		OS_APACHE_CONF_DIR="/etc/apache2"
		# KloxoNext keeps the historical 'apache' account on every OS, see os_compat_accounts
		OS_APACHE_USER="apache"
		OS_NAMED_USER="bind"
		OS_NAMED_CONF="/etc/bind/named.conf"
		OS_SYSTEMD_DIR="/lib/systemd/system"
		OS_CA_BUNDLE="/etc/ssl/certs/ca-certificates.crt"
		OS_SYSCONFIG_DIR="/etc/default"
	fi

	export OS_APACHE_CONF_DIR OS_APACHE_USER OS_NAMED_USER OS_NAMED_CONF \
		OS_SYSTEMD_DIR OS_CA_BUNDLE OS_SYSCONFIG_DIR
}

# Kloxo code chowns many paths to 'apache', 'lxlabs', 'nouser' and 'vpopmail'.
# Make sure these accounts exist on every distribution.
os_compat_accounts() {
	getent group nogroup  >/dev/null || groupadd -r nogroup
	getent passwd nouser  >/dev/null || useradd -r -M -g nogroup -s /sbin/nologin nouser
	getent group lxlabs   >/dev/null || groupadd -r lxlabs
	getent passwd lxlabs  >/dev/null || useradd -r -M -d /home/lxlabs -g lxlabs -s /sbin/nologin lxlabs
	getent group apache   >/dev/null || groupadd -r apache
	getent passwd apache  >/dev/null || useradd -r -M -d /var/www -g apache -s /sbin/nologin apache
	getent group vmail    >/dev/null || groupadd -g 5000 vmail
	getent passwd vmail   >/dev/null || useradd -u 5000 -g vmail -d /home/vmail -m -s /sbin/nologin vmail

	if os_is_debian ; then
		# web servers from Ubuntu run as www-data; let them share the apache group
		usermod -a -G apache www-data >/dev/null 2>&1
		[ -x /usr/sbin/nologin ] && [ ! -e /sbin/nologin ] && ln -s /usr/sbin/nologin /sbin/nologin
	fi
}

# Random password without depending on mkpasswd/expect (differs across distros)
os_random_password() {
	local len="${1:-16}"
	openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${len}"
}

os_detect
os_paths
