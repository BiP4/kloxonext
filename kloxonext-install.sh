#!/bin/bash
#
#    KloxoNext - bootstrap installer
#
#    Supported: AlmaLinux / Rocky Linux 9.x and 10.x, Ubuntu 26.04 LTS
#
#    From a clone of the repository:
#        git clone <your-kloxonext-repo> /root/kloxonext
#        bash /root/kloxonext/kloxonext-install.sh
#
#    Or straight from a repository URL:
#        bash kloxonext-install.sh --repo=https://github.com/BiP4/kloxonext.git [--branch=main]
#
#    Every other option is passed to kloxo/install/setup.sh:
#        --php="84 85"  --admin-password=...  --install-type=slave  --yes
#

set -o pipefail

KPATH="/usr/local/lxlabs/kloxo"
REPO=""
BRANCH="main"
PASS_ARGS=()

for arg in "$@" ; do
	case "${arg}" in
		--repo=*)   REPO="${arg#*=}" ;;
		--branch=*) BRANCH="${arg#*=}" ;;
		*)          PASS_ARGS+=("${arg}") ;;
	esac
done

if [ "$(id -u)" -ne 0 ] ; then
	echo "Run as root."
	exit 1
fi

if [[ -d /var/lib/mysql/kloxo ]] && [[ -f "${KPATH}/bin/kloxoversion" ]] ; then
	echo "KloxoNext/Kloxo is already installed. Use 'sh /script/upcp' to update."
	exit 1
fi

cat <<'EOF'
 ------------------------------------------------------------------------
   KloxoNext - web hosting control panel

   The installation makes major changes to this server (web, mail, DNS,
   FTP, database services). Do not run it on a production server that
   already hosts services. Package installation can be silent for long
   periods - do not interrupt it.
 ------------------------------------------------------------------------
EOF

if [[ ! " ${PASS_ARGS[*]} " =~ " --yes " ]] && [[ -t 0 ]] ; then
	read -r -p "Continue? [y/N] " a
	[[ "${a}" =~ ^[Yy]$ ]] || exit 1
fi

# git is needed to fetch/update the code
if ! command -v git >/dev/null 2>&1 ; then
	if command -v dnf >/dev/null 2>&1 ; then
		dnf -y install git
	else
		DEBIAN_FRONTEND=noninteractive apt-get -q update && DEBIAN_FRONTEND=noninteractive apt-get -y install git
	fi
fi

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TMP=""

if [[ -n "${REPO}" ]] ; then
	TMP="$(mktemp -d)"
	git clone --depth 1 --branch "${BRANCH}" "${REPO}" "${TMP}/src" || exit 1
	SRC="${TMP}/src"
elif [[ -d "${SRC}/.git" ]] ; then
	REPO="$(git -C "${SRC}" remote get-url origin 2>/dev/null)"
	BRANCH="$(git -C "${SRC}" rev-parse --abbrev-ref HEAD 2>/dev/null)"
fi

if [[ ! -d "${SRC}/kloxo/httpdocs" ]] ; then
	echo "Cannot find the KloxoNext sources (expected ${SRC}/kloxo/httpdocs)."
	exit 1
fi

echo "- Installing KloxoNext files to ${KPATH}"
mkdir -p "${KPATH}"
cp -a "${SRC}/kloxo/." "${KPATH}/"
# sources copied from a non-POSIX filesystem may be world-writable
chmod -R go-w "${KPATH}"

rm -rf /script
ln -sf "${KPATH}/pscript" /script
echo 'kloxo' > /script/programname

mkdir -p "${KPATH}/etc/conf" "${KPATH}/log"

# Where 'sh /script/kloxonext-update' (and the panel update page) fetch new versions
if [[ -n "${REPO}" ]] ; then
	raw=""
	if [[ "${REPO}" =~ github\.com[:/]([^/]+)/([^/.]+) ]] ; then
		raw="https://raw.githubusercontent.com/${BASH_REMATCH[1]}/${BASH_REMATCH[2]}/${BRANCH}/kloxo/bin/kloxoversion"
	fi

	cat > "${KPATH}/etc/conf/update-source.conf" <<EOF
; KloxoNext update source (used by /script/kloxonext-update)
REPO_URL = "${REPO}"
BRANCH = "${BRANCH}"
VERSION_URL = "${raw}"
EOF
fi

# installed commit: the panel Update page / auto-update compare it with GitHub
if [[ -d "${SRC}/.git" ]] ; then
	git -C "${SRC}" rev-parse HEAD > "${KPATH}/etc/conf/update-commit" 2>/dev/null
fi

[[ -n "${TMP}" ]] && rm -rf "${TMP}"

getent group lxlabs >/dev/null || groupadd -r lxlabs
getent passwd lxlabs >/dev/null || useradd -r -M -d /home/lxlabs -g lxlabs -s /sbin/nologin lxlabs

bash "${KPATH}/install/setup.sh" "${PASS_ARGS[@]}" 2>&1 | tee "${KPATH}/install/install.log"
