/*
 * KloxoNext 'nexus' skin - shell behaviour (no dependencies)
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var mobile = window.matchMedia('(max-width: 860px)');

	function store(key, value) {
		try {
			if (value === null) { localStorage.removeItem(key); } else { localStorage.setItem(key, value); }
		} catch (e) { /* private mode: keep working without persistence */ }
	}

	/* ---- sidebar: drawer on phones, icon-collapse on desktop ------------ */

	function toggleSidebar() {
		if (mobile.matches) {
			root.classList.toggle('kn-sidebar-open');
		} else {
			var c = root.classList.toggle('kn-collapsed');
			store('kn-sidebar', c ? 'collapsed' : null);
			syncSections();
		}
	}

	function collapsedDesktop() {
		return root.classList.contains('kn-collapsed') && !mobile.matches;
	}

	// collapsed: every flyout closed (they open on hover); expanded: the section
	// holding the current page is open
	function syncSections() {
		document.querySelectorAll('.kn-nav-section').forEach(function (d) {
			d.open = !collapsedDesktop() && !!d.querySelector('[aria-current="page"]');
		});
	}

	document.addEventListener('click', function (e) {
		var t = e.target.closest('[data-kn-toggle-sidebar]');
		if (t) { toggleSidebar(); return; }

		if (e.target.closest('[data-kn-close-sidebar]')) {
			root.classList.remove('kn-sidebar-open');
			return;
		}

		var conf = e.target.closest('[data-kn-confirm]');
		if (conf && !window.confirm(conf.getAttribute('data-kn-confirm'))) {
			e.preventDefault();
			return;
		}

		if (e.target.closest('[data-kn-toast-close]')) {
			var toast = e.target.closest('[data-kn-toast]');
			if (toast) { toast.remove(); }
			return;
		}

		// close the account menu / collapsed flyouts when clicking elsewhere
		document.querySelectorAll('details.kn-user[open]').forEach(function (d) {
			if (!d.contains(e.target)) { d.removeAttribute('open'); }
		});

		if (collapsedDesktop()) {
			document.querySelectorAll('.kn-nav-section[open]').forEach(function (d) {
				if (!d.contains(e.target)) { d.removeAttribute('open'); }
			});
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			root.classList.remove('kn-sidebar-open');
			document.querySelectorAll('details.kn-user[open]').forEach(function (d) { d.removeAttribute('open'); });
		}
	});

	// only one sidebar section open at a time keeps the menu short
	document.querySelectorAll('.kn-nav-section').forEach(function (d) {
		var summary = d.querySelector('summary');
		var timer = null;

		function place() {
			var sub = d.querySelector('.kn-nav-sub');
			if (!sub) { return; }
			var r = summary.getBoundingClientRect();
			sub.style.top = Math.max(8, Math.min(r.top - 8, window.innerHeight - sub.offsetHeight - 8)) + 'px';
		}

		function show() {
			if (!collapsedDesktop()) { return; }
			clearTimeout(timer);
			d.open = true;
			place();
		}

		function hide() {
			if (!collapsedDesktop()) { return; }
			clearTimeout(timer);
			// short delay: lets the pointer travel from the icon into the flyout
			timer = setTimeout(function () { d.open = false; }, 180);
		}

		d.addEventListener('toggle', function () {
			if (!d.open) { return; }
			document.querySelectorAll('.kn-nav-section[open]').forEach(function (o) {
				if (o !== d) { o.removeAttribute('open'); }
			});
			if (collapsedDesktop()) { place(); }
		});

		// collapsed sidebar: flyouts follow the pointer / keyboard focus, clicks do not pin them
		d.addEventListener('mouseenter', show);
		d.addEventListener('mouseleave', hide);
		d.addEventListener('focusin', show);
		d.addEventListener('focusout', function (e) {
			if (!d.contains(e.relatedTarget)) { hide(); }
		});
		summary.addEventListener('click', function (e) {
			if (collapsedDesktop()) { e.preventDefault(); show(); }
		});
	});

	mobile.addEventListener('change', syncSections);
	syncSections();

	/* ---- light / dark theme --------------------------------------------- */

	document.querySelectorAll('[data-kn-theme-toggle]').forEach(function (b) {
		b.addEventListener('click', function () {
			var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			store('kn-theme', next);
		});
	});

	/* ---- page title: last breadcrumb entry -------------------------------- */

	var title = document.getElementById('kn-page-title');
	// first cell of the navigation bar holds the path; the second one "Login as ..."
	var nav = document.querySelector('.kn-main .verb4 .tbl_navigation td') || document.querySelector('.kn-main .verb4');
	if (title && nav) {
		// "admin — {All Clients}" -> "All Clients"
		var parts = nav.textContent.replace(/\s+/g, ' ').split(/[—»›]/);
		// drop braces and icon-font glyphs (private use area)
		var last = parts[parts.length - 1].replace(/[{}-☐]/g, '').trim();
		title.textContent = last;
		if (last) { document.title = last + ' · KloxoNext'; }
	}

	/* ---- wide legacy tables scroll horizontally instead of breaking layout */

	document.querySelectorAll('.kn-main table').forEach(function (t) {
		if (t.closest('.kn-table-wrap')) { return; }
		var isData = t.querySelector(':scope > tbody > tr.tablerow0, :scope > tbody > tr.tablerow1, :scope > tbody > tr > td.tableheader');
		if (!isData) { return; }
		var w = document.createElement('div');
		w.className = 'kn-table-wrap';
		t.parentNode.insertBefore(w, t);
		w.appendChild(t);
	});

	window.requestAnimationFrame(function () {
		window.requestAnimationFrame(function () { root.classList.remove('kn-preload'); });
	});

	/* ---- success toast fades out; errors stay ----------------------------- */

	var ok = document.querySelector('.kn-toast-ok');
	if (ok) {
		setTimeout(function () {
			ok.style.transition = 'opacity .4s ease';
			ok.style.opacity = '0';
			setTimeout(function () { ok.remove(); }, 450);
		}, 6000);
	}
})();
