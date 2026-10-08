<?php 

// KloxoNext - list/apply package updates through dnf (EL) or apt (Ubuntu)
class package__yum extends lxDriverClass {

static function getYumCommand()
{
	if (OsPlatform::isDebian()) {
		return 'apt-get -qq update >/dev/null 2>&1; apt list --upgradable 2>/dev/null';
	}

	return 'dnf -q check-update';
}

static function getPackages($nocache = false)
{
	$cmd = self::getYumCommand();
	$file = fix_nname_to_be_variable($cmd);
	$file = "__path_program_root/cache/$file";

	if ($nocache) {
		$val = shell_exec($cmd);
	} else {
		$val = get_with_cache($cmd, $file);
	}

	$list = explode("\n", (string)$val);

	$res = null;

	foreach ($list as $l) {
		$l = trim(trimSpaces($l));

		if (!$l) {
			continue;
		}

		if (OsPlatform::isDebian()) {
			// name/noble-updates 1.2.3-1ubuntu1 amd64 [upgradable from: 1.2.2-1]
			if (!preg_match('#^([^/\s]+)/(\S+)\s+(\S+)#', $l, $m)) {
				continue;
			}

			$out['nname'] = $m[1];
			$out['update_version'] = $m[3];
			$out['repo'] = $m[2];
		} else {
			// name.arch version repo
			$v = explode(" ", $l);

			if (lx_count($v) < 3 || strpos($v[0], '.') === false) {
				continue;
			}

			$out['nname'] = $v[0];
			$out['update_version'] = $v[1];
			$out['repo'] = $v[2];
		}

		$out['kloxo_status'] = (strpos($out['repo'], 'kloxo') !== false) ? 'on' : 'off';
		$res[] = $out;
	}

	return $res;
}

static function doUpdate($list)
{
	$cmd = self::getYumCommand();
	$file = fix_nname_to_be_variable($cmd);
	$file = "__path_program_root/cache/$file";

	// strip '.arch' suffix used by dnf listings
	$names = array_map(function ($n) { return preg_replace('/\.(x86_64|aarch64|noarch|i686)$/', '', $n); }, $list);

	OsPlatform::upgrade($names);
	lunlink($file);
}

}
