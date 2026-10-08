#!/bin/bash
#
#    KloxoNext - native multi-PHP support
#
#    Historically Kloxo extracted PHP rpms into /opt/phpXYm (domains) and
#    /opt/phpXYs (panel). Every other part of Kloxo (web drivers, php-fpm
#    units, the per-domain 'PHP Selected' option, phpini...) only relies on
#    that layout:
#
#        /opt/phpXY[m|s]/usr/bin/php
#        /opt/phpXY[m|s]/usr/bin/php-cgi
#        /opt/phpXY[m|s]/usr/sbin/php-fpm
#        /opt/phpXY[m|s]/usr/lib64/php/modules   (extension_dir)
#        /opt/phpXY[m|s]/etc/php.d               (PHP_INI_SCAN_DIR)
#        /opt/phpXY[m|s]/custom/                 (managed by phpm-config-setup)
#
#    KloxoNext installs PHP from the distribution package manager instead
#    (Remi SCL 'phpXY-php-*' on EL, 'phpX.Y-*' on Ubuntu) and builds that same
#    layout with symlinks, so the rest of the panel keeps working unchanged.
#
#    Requires: . /script/os.inc
#

[ -n "${KLOXO_PHP_NATIVE_INC_LOADED:-}" ] && return 0
KLOXO_PHP_NATIVE_INC_LOADED=1

[ -z "${KLOXO_OS_INC_LOADED:-}" ] && . "$(dirname "${BASH_SOURCE[0]}")/os.inc"

# PHP branches KloxoNext knows about, oldest first.
PHP_KNOWN_BRANCHES="74 80 81 82 83 84 85"

# Minimum branch used by the panel itself (see php_panel_branch).
PHP_PANEL_MIN_BRANCH="84"
PHP_PANEL_BRANCH="${PHP_PANEL_MIN_BRANCH}"

# Extensions installed for every branch (logical names).
PHP_DEFAULT_EXTS="cli fpm cgi common mysqlnd pdo gd mbstring xml intl zip opcache soap bcmath curl ldap process sodium gmp imap apcu redis imagick sqlite3 tidy"

# "84" -> "8.4"
php_dotted() { echo "${1:0:1}.${1:1}"; }

# Real package names for one extension of one branch
_php_pkg_for_ext() {
	local xy="$1" ext="$2" d
	d="$(php_dotted "${xy}")"

	if os_is_el ; then
		case "${ext}" in
			cli|fpm|common|mysqlnd|pdo|gd|mbstring|xml|intl|opcache|soap|bcmath|ldap|process|sodium|gmp|tidy)
				echo "php${xy}-php-${ext}" ;;
			cgi)     echo "" ;;                       # php-cgi ships with -cli on Remi
			curl)    echo "" ;;                       # built into -common on Remi
			sqlite3) echo "php${xy}-php-pdo" ;;
			zip)     echo "php${xy}-php-pecl-zip" ;;
			imap)    [ "${xy}" -ge 84 ] && echo "php${xy}-php-pecl-imap" || echo "php${xy}-php-imap" ;;
			apcu)    echo "php${xy}-php-pecl-apcu" ;;
			redis)   echo "php${xy}-php-pecl-redis6" ;;
			imagick) echo "php${xy}-php-pecl-imagick-im7" ;;
			*)       echo "php${xy}-php-${ext}" ;;
		esac
	else
		case "${ext}" in
			pdo|process|sodium) echo "" ;;            # part of phpX.Y-common on Debian/Ubuntu
			mysqlnd) echo "php${d}-mysql" ;;
			*)       echo "php${d}-${ext}" ;;
		esac
	fi
}

php_native_packages() {
	local xy="$1" ext out=""
	for ext in ${PHP_DEFAULT_EXTS} ; do
		out="${out} $(_php_pkg_for_ext "${xy}" "${ext}")"
	done
	echo ${out}
}

