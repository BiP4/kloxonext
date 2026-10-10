#!/bin/bash
# KloxoNext - phase 1 smoke test, run inside a fresh AlmaLinux/Ubuntu container:
#   docker run --rm -v "$PWD":/src <image> bash /src/tests/container-phase1.sh
set -u
export KLOXO_TEST=1
pass=0; fail=0
ok()  { echo "  [PASS] $*"; pass=$((pass+1)); }
bad() { echo "  [FAIL] $*"; fail=$((fail+1)); }

if command -v apt-get >/dev/null ; then
	export DEBIAN_FRONTEND=noninteractive
	apt-get -qq update >/dev/null && apt-get -qq install -y curl ca-certificates openssl jq git rsync procps >/dev/null
else
	dnf -q -y install curl openssl jq git rsync procps-ng >/dev/null 2>&1 || dnf -q -y install --allowerasing curl openssl jq git rsync procps-ng >/dev/null
fi

mkdir -p /usr/local/lxlabs
cp -a /src/kloxo /usr/local/lxlabs/kloxo
ln -sfn /usr/local/lxlabs/kloxo/pscript /script
echo kloxo > /script/programname

. /script/os.inc
. /script/php-native.inc

echo "== OS: ${OS_ID} ${OS_VERSION} family=${OS_FAMILY} pkg=${OS_PKG}"
os_is_supported && ok "supported OS" || bad "os_is_supported"

# dash compatibility: scripts must re-exec under bash
if [[ -x /bin/dash ]] ; then
	out="$(dash /script/php-branch-installer --help 2>&1)"
	echo "${out}" | grep -q "format:" && ok "dash re-exec guard" || bad "dash re-exec guard: ${out}"
fi

os_compat_accounts
for u in apache lxlabs nouser vmail ; do getent passwd $u >/dev/null && ok "account $u" || bad "account $u" ; done

echo "== repos"
os_setup_repos >/dev/null 2>&1
b="$(php_available_branches)"
echo "   available PHP branches: ${b}"
PB="$(php_panel_branch)"; [[ "${PB}" -ge 84 ]] && [[ " ${b} " == *" ${PB} "* ]] && ok "panel PHP branch ${PB} available" || bad "no PHP >= 8.4 available"

echo "== phpm-installer php${PB}s / php${PB}m"
bash /script/phpm-installer php${PB}s -y > /tmp/p84s.log 2>&1 || { tail -20 /tmp/p84s.log; }
bash /script/phpm-installer php${PB}m > /tmp/p84m.log 2>&1 || { tail -20 /tmp/p84m.log; }

for base in php${PB}s php${PB}m ; do
	[[ -x /opt/${base}/usr/bin/php ]] && ok "${base} php binary" || bad "${base} php binary"
	[[ -x /opt/${base}/usr/sbin/php-fpm ]] && ok "${base} php-fpm binary" || bad "${base} php-fpm binary"
	v="$(/opt/${base}/custom/php-cli.sh -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>&1)"
	[ "${v}" == "$(php_dotted ${PB})" ] && ok "${base} cli wrapper runs PHP ${v}" || bad "${base} cli wrapper: ${v}"
	mods="$(/opt/${base}/custom/php-cli.sh -m 2>&1)"
	for m in mysqli mbstring curl openssl; do
		echo "${mods}" | grep -qix "${m}" && ok "${base} module ${m}" || bad "${base} module ${m}"
	done
done

php_set_branch ${PB}
[ "$(cat /usr/local/lxlabs/kloxo/init/php_branch_active)" == "php${PB}m" ] && ok "php branch -> php${PB}m" || bad "php branch"
command -v php >/dev/null && ok "/usr/bin/php present ($(php -r 'echo PHP_VERSION;'))" || bad "/usr/bin/php missing"

echo "== panel library under PHP 8.4"
ln -sf /opt/php${PB}s/custom/php-cli.sh /usr/bin/lxphp.exe
cd /usr/local/lxlabs/kloxo/httpdocs
out="$(lxphp.exe -d display_errors=stderr -r '
	include "lib/html/include.php";
	echo "OS=", OsPlatform::prettyName(), " family=", OsPlatform::family(), "\n";
	echo "map httpd=", OsPlatform::mapPackage("httpd"), " php84-php-gd=", OsPlatform::mapPackage("php84-php-gd"), "\n";
	echo "installed openssl=", (OsPlatform::isInstalled("openssl") ? "yes" : "no"), " ver=", getRpmVersion("openssl"), "\n";
	echo "service sshd-or-cron exists=", (isServiceExists("cron") || isServiceExists("crond") ? "yes" : "no"), "\n";
	echo "php branch=", getRpmBranchInstalled("php"), "\n";
' 2>&1)"
echo "${out}" | sed 's/^/   /'
echo "${out}" | grep -q "^OS=" && ok "include.php loads on PHP 8.4" || bad "include.php failed"
echo "${out}" | grep -q "php branch=php${PB}" && ok "getRpmBranchInstalled('php')" || bad "getRpmBranchInstalled"
echo "${out}" | grep -qi "fatal\|parse error" && bad "fatal errors in output" || ok "no fatal errors"

echo "== nginx panel config"
pkg_install nginx >/dev/null 2>&1
mkdir -p /usr/local/lxlabs/kloxo/init/tmp /usr/local/lxlabs/kloxo/log /usr/local/lxlabs/kloxo/etc
openssl req -x509 -newkey rsa:2048 -nodes -subj /CN=test -keyout /tmp/k.pem -out /tmp/c.pem -days 1 2>/dev/null
cat /tmp/k.pem /tmp/c.pem > /usr/local/lxlabs/kloxo/etc/program.pem
ver="$(nginx -v 2>&1 | sed 's#.*nginx/##')"
if printf '%s\n%s\n' 1.25.1 "${ver}" | sort -V -C ; then h2l=""; h2d="http2 on;"; else h2l="http2 "; h2d=""; fi
sed -e 's/__nonssl_port__/7778/g' -e 's/__ssl_port__/7777/g' -e "s/__panel_php__/php${PB}s/" \
	-e "s/__http2_listen__/${h2l}/g" -e "s/__http2_directive__/${h2d}/" \
	/usr/local/lxlabs/kloxo/init/kloxo-nginx.conf.base > /usr/local/lxlabs/kloxo/init/kloxo-nginx.conf
nginx -t -c /usr/local/lxlabs/kloxo/init/kloxo-nginx.conf > /tmp/nginx-t.log 2>&1 && ok "nginx -t (nginx ${ver})" || { bad "nginx -t"; cat /tmp/nginx-t.log; }

echo
echo "RESULT ${OS_ID} ${OS_VERSION}: ${pass} passed, ${fail} failed"
[[ "${fail}" -eq 0 ]]
