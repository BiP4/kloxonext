<?php

/**
 * KloxoNext 'nexus' skin - sidebar navigation.
 *
 * The menu content (and every permission rule deciding what a given admin,
 * reseller, customer or mail user may see) lives in the simplicity skin
 * menu.php. Instead of duplicating 1100+ lines, we render it into a buffer
 * and convert its mega-menu markup into a structured list of sections.
 */

function kn_nav_icon($name)
{
	// 24x24 stroke icons (MIT-licensed shapes in the style of Lucide)
	$p = array(
		'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		'admin'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18.5 14.5A6.5 6.5 0 0 1 21.5 20"/>',
		'resource' => '<path d="M4 4h16v6H4z"/><path d="M4 14h16v6H4z"/><path d="M8 7h.01M8 17h.01"/>',
		'advanced' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M1 14h6M9 8h6M17 16h6"/>',
		'server'   => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'security' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'task'     => '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 2v4M16 2v4M8 11h8M8 15h5"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7v.5"/><path d="M12 17h.01"/>',
		'logout'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17-5-5 5-5"/><path d="M5 12h12"/>',
		'dot'      => '<circle cx="12" cy="12" r="3"/>',
	);

	$d = isset($p[$name]) ? $p[$name] : $p['dot'];

	return '<svg class="kn-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
		. 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
}

/**
 * @return array list of sections: ['title', 'icon', 'href'|null, 'groups' => [['label', 'links' => [['text','href']]]]]
 */
function kn_nav_sections($menuhtml, $login)
{
	$icons = array(
		'home' => 'home', 'administration' => 'admin', 'resource' => 'resource', 'advanced' => 'advanced',
		'server' => 'server', 'security' => 'security', 'webmailanddb' => 'mail', 'task' => 'task',
	);

	$titleToIcon = array();

	foreach ($icons as $kw => $icon) {
		$titleToIcon[mb_strtolower(trim($login->getKeywordUc($kw)))] = $icon;
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="utf-8"?><div>' . $menuhtml . '</div>');
	libxml_clear_errors();

	$xp = new DOMXPath($doc);
	$sections = array();

	foreach ($xp->query("//ul[contains(@class,'menuTemplate2')]/li") as $li) {
		$top = $xp->query("./a", $li)->item(0);

		if (!$top) {
			continue;
		}

		$title = trim($top->textContent);
		$href = $top->getAttribute('href');

		// help / logout live in the top bar
		if ($title === '' || stripos($href, 'logout') !== false || stripos($top->getAttribute('onclick'), 'infomsg') !== false) {
			continue;
		}

		$section = array(
			'title' => $title,
			'icon' => isset($titleToIcon[mb_strtolower($title)]) ? $titleToIcon[mb_strtolower($title)] : 'dot',
			'href' => (strpos($href, 'javascript') === 0) ? null : $href,
			'groups' => array(),
		);

		$group = array('label' => '', 'links' => array());
		$seen = array();

		// walk the dropdown in document order: <b> starts a group, <a> adds a link
		foreach ($xp->query(".//div[contains(@class,'drop')]//b | .//div[contains(@class,'drop')]//a", $li) as $n) {
			if ($n->nodeName === 'b') {
				if (!empty($group['links'])) {
					$section['groups'][] = $group;
				}

				$group = array('label' => trim($n->textContent), 'links' => array());

				continue;
			}

			$h = $n->getAttribute('href');
			$t = trim(preg_replace('/\s+/u', ' ', $n->textContent));

			if ($t === '' || $h === '' || strpos($h, 'javascript') === 0 || isset($seen[$h . '|' . $t])) {
				continue;
			}

			$seen[$h . '|' . $t] = true;
			$group['links'][] = array('text' => $t, 'href' => $h, 'target' => $n->getAttribute('target'));
		}

		if (!empty($group['links'])) {
			$section['groups'][] = $group;
		}

		$sections[] = $section;
	}

	return $sections;
}

/** True when $href points to the page currently displayed. */
function kn_nav_is_current($href)
{
	$q = isset($_SERVER['QUERY_STRING']) ? urldecode($_SERVER['QUERY_STRING']) : '';

	if ($q === '' || strpos($href, '/display.php?') !== 0) {
		return false;
	}

	parse_str(parse_url(html_entity_decode($href), PHP_URL_QUERY) ?? '', $want);
	parse_str($q, $have);

	foreach (array('frm_action', 'frm_o_cname', 'frm_subaction', 'frm_dttype') as $k) {
		$w = isset($want[$k]) ? $want[$k] : '';
		$h = isset($have[$k]) ? $have[$k] : '';

		if ($w !== $h) {
			return false;
		}
	}

	return true;
}

function kn_nav_render($sections)
{
	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

	$out = "<nav class=\"kn-nav\" aria-label=\"Main\">\n";

	foreach ($sections as $i => $s) {
		$hasSub = !empty($s['groups']);

		if (!$hasSub) {
			$cur = ($s['href'] && kn_nav_is_current($s['href'])) ? ' aria-current="page"' : '';
			$out .= "<a class=\"kn-nav-top\" href=\"{$h($s['href'] ?: '#')}\"{$cur}>"
				. kn_nav_icon($s['icon']) . "<span class=\"kn-nav-text\">{$h($s['title'])}</span></a>\n";

			continue;
		}

		$open = '';
		$body = '';

		foreach ($s['groups'] as $g) {
			$body .= "<div class=\"kn-nav-group\">";

			if ($g['label'] !== '') {
				$body .= "<div class=\"kn-nav-label\">{$h($g['label'])}</div>";
			}

			foreach ($g['links'] as $l) {
				$cur = '';

				if (kn_nav_is_current($l['href'])) {
					$cur = ' aria-current="page"';
					$open = ' open';
				}

				$tgt = $l['target'] ? " target=\"{$h($l['target'])}\" rel=\"noopener\"" : '';
				$body .= "<a href=\"{$h($l['href'])}\"{$cur}{$tgt}>{$h($l['text'])}</a>";
			}

			$body .= "</div>";
		}

		$out .= "<details class=\"kn-nav-section\" data-kn-section=\"{$i}\"{$open}>"
			. "<summary class=\"kn-nav-top\" title=\"{$h($s['title'])}\">" . kn_nav_icon($s['icon'])
			. "<span class=\"kn-nav-text\">{$h($s['title'])}</span>"
			. "<svg class=\"kn-chevron\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"m9 6 6 6-6 6\"/></svg>"
			. "</summary><div class=\"kn-nav-sub\">{$body}</div></details>\n";
	}

	return $out . "</nav>\n";
}
