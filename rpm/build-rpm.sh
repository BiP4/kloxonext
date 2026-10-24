#!/bin/bash
#
#    KloxoNext - build the RPM:  bash rpm/build-rpm.sh [--release=N]
#
#    The sources are the git tree (committed files plus uncommitted changes of tracked
#    files); the version comes from kloxo/bin/kloxoversion ("9.0.0-1").
#    rpmbuild runs here when installed, otherwise in an almalinux:9 container (docker).
#    Result: rpm/RPMS/noarch/kloxonext-<version>-<release>.el9.noarch.rpm
#

set -e -o pipefail

top="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${top}"

ver="$(cut -d- -f1 kloxo/bin/kloxoversion | tr -d '[:space:]')"
rel="$(cut -s -d- -f2 kloxo/bin/kloxoversion | tr -d '[:space:]')"
rel="${rel:-1}"

for a in "$@" ; do
	case "${a}" in
		--release=*) rel="${a#*=}" ;;
	esac
done

out="${top}/rpm"
mkdir -p "${out}/SOURCES" "${out}/RPMS"

# tracked files as they are now (git stash create: the working tree, without touching it)
tree="$(git stash create 2>/dev/null || true)"
tree="${tree:-HEAD}"
git archive --format=tar.gz --prefix="kloxonext-${ver}/" -o "${out}/SOURCES/kloxonext-${ver}.tar.gz" "${tree}"

echo "- sources: rpm/SOURCES/kloxonext-${ver}.tar.gz (${tree})"

build='rpmbuild -bb --define "_topdir /tmp/knrpm" --define "kn_version '"${ver}"'" --define "kn_release '"${rel}"'" /tmp/knrpm/SPECS/kloxonext.spec'
prep='mkdir -p /tmp/knrpm/SOURCES /tmp/knrpm/SPECS && cp SRC/rpm/SOURCES/kloxonext-'"${ver}"'.tar.gz /tmp/knrpm/SOURCES/ && cp SRC/rpm/kloxonext.spec /tmp/knrpm/SPECS/'
copy='cp /tmp/knrpm/RPMS/noarch/*.rpm SRC/rpm/RPMS/'

if command -v rpmbuild >/dev/null 2>&1 ; then
	bash -c "${prep//SRC/${top}} && ${build} && ${copy//SRC/${top}}"
else
	MSYS_NO_PATHCONV=1 docker run --rm -v "${top}:/src" almalinux:9 bash -c \
		"dnf -y -q install rpm-build >/dev/null && ${prep//SRC//src} && ${build} && ${copy//SRC//src}"
fi

ls -l "${out}/RPMS/"kloxonext-"${ver}"-"${rel}".*.rpm
