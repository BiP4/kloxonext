<?php

// KloxoNext 'nexus' skin - object tabs (Home, Domains, Mail Accounts, ...)

function print_tab_block_start($alist)
{
	global $gbl, $sgbl, $login, $ghtml;

	if (!$alist) {
		return;
	}

	$nalist = array();

	foreach ($alist as $k => $a) {
		$nalist[] = $ghtml->getFullUrl($a);
	}

	if ($login->getSpecialObject('sp_specialplay')->isOn('enable_ajax')) {
		$ghtml->print_dialog($nalist, $gbl->__c_object);
	}
?>
	<nav class="kn-tabs" aria-label="Sections">
<?php
	foreach ($nalist as $k => $a) {
		print_tab_button($k, $a, null);
	}
?>
	</nav>
<?php
}

function print_tab_button($key, $url, $list)
{
	global $gbl, $sgbl, $login, $ghtml;

	$psuedourl = null;
	$target = null;
	$buttonpath = '';

	$ghtml->resolve_int_ext($url, $psuedourl, $target);

	$descr = $ghtml->getActionDetails($url, $psuedourl, $buttonpath, $path, $post, $file, $name, $image, $__t_identity);

	$check = $ghtml->compare_urls("display.php?{$ghtml->get_get_from_current_post(null)}", $url);

	$help = htmlspecialchars(strip_tags((string)$descr['help']), ENT_QUOTES, 'UTF-8');
	$label = $descr[2];
	$current = $check ? ' aria-current="page"' : '';
?>
		<a class="kn-tab" <?= $target ?> href="<?= $url ?>" title="<?= $help ?>"<?= $current ?>><?= $label ?></a>
<?php
	return $check;
}
