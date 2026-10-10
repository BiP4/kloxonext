<?php
// KloxoNext - the placeholder page of existing domains gets the new design
//
//   update-default-pages.php           once per server (flag etc/flag/default-pages-v2.flg)
//   update-default-pages.php --force   again
//
// Only an index.html that is still the untouched old default page (KloxoNG "Default
// Page for ...") is replaced; a site the owner uploaded is never changed. The file is
// written as the owner of the domain (lxuser_put_contents).

include_once "lib/html/include.php";

initProgram('admin');

$flag = "../etc/flag/default-pages-v2.flg";
$force = in_array('--force', (array)$argv, true);

if (!$force && file_exists($flag)) {
	exit(0);
}

$tpl = "../file/skeleton/index.html";

if (!is_file($tpl)) {
	print("- no template {$tpl}\n");
	exit(1);
}

$template = file_get_contents($tpl);
$done = 0;

// images of the KloxoNG skeleton (md5)
$old_images = array(
	'logo.png'        => 'eb59c3a7f21f28caddd88396523069d0',
	'kloxong.png'     => 'b44266bfe1c3ce77d44e4caa0287a508',
	'kloxong_big.png' => '5e2a0e5a28a64f113615e0d9d37f1be2',
	'abstract.jpg'    => '0d1c791310019dff059bdfaaf3d7e462',
);

$sq = new Sqlite(null, 'web');

foreach ((array)$sq->getTable(array('nname')) as $r) {
	try {
		$w = new Web(null, 'localhost', $r['nname']);
		$w->get();

		if (!$w->username || !$w->customer_name) {
			continue;
		}

		$file = $w->getFullDocRoot() . "/index.html";

		if (!is_file($file) || is_link($file)) {
			continue;
		}

		$old = (string)file_get_contents($file, false, null, 0, 65536);

		if (strpos($old, 'Default Page for') === false || strpos($old, 'Kloxo') === false) {
			continue;
		}

		$c = new Sqlite(null, 'client');
		$cr = $c->getRows('nname', $w->customer_name);
		$email = ($cr && !empty($cr[0]['contactemail'])) ? $cr[0]['contactemail'] : '';

		$page = str_replace(
			array('<%domainname%>', '<%clientname%>', '<%contactemail%>'),
			array($w->nname, $w->customer_name, $email),
			$template);

		lxuser_put_contents($w->username, $file, $page);

		$dir = dirname($file);

		if (!is_dir("{$dir}/images")) {
			lxuser_mkdir($w->username, "{$dir}/images");
		}

		// the old skeleton's images, only exact copies (a logo the owner put there stays)
		foreach ($old_images as $img => $md5) {
			$f = "{$dir}/images/{$img}";

			if (is_file($f) && !is_link($f) && md5_file($f) === $md5) {
				@unlink($f);
			}
		}

		$done++;
	} catch (Exception $e) {
		print("- {$r['nname']}: {$e->getMessage()}\n");
	}
}

@touch($flag);

print("- new default page on {$done} domain(s)\n");
