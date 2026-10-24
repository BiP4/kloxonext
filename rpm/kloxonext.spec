#    KloxoNext - RPM package (AlmaLinux / Rocky Linux 9 and 10)
#
#    Replaces the Kloxo Next Generation package 'kloxo' (8.x): installing it on a KloxoNG
#    server starts the migration (sh /script/kloxong-migrate) once dnf has finished:
#    PHP, mail (qmail-toaster -> Postfix + Dovecot), configurations, third-party apps.
#    On a KloxoNext server it is an update (cleanup); on a new server it installs.
#
#    Build: bash rpm/build-rpm.sh   (rpm/RPMS/noarch/kloxonext-<version>.noarch.rpm)

%global kloxopath /usr/local/lxlabs/kloxo

# shell, PHP and Python files of the panel must not turn into package dependencies
%global __requires_exclude_from ^%{kloxopath}/.*$
%global __provides_exclude_from ^%{kloxopath}/.*$
%global __brp_python_bytecompile %{nil}
%global __brp_mangle_shebangs %{nil}
%global debug_package %{nil}

Name:           kloxonext
Version:        %{kn_version}
Release:        %{kn_release}%{?dist}
Summary:        KloxoNext web hosting control panel
License:        AGPLv3
URL:            https://github.com/BiP4/kloxonext
Source0:        kloxonext-%{version}.tar.gz
BuildArch:      noarch

# the KloxoNG / Kloxo-MR panel package
Obsoletes:      kloxo < 9
Obsoletes:      kloxomr < 9
Obsoletes:      kloxomr7 < 9
Provides:       kloxo = %{version}-%{release}
# their /etc/yum.repos.d/kloxo.repo (KloxoNG copr, qmail, nginx.org ...): not used any more
Obsoletes:      kloxo-release < 9
Obsoletes:      kloxong-release < 9
Obsoletes:      kloxong-testing < 9

Requires:       bash
Requires:       coreutils
Requires:       findutils
Requires:       rsync
Requires:       systemd
Requires:       tar
Requires:       which
Requires(pre):  shadow-utils

%description
KloxoNext is a web hosting control panel based on Kloxo Next Generation, rebuilt for
current distributions: PHP 8 panel, native PHP packages for the websites, nginx / Apache,
BIND, Postfix + Dovecot (SpamAssassin / Bogofilter, OpenDKIM), Pure-FTPd, MariaDB.

Installed over Kloxo Next Generation it migrates the server (sh /script/kloxong-migrate).

%prep
%setup -q -n kloxonext-%{version}

%build

%install
%{__mkdir_p} %{buildroot}%{kloxopath}
%{__cp} -a kloxo/. %{buildroot}%{kloxopath}/
# runtime folders: created by the panel, never owned by the package
%{__rm} -rf %{buildroot}%{kloxopath}/log %{buildroot}%{kloxopath}/session %{buildroot}%{kloxopath}/pid
%{__ln_s} -f %{kloxopath}/pscript %{buildroot}/script
%{__mkdir_p} %{buildroot}%{kloxopath}/etc/conf
echo '%{version}-%{kn_release}' > %{buildroot}%{kloxopath}/etc/conf/package-version

%pretrans -p <lua>
-- /script was a directory on very old Kloxo versions: the package installs a symlink
local st = posix.stat("/script")
if st and st.type == "directory" then
  os.rename("/script", "/script.rpmold." .. os.time())
end

%pre
. /etc/os-release 2>/dev/null
major="${VERSION_ID%%%%.*}"

if [ -n "${major}" ] && [ "${major}" -lt 9 ] 2>/dev/null ; then
	echo "*** KloxoNext needs AlmaLinux / Rocky Linux 9 or 10 (this is ${PRETTY_NAME})."
	echo "*** Upgrade the system first (ELevate: https://wiki.almalinux.org/elevate/),"
	echo "*** then install this package again. Nothing was changed."
	exit 1
fi

# what was installed before: decides migration / update / install in %%post
mkdir -p /var/lib/kloxonext
if rpm -q kloxo >/dev/null 2>&1 ; then
	rpm -q --qf '%%{VERSION}\n' kloxo | head -1 > /var/lib/kloxonext/previous-kloxo
fi

getent group lxlabs >/dev/null || groupadd -r lxlabs
getent passwd lxlabs >/dev/null || useradd -r -M -d /home/lxlabs -g lxlabs -s /sbin/nologin \
	-c "Kloxo Website Control Panel" lxlabs
exit 0

%post
echo 'kloxo' > /script/programname
mkdir -p %{kloxopath}/log %{kloxopath}/session %{kloxopath}/pid %{kloxopath}/etc/flag
chmod 777 %{kloxopath}/session

prev="$(cat /var/lib/kloxonext/previous-kloxo 2>/dev/null)"

if [ -n "${prev}" ] && [ "${prev%%%%.*}" -lt 9 ] 2>/dev/null && [ ! -f %{kloxopath}/etc/flag/kloxonext-migrated.flg ] ; then
	mode=migrate
elif [ -d /var/lib/mysql/kloxo ] ; then
	mode=update
else
	mode=install
fi

# the work needs dnf (PHP, Postfix, Dovecot ...): it runs as its own service once this
# transaction has ended; KLOXONEXT_NO_AUTO=1 leaves it to the administrator
if [ -n "${KLOXONEXT_NO_AUTO}" ] || ! command -v systemd-run >/dev/null 2>&1 || [ ! -d /run/systemd/system ] ; then
	echo
	echo "  KloxoNext installed. Finish with:  sh /script/kloxonext-rpm-setup ${mode}"
	echo
else
	systemctl reset-failed kloxonext-setup.service >/dev/null 2>&1
	systemd-run --unit=kloxonext-setup --description="KloxoNext ${mode}" --no-block \
		--property=Type=oneshot --property=TimeoutStartSec=infinity \
		/bin/bash %{kloxopath}/pscript/kloxonext-rpm-setup "${mode}" >/dev/null 2>&1

	echo
	echo "  KloxoNext: ${mode} started in the background (it waits for dnf to finish)."
	echo "  Follow it:   journalctl -fu kloxonext-setup"
	echo "  Log:         /var/log/kloxonext-setup.log"
	echo
fi
exit 0

%files
%defattr(-,root,root,-)
%license LICENSE.txt
%{kloxopath}
/script

%changelog
* Sat Oct 24 2026 KloxoNext <noreply@github.com> - 9.0.0-1
- First KloxoNext package: replaces Kloxo Next Generation 8.x (migration to KloxoNext)