# Sets NATIVE_PHP_BIN, NATIVE_PHP_CGI, NATIVE_PHP_FPM, NATIVE_PHP_SCAN, NATIVE_PHP_PECL
php_native_paths() {
	local xy="$1" d
	d="$(php_dotted "${xy}")"

	if os_is_el ; then
		local root="/opt/remi/php${xy}/root"
		NATIVE_PHP_BIN="${root}/usr/bin/php"
		NATIVE_PHP_CGI="${root}/usr/bin/php-cgi"
		NATIVE_PHP_FPM="${root}/usr/sbin/php-fpm"
		NATIVE_PHP_SCAN="/etc/opt/remi/php${xy}/php.d"
	else
		NATIVE_PHP_BIN="/usr/bin/php${d}"
		NATIVE_PHP_CGI="/usr/bin/php-cgi${d}"
		NATIVE_PHP_FPM="/usr/sbin/php-fpm${d}"
		NATIVE_PHP_SCAN="/etc/php/${d}/fpm/conf.d"
	fi
}

php_native_installed() {
	php_native_paths "$1"
	[ -x "${NATIVE_PHP_BIN}" ]
}

php_native_ext_dir() {
	php_native_paths "$1"
	"${NATIVE_PHP_BIN}" -n -r 'echo ini_get("extension_dir");' 2>/dev/null
}

# Branches the enabled repositories can provide
php_available_branches() {
	local xy out=""
	for xy in ${PHP_KNOWN_BRANCHES} ; do
		if os_is_el ; then
			pkg_available "php${xy}-php-cli" && out="${out} ${xy}"
		else
			pkg_available "php$(php_dotted "${xy}")-cli" && out="${out} ${xy}"
		fi
	done
	echo ${out}
}

# Highest branch installed on this server (native packages)
php_latest_installed() {
	local xy last=""
	for xy in ${PHP_KNOWN_BRANCHES} ; do
		php_native_installed "${xy}" && last="${xy}"
	done
	echo "${last}"
}

# Highest branch the repositories provide
php_latest_available() {
	local b
	b="$(php_available_branches)"
	echo "${b##* }"
}

# Branch for the panel: PHP 8.4 when the repositories have it, otherwise the
# oldest available branch newer than 8.4 (Ubuntu 26.04 ships only 8.5).
php_panel_branch() {
	local xy
	if php_native_installed "${PHP_PANEL_MIN_BRANCH}" ; then
		echo "${PHP_PANEL_MIN_BRANCH}" ; return
	fi
	for xy in $(php_available_branches) ; do
		if [ "${xy}" -ge "${PHP_PANEL_MIN_BRANCH}" ] ; then
			echo "${xy}" ; return
		fi
	done
	echo "${PHP_PANEL_MIN_BRANCH}"
}

php_native_install() {
	local xy="$1"
	echo "- Installing native PHP $(php_dotted "${xy}") packages"
	# shellcheck disable=SC2046
	pkg_install $(php_native_packages "${xy}")

	if os_is_debian ; then
		# Kloxo runs its own php-fpm masters; the distribution ones must not grab the sockets
		local d
		d="$(php_dotted "${xy}")"
		systemctl disable --now "php${d}-fpm" >/dev/null 2>&1
	else
		systemctl disable --now "php${xy}-php-fpm" >/dev/null 2>&1
	fi
}

# Build /opt/<base_name> (phpXYm or phpXYs) from the native installation.
php_build_compat_layout() {
	local base_name="$1" xy main ext_dir f
	xy="$(echo "${base_name}" | sed -E 's/^php([0-9]+)[ms]$/\1/')"
	main="/opt/${base_name}"

	php_native_paths "${xy}"

	if [ ! -x "${NATIVE_PHP_BIN}" ] ; then
		echo "- ERROR: PHP $(php_dotted "${xy}") is not installed (${NATIVE_PHP_BIN} missing)"
		return 1
	fi

	ext_dir="$(php_native_ext_dir "${xy}")"

	mkdir -p "${main}/usr/bin" "${main}/usr/sbin" "${main}/usr/lib64/php" "${main}/etc" "${main}/custom" "${main}/var/log"

	ln -snf "${NATIVE_PHP_BIN}" "${main}/usr/bin/php"
	[ -x "${NATIVE_PHP_CGI}" ] && ln -snf "${NATIVE_PHP_CGI}" "${main}/usr/bin/php-cgi"
	[ -x "${NATIVE_PHP_FPM}" ] && ln -snf "${NATIVE_PHP_FPM}" "${main}/usr/sbin/php-fpm"
	ln -snf "${ext_dir}" "${main}/usr/lib64/php/modules"
	[ -e "${main}/usr/lib" ] || ln -snf "${main}/usr/lib64" "${main}/usr/lib"

	# Private copy of the extension ini files: phpm-config-setup renames
	# them (.ini <-> .nonini) to enable/disable modules per branch.
	mkdir -p "${main}/etc/php.d"
	for f in "${NATIVE_PHP_SCAN}"/*.ini ; do
		[ -e "${f}" ] || continue
		local name
		name="$(basename "${f}")"
		# keep the admin's choice when the module was disabled/enabled before
		[ -e "${main}/etc/php.d/${name%.ini}.nonini" ] && continue
		[ -e "${main}/etc/php.d/${name%.ini}_used.ini" ] && continue
		cp -fL "${f}" "${main}/etc/php.d/${name}"
	done

	"${NATIVE_PHP_BIN}" -n -r 'echo PHP_VERSION;' > "${main}/version" 2>/dev/null
	echo "${OS_FAMILY}" > "${main}/native"
}

# The 'php' branch (default for domains without 'PHP Selected') always
# follows the newest phpXYm installed on the server.
#   /usr/local/lxlabs/kloxo/init/php-branch     -> /opt/phpXYm
#   /usr/bin/php (only when not owned by a package) -> newest php cli
php_set_branch() {
	local xy="${1:-$(php_latest_installed)}"
	[ -z "${xy}" ] && return 1

	if [ ! -d "/opt/php${xy}m" ] ; then
		sh /script/phpm-installer "php${xy}m"
	fi

	ln -snf "/opt/php${xy}m" "${KLOXO_PATH}/init/php-branch"
	ln -snf "/opt/php${xy}m/custom/php-fpm.sh" "${KLOXO_PATH}/init/php-branch-fpm"
	ln -snf "/opt/php${xy}m/custom/php-cli.sh" "${KLOXO_PATH}/init/php-branch-cli"
	ln -snf "/opt/php${xy}m/custom/php-cgi.sh" "${KLOXO_PATH}/init/php-branch-cgi"

	php_native_paths "${xy}"

	if os_is_el ; then
		# Remi SCL only ships /usr/bin/phpXY; never overwrite a file owned by a package
		local pair dst src
		for pair in "/usr/bin/php:${NATIVE_PHP_BIN}" "/usr/bin/php-cgi:${NATIVE_PHP_CGI}" ; do
			dst="${pair%%:*}"
			src="${pair#*:}"
			if [ ! -e "${dst}" ] || [ -L "${dst}" ] ; then
				ln -snf "${src}" "${dst}"
			fi
		done
	else
		# Ubuntu manages /usr/bin/php and /usr/bin/php-cgi with alternatives
		update-alternatives --set php     "${NATIVE_PHP_BIN}" >/dev/null 2>&1
		update-alternatives --set php-cgi "${NATIVE_PHP_CGI}" >/dev/null 2>&1
	fi

	echo "php${xy}m" > "${KLOXO_PATH}/init/php_branch_active"
}

# Remove the compat layout (native packages stay installed)
php_remove_compat_layout() {
	local base_name="$1"
	[ -n "${base_name}" ] && [ -d "/opt/${base_name}" ] && rm -rf "/opt/${base_name:?}"
	rm -f "/usr/bin/${base_name}-"* "/usr/sbin/${base_name}-"*
}
