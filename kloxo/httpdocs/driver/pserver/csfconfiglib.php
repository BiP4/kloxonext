<?php

// KloxoNext - Firewall > CSF Settings: every ConfigServer Security & Firewall setting
// from the panel, like the csf module of Webmin.
//
//   Configuration   all options of /etc/csf/csf.conf, grouped by section, with the
//                   help text of the file; only the changed options are written
//   Files           the csf lists (allow, deny, ignore, pignore, dyndns, blocklists ...)
//   Tools           restart / enable / disable, IP search, allow / deny / temporary
//                   bans, rules, temporary list and lfd log
//
// Administrator and local server only. Every write keeps a backup next to the file
// (<file>.kn-<date>, 10 kept); when csf does not restart with the new settings the
// backup is put back. SSH and the panel ports are always kept in TCP_IN / TCP6_IN.

class csfconfig extends lxclass
{
	static $__desc = array("", "", "csf_settings");
	static $__desc_nname = array("", "", "csf_settings");

	static $__acdesc_show = array("", "", "csf_settings");

	const DIR = '/etc/csf';
	const CSF = '/usr/sbin/csf';

	// editable lists, with what they hold
	static $files = array(
		'csf.allow'       => 'IPs always allowed through the firewall',
		'csf.deny'        => 'IPs blocked by the firewall',
		'csf.ignore'      => 'IPs lfd never blocks',
		'csf.pignore'     => 'processes lfd ignores',
		'csf.fignore'     => 'files lfd ignores (directory watching)',
		'csf.rignore'     => 'reverse DNS names lfd ignores',
		'csf.signore'     => 'scripts lfd ignores (email alerts)',
		'csf.mignore'     => 'users ignored for email limits',
		'csf.suignore'    => 'files ignored by the superuser check',
		'csf.uidignore'   => 'UIDs ignored by the user tracking',
		'csf.dyndns'      => 'dynamic DNS names allowed (re-resolved)',
		'csf.blocklists'  => 'IP block lists downloaded by lfd',
		'csf.logfiles'    => 'log files watched by lfd',
		'csf.logignore'   => 'log lines lfd ignores (regex)',
		'csf.syslogs'     => 'syslog files',
		'csf.syslogusers' => 'users allowed to write to syslog',
		'csf.sips'        => 'local IPs never blocked',
		'csf.redirect'    => 'port / IP redirections',
		'csf.smtpauth'    => 'IPs allowed for SMTP AUTH',
		'csf.cloudflare'  => 'Cloudflare integration',
		'csf.rblconf'     => 'RBL checks',
		'csf.dirwatch'    => 'directories watched by lfd',
		'csf.resellers'   => 'reseller permissions',
		'csfpre.sh'       => 'shell commands run before csf loads its rules',
		'csfpost.sh'      => 'shell commands run after csf loads its rules',
	);

	function get() {}
	function write() {}

	static function initThisObjectRule($parent, $class, $name = null)
	{
		return 'csfconfig';
	}

	static function installed()
	{
		return is_file(self::DIR . '/csf.conf') && is_executable(self::CSF);
	}

	// ------------------------------------------------------------ helpers

	static function h($s)
	{
		return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	}

	// output of a command, its exit code in $rc
	static function cmd($cmd, &$rc = null)
	{
		$out = array();
		exec("{$cmd} 2>&1", $out, $rc);

		// csf colours its output for terminals
		return preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', implode("\n", $out));
	}

	static function csf($args, &$rc = null)
	{
		$cmd = self::CSF;

		// every argument is a fixed option or a validated value (IP, TTL, comment), quoted
		foreach ((array)$args as $a) {
			$cmd .= ' ' . escapeshellarg((string)$a);
		}

		return self::cmd($cmd, $rc); // NOSONAR
	}

	// per-session form token (stateless: HMAC of the panel session id)
	static function token()
	{
		global $gbl, $sgbl;

		$f = "{$sgbl->__path_program_root}/etc/conf/csf-form.key";

		if (!is_file($f)) {
			@file_put_contents($f, bin2hex(random_bytes(32)));
			@chmod($f, 0600);
		}

		$sid = isset($gbl->c_session->nname) ? $gbl->c_session->nname : '';

		return hash_hmac('sha256', 'csf|' . $sid, (string)@file_get_contents($f));
	}

	// the panel's own POST token (checked by display.php for every POST)
	static function panelToken()
	{
		// created on first use, like every form of the panel
		return (string)getCSRFToken();
	}

	static function backup($file)
	{
		if (!is_file($file)) {
			return null;
		}

		$b = $file . '.kn-' . date('Ymd-His');
		@copy($file, $b);
		@chmod($b, 0600);

		// keep the 10 newest backups of this file
		$list = (array)glob($file . '.kn-*');
		rsort($list);

		foreach (array_slice($list, 10) as $old) {
			@unlink($old);
		}

		return $b;
	}

	// SSH + panel ports (from /script/firewall)
	static function requiredTcp()
	{
		$out = self::cmd('sh /script/firewall status');

		if (preg_match('/^REQUIRED_TCP=(.*)$/m', $out, $m)) {
			return preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY);
		}

		return array('22', '7777', '7778');
	}

	// a csf port list ("20,21,22,30000:35000") keeps every required port
	static function keepRequired($list, $required)
	{
		$items = preg_split('/\s*,\s*/', trim($list), -1, PREG_SPLIT_NO_EMPTY);
		$added = array();

		foreach ($required as $p) {
			$in = false;

			foreach ($items as $it) {
				if ($it === $p || (preg_match('/^(\d+):(\d+)$/', $it, $m) && $p >= $m[1] && $p <= $m[2])) {
					$in = true;

					break;
				}
			}

			if (!$in) {
				$items[] = $p;
				$added[] = $p;
			}
		}

		return array(implode(',', $items), $added);
	}

	// ------------------------------------------------------------ csf.conf

	// sections => list of options (name, value, help); keeps the order of the file
	static function parseConf($text)
	{
		$sections = array();
		$cur = 'General';
		$sections[$cur] = array('desc' => '', 'opts' => array());
		$buf = array();
		$inSectionDesc = false;

		foreach (preg_split('/\r?\n/', $text) as $line) {
			$t = rtrim($line);

			if ($t === '' && !$buf) {
				continue;
			}

			// block separators: "# #", "####...", "#"
			if (preg_match('/^#\s*#?\s*$/', $t) || preg_match('/^#{3,}\s*$/', $t)) {
				if ($inSectionDesc && $buf) {
					$sections[$cur]['desc'] = trim(implode("\n", $buf));
					$buf = array();
					$inSectionDesc = false;
				}

				continue;
			}

			if ($t !== '' && $t[0] === '#') {
				$c = preg_replace('/^#\s?/', '', $t);

				if (preg_match('/^\s*SECTION:\s*(.+?)\s*$/', $c, $m)) {
					$cur = trim(strip_tags($m[1]));

					if (!isset($sections[$cur])) {
						$sections[$cur] = array('desc' => '', 'opts' => array());
					}

					$buf = array();
					$inSectionDesc = true;

					continue;
				}

				$buf[] = $c;

				continue;
			}

			if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*"(.*)"\s*$/', $t, $m)) {
				$sections[$cur]['opts'][] = array('name' => $m[1], 'value' => $m[2], 'help' => trim(implode("\n", $buf)));
				$buf = array();
				$inSectionDesc = false;
			}
		}

		foreach ($sections as $k => $s) {
			if (!$s['opts']) {
				unset($sections[$k]);
			}
		}

		return $sections;
	}

	// write the changed options, in place; returns the new text
	static function applyConf($text, $changes)
	{
		$lines = preg_split('/\r?\n/', $text);

		foreach ($lines as $i => $line) {
			if (preg_match('/^([A-Z][A-Z0-9_]*)(\s*=\s*)"(.*)"\s*$/', $line, $m) && array_key_exists($m[1], $changes)) {
				$lines[$i] = $m[1] . $m[2] . '"' . $changes[$m[1]] . '"';
			}
		}

		return implode("\n", $lines);
	}

	// ------------------------------------------------------------ actions

	function handlePost(&$msg, &$out)
	{
		$a = isset($_POST['kn_csf_action']) ? (string)$_POST['kn_csf_action'] : '';

		if ($a === '') {
			return;
		}

		if (!isset($_POST['kn_csf_token']) || !hash_equals(self::token(), (string)$_POST['kn_csf_token'])) {
			$msg = array('err', 'The form expired. Reload the page and try again.');

			return;
		}

		switch ($a) {
			case 'saveconf':
				$this->saveConf($msg, $out);

				break;
			case 'savefile':
				$this->saveFile($msg, $out);

				break;
			default:
				$this->tool($a, $msg, $out);
		}
	}

	function saveConf(&$msg, &$out)
	{
		$file = self::DIR . '/csf.conf';
		$text = (string)file_get_contents($file);
		$known = array();

		foreach (self::parseConf($text) as $s) {
			foreach ($s['opts'] as $o) {
				$known[$o['name']] = $o['value'];
			}
		}

		$changes = array();

		foreach ((array)(isset($_POST['opt']) ? $_POST['opt'] : array()) as $k => $v) {
			$k = (string)$k;
			$v = str_replace(array("\r", "\n", "\0", "\t"), array('', ' ', '', ' '), (string)$v);

			if (!isset($known[$k]) || $known[$k] === $v) {
				continue;
			}

			if (strpos($v, '"') !== false || strlen($v) > 8000) {
				$msg = array('err', "{$k}: a value cannot contain double quotes.");

				return;
			}

			$changes[$k] = $v;
		}

		if (!$changes) {
			$msg = array('ok', 'Nothing changed.');

			return;
		}

		// never lock the administrator out
		$note = '';
		$req = self::requiredTcp();

		foreach (array('TCP_IN', 'TCP6_IN') as $k) {
			if (isset($changes[$k])) {
				list($changes[$k], $added) = self::keepRequired($changes[$k], $req);

				if ($added) {
					$note .= " {$k} keeps " . implode(', ', $added) . " (SSH / panel).";
				}
			}
		}

		$backup = self::backup($file);
		file_put_contents($file, self::applyConf($text, $changes));

		$out = self::csf('-r', $rc);

		if ($rc !== 0) {
			// put the working configuration back
			if ($backup) {
				copy($backup, $file);
				$out .= "\n\n--- csf did not restart: previous configuration restored ---\n" . self::csf('-r');
			}

			$msg = array('err', 'csf did not accept the new settings; the previous configuration was restored.');

			return;
		}

		if (isset($_POST['restart_lfd']) && !isset($changes['TESTING'])) {
			$out .= "\n" . self::cmd('systemctl restart lfd');
		}

		if (isset($changes['TESTING']) || isset($_POST['restart_lfd'])) {
			$out .= "\n" . self::cmd('systemctl ' . (($this->confValue('TESTING') === '1') ? 'stop' : 'restart') . ' lfd');
		}

		$msg = array('ok', count($changes) . ' setting(s) saved: ' . implode(', ', array_keys($changes)) . '. csf restarted.' . $note);
	}

	function confValue($key)
	{
		$text = (string)@file_get_contents(self::DIR . '/csf.conf');

		return preg_match('/^' . preg_quote($key, '/') . '\s*=\s*"(.*)"\s*$/m', $text, $m) ? $m[1] : null;
	}

	static function fileList()
	{
		$list = array();

		foreach (self::$files as $f => $d) {
			if (is_file(self::DIR . '/' . $f)) {
				$list[$f] = $d;
			}
		}

		return $list;
	}

	function saveFile(&$msg, &$out)
	{
		$f = isset($_POST['file']) ? (string)$_POST['file'] : '';
		$list = self::fileList();

		if (!isset($list[$f])) {
			$msg = array('err', 'Unknown file.');

			return;
		}

		$path = self::DIR . '/' . $f;
		$text = str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), isset($_POST['content']) ? (string)$_POST['content'] : '');

		if ($text !== '' && substr($text, -1) !== "\n") {
			$text .= "\n";
		}

		$backup = self::backup($path);
		file_put_contents($path, $text);

		$out = self::csf('-r', $rc);

		if ($rc !== 0) {
			if ($backup) {
				copy($backup, $path);
				$out .= "\n\n--- csf did not restart: previous file restored ---\n" . self::csf('-r');
			}

			$msg = array('err', "csf did not accept {$f}; the previous version was restored.");

			return;
		}

		// lfd reads its own lists when it starts
		if (!preg_match('/^csf\.(allow|deny|redirect)$|\.sh$/', $f) && $this->confValue('TESTING') !== '1') {
			$out .= "\n" . self::cmd('systemctl restart lfd');
		}

		$msg = array('ok', "{$f} saved, csf restarted.");
	}

	static function validIp($ip)
	{
		$ip = trim((string)$ip);
		$addr = $ip;
		$pre = null;

		if (strpos($ip, '/') !== false) {
			list($addr, $pre) = explode('/', $ip, 2);
		}

		if (filter_var($addr, FILTER_VALIDATE_IP) === false) {
			return false;
		}

		if ($pre !== null) {
			$max = (strpos($addr, ':') !== false) ? 128 : 32;

			if (!ctype_digit($pre) || (int)$pre > $max) {
				return false;
			}
		}

		return $ip;
	}

	function tool($a, &$msg, &$out)
	{
		$ip = self::validIp(isset($_POST['ip']) ? $_POST['ip'] : '');
		$comment = substr(preg_replace('/[^A-Za-z0-9 ._:@\/()-]/', '', isset($_POST['comment']) ? (string)$_POST['comment'] : ''), 0, 80);
		$ttl = isset($_POST['ttl']) ? (int)$_POST['ttl'] : 3600;
		$ttl = max(60, min($ttl, 31536000));

		$simple = array(
			'restart'     => array(array('-r'), 'csf restarted.'),
			'restartall'  => array(array('-ra'), 'csf and lfd restarted.'),
			'enable'      => array(array('-e'), 'csf and lfd enabled.'),
			'disable'     => array(array('-x'), 'csf and lfd disabled (the server is not protected).'),
			'tempflush'   => array(array('-tf'), 'All temporary bans and allows removed.'),
			'templist'    => array(array('-t'), 'Temporary bans and allows.'),
			'rules'       => array(array('-l'), 'Current iptables rules.'),
		);

		if (isset($simple[$a])) {
			$out = self::csf($simple[$a][0], $rc);
			$msg = array(($rc === 0) ? 'ok' : 'err', $simple[$a][1]);

			if ($a === 'rules') {
				$lines = explode("\n", $out);

				if (count($lines) > 3000) {
					$out = implode("\n", array_slice($lines, 0, 3000)) . "\n... (" . (count($lines) - 3000) . " more lines)";
				}
			}

			return;
		}

		if ($a === 'lfdrestart') {
			$out = self::cmd('systemctl restart lfd; systemctl status lfd --no-pager -n 5');
			$msg = array('ok', 'lfd restarted.');

			return;
		}

		if ($a === 'lfdlog') {
			$out = is_file('/var/log/lfd.log') ? self::cmd('tail -n 300 /var/log/lfd.log') : 'No /var/log/lfd.log yet.';
			$msg = array('ok', 'Last 300 lines of /var/log/lfd.log.');

			return;
		}

		$ipops = array(
			'grep'      => array('-g', null, 'Search result for %s.'),
			'allow'     => array('-a', 'comment', '%s added to csf.allow.'),
			'deny'      => array('-d', 'comment', '%s added to csf.deny.'),
			'allowrm'   => array('-ar', null, '%s removed from csf.allow.'),
			'denyrm'    => array('-dr', null, '%s removed from csf.deny (unblocked).'),
			'tempallow' => array('-ta', 'ttl', '%s temporarily allowed.'),
			'tempdeny'  => array('-td', 'ttl', '%s temporarily blocked.'),
			'temprm'    => array('-tr', null, '%s removed from the temporary lists.'),
		);

		if (!isset($ipops[$a])) {
			$msg = array('err', 'Unknown action.');

			return;
		}

		if ($ip === false) {
			$msg = array('err', 'Enter a valid IP address or network (for example 203.0.113.7 or 203.0.113.0/24).');

			return;
		}

		$args = array($ipops[$a][0], $ip);

		if ($ipops[$a][1] === 'comment' && $comment !== '') {
			$args[] = $comment;
		} elseif ($ipops[$a][1] === 'ttl') {
			$args[] = (string)$ttl;

			if ($comment !== '') {
				$args[] = $comment;
			}
		}

		$out = self::csf($args, $rc);
		$msg = array(($rc === 0) ? 'ok' : 'err', sprintf($ipops[$a][2], $ip));
	}

	// ------------------------------------------------------------ page

	static function url($tab, $extra = array())
	{
		$q = array();
		parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q);
		unset($q['kn_csf_tab'], $q['kn_csf_file']);
		$q['kn_csf_tab'] = $tab;

		foreach ($extra as $k => $v) {
			$q[$k] = $v;
		}

		return '/display.php?' . http_build_query($q);
	}

	function showRawPrint($subaction = null)
	{
		global $login;

		if (!$login->isAdmin()) {
			print("<p>The CSF settings are available to the administrator only.</p>");

			return;
		}

		$p = $this->getParentO();
		$pp = $p ? $p->getParentO() : null;

		if ($pp && isset($pp->nname) && !isLocalhost($pp->nname)) {
			print("<p>CSF is managed here for the local server only.</p>");

			return;
		}

		if (!self::installed()) {
			print("<p>CSF is not installed on this server. It can be installed from the Firewall page.</p>");

			return;
		}

		$msg = null;
		$out = null;

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->handlePost($msg, $out);
		}

		$tab = isset($_GET['kn_csf_tab']) ? (string)$_GET['kn_csf_tab'] : 'conf';

		if (!in_array($tab, array('conf', 'files', 'tools'), true)) {
			$tab = 'conf';
		}

		$token = self::token();
		$ver = trim(self::csf('-v'));
		$ver = strtok($ver, "\n") . ' ' . (preg_match('/v[0-9][0-9.]*/', $ver, $m) ? $m[0] : '');
		$testing = ($this->confValue('TESTING') === '1');
		$lfd = trim(self::cmd('systemctl is-active lfd'));
		$disabled = is_file(self::DIR . '/csf.disable');

		$this->printStyle();
?>
<div class="kn-csf">
	<div class="kn-csf-head">
		<div>
			<strong><?= self::h($ver) ?></strong>
			<span class="kn-csf-badge <?= $disabled ? 'bad' : 'good' ?>"><?= $disabled ? 'csf disabled' : 'csf enabled' ?></span>
			<span class="kn-csf-badge <?= ($lfd === 'active') ? 'good' : 'warn' ?>">lfd: <?= self::h($lfd) ?></span>
			<?php if ($testing) { ?><span class="kn-csf-badge warn">TESTING mode: rules are flushed every few minutes, lfd does not run</span><?php } ?>
		</div>
	</div>

	<nav class="kn-csf-tabs">
		<a href="<?= self::h(self::url('conf')) ?>" class="<?= ($tab === 'conf') ? 'on' : '' ?>">Configuration (csf.conf)</a>
		<a href="<?= self::h(self::url('files')) ?>" class="<?= ($tab === 'files') ? 'on' : '' ?>">Files &amp; lists</a>
		<a href="<?= self::h(self::url('tools')) ?>" class="<?= ($tab === 'tools') ? 'on' : '' ?>">Tools</a>
	</nav>

	<?php if ($msg) { ?>
	<div class="kn-csf-msg <?= ($msg[0] === 'ok') ? 'ok' : 'err' ?>"><?= self::h($msg[1]) ?></div>
	<?php } ?>
	<?php if ($out !== null && $out !== '') { ?>
	<pre class="kn-csf-out"><?= self::h($out) ?></pre>
	<?php } ?>
<?php
		if ($tab === 'conf') {
			$this->printConf($token);
		} elseif ($tab === 'files') {
			$this->printFiles($token);
		} else {
			$this->printTools($token);
		}
?>
</div>
<?php
	}

	function printConf($token)
	{
		$sections = self::parseConf((string)@file_get_contents(self::DIR . '/csf.conf'));
		$n = 0;
?>
	<form method="post" action="<?= self::h(self::url('conf')) ?>" class="kn-csf-conf" id="kn-csf-conf">
		<input type="hidden" name="kn_csf_token" value="<?= self::h($token) ?>">
		<input type="hidden" name="frm_token" value="<?= self::h(self::panelToken()) ?>">
		<input type="hidden" name="kn_csf_action" value="saveconf">

		<div class="kn-csf-bar">
			<input type="search" id="kn-csf-search" placeholder="Search options and help (e.g. TCP_IN, LF_SSHD, port scan)">
			<select id="kn-csf-jump">
				<option value="">Go to section…</option>
				<?php $i = 0; foreach ($sections as $name => $s) { ?>
				<option value="kn-csf-s<?= $i++ ?>"><?= self::h($name) ?> (<?= count($s['opts']) ?>)</option>
				<?php } ?>
			</select>
			<label class="kn-csf-chk"><input type="checkbox" name="restart_lfd" value="1" checked> restart lfd too</label>
			<button type="submit" class="kn-csf-btn primary">Save changes &amp; restart csf</button>
			<span id="kn-csf-changed" class="kn-csf-changed"></span>
		</div>

		<?php $i = 0; foreach ($sections as $name => $s) { ?>
		<section class="kn-csf-section" id="kn-csf-s<?= $i++ ?>">
			<h3><?= self::h($name) ?></h3>
			<?php if ($s['desc'] !== '') { ?><p class="kn-csf-sdesc"><?= nl2br(self::h(strip_tags($s['desc']))) ?></p><?php } ?>

			<?php foreach ($s['opts'] as $o) { $n++; $id = 'kn-csf-o' . $n; $long = (strlen($o['value']) > 40 || preg_match('/(_IN|_OUT|PORTS|_LIST|IGNORE)$/', $o['name'])); ?>
			<div class="kn-csf-opt" data-search="<?= self::h(strtolower($o['name'] . ' ' . strip_tags($o['help']))) ?>">
				<label for="<?= $id ?>" class="kn-csf-name"><?= self::h($o['name']) ?></label>
				<div class="kn-csf-field">
					<?php if ($long) { ?>
					<textarea id="<?= $id ?>" name="opt[<?= self::h($o['name']) ?>]" rows="2" data-orig="<?= self::h($o['value']) ?>" disabled><?= self::h($o['value']) ?></textarea>
					<?php } else { ?>
					<input id="<?= $id ?>" type="text" name="opt[<?= self::h($o['name']) ?>]" value="<?= self::h($o['value']) ?>" data-orig="<?= self::h($o['value']) ?>" disabled>
					<?php } ?>
					<?php if ($o['help'] !== '') { ?>
					<details><summary>Help</summary><div class="kn-csf-help"><?= nl2br(self::h(strip_tags($o['help']))) ?></div></details>
					<?php } ?>
				</div>
			</div>
			<?php } ?>
		</section>
		<?php } ?>
	</form>

	<script>
	(function () {
		var form = document.getElementById('kn-csf-conf');
		var fields = form.querySelectorAll('[data-orig]');
		var counter = document.getElementById('kn-csf-changed');

		// fields stay disabled (not sent) until they are edited: only changes are posted
		function changed() {
			var n = 0;
			fields.forEach(function (f) {
				var c = (f.value !== f.getAttribute('data-orig'));
				f.closest('.kn-csf-opt').classList.toggle('changed', c);
				if (c) { n++; }
			});
			counter.textContent = n ? (n + ' changed') : '';
		}

		fields.forEach(function (f) {
			f.disabled = false;
			f.addEventListener('input', changed);
		});

		form.addEventListener('submit', function () {
			fields.forEach(function (f) {
				if (f.value === f.getAttribute('data-orig')) { f.disabled = true; }
			});
		});

		document.getElementById('kn-csf-search').addEventListener('input', function () {
			var q = this.value.trim().toLowerCase();
			form.querySelectorAll('.kn-csf-opt').forEach(function (o) {
				o.style.display = (!q || o.getAttribute('data-search').indexOf(q) !== -1) ? '' : 'none';
			});
			form.querySelectorAll('.kn-csf-section').forEach(function (s) {
				var any = Array.prototype.some.call(s.querySelectorAll('.kn-csf-opt'), function (o) { return o.style.display !== 'none'; });
				s.style.display = any ? '' : 'none';
			});
		});

		document.getElementById('kn-csf-jump').addEventListener('change', function () {
			var el = document.getElementById(this.value);
			if (el) { el.scrollIntoView({behavior: 'smooth', block: 'start'}); }
			this.value = '';
		});
	})();
	</script>
<?php
	}

	function printFiles($token)
	{
		$list = self::fileList();
		$f = isset($_GET['kn_csf_file']) ? (string)$_GET['kn_csf_file'] : 'csf.allow';

		if (isset($_POST['file']) && isset($list[(string)$_POST['file']])) {
			$f = (string)$_POST['file'];
		}

		if (!isset($list[$f])) {
			$f = key($list);
		}
?>
	<div class="kn-csf-files">
		<ul class="kn-csf-flist">
			<?php foreach ($list as $name => $d) { ?>
			<li class="<?= ($name === $f) ? 'on' : '' ?>"><a href="<?= self::h(self::url('files', array('kn_csf_file' => $name))) ?>"><strong><?= self::h($name) ?></strong><span><?= self::h($d) ?></span></a></li>
			<?php } ?>
		</ul>

		<?php if ($f) { ?>
		<form method="post" action="<?= self::h(self::url('files', array('kn_csf_file' => $f))) ?>" class="kn-csf-fedit">
			<input type="hidden" name="kn_csf_token" value="<?= self::h($token) ?>">
		<input type="hidden" name="frm_token" value="<?= self::h(self::panelToken()) ?>">
			<input type="hidden" name="kn_csf_action" value="savefile">
			<input type="hidden" name="file" value="<?= self::h($f) ?>">
			<h3><?= self::h(self::DIR . '/' . $f) ?></h3>
			<p class="kn-csf-sdesc"><?= self::h($list[$f]) ?>. A backup is kept; when csf does not accept the file the previous version is restored.</p>
			<textarea name="content" rows="24" spellcheck="false"><?= self::h((string)@file_get_contents(self::DIR . '/' . $f)) ?></textarea>
			<button type="submit" class="kn-csf-btn primary">Save &amp; restart csf</button>
		</form>
		<?php } ?>
	</div>
<?php
	}

	function printTools($token)
	{
		$form = function ($action, $label, $class = '', $confirm = '') use ($token) {
			$c = $confirm ? ' onsubmit="return confirm(' . self::h(json_encode($confirm)) . ')"' : '';

			return '<form method="post" action="' . self::h(self::url('tools')) . '"' . $c . '>'
				. '<input type="hidden" name="kn_csf_token" value="' . self::h($token) . '">'
				. '<input type="hidden" name="frm_token" value="' . self::h(self::panelToken()) . '">'
				. '<input type="hidden" name="kn_csf_action" value="' . self::h($action) . '">'
				. '<button type="submit" class="kn-csf-btn ' . $class . '">' . self::h($label) . '</button></form>';
		};
?>
	<div class="kn-csf-tools">
		<div class="kn-csf-card">
			<h3>Service</h3>
			<div class="kn-csf-row">
				<?= $form('restart', 'Restart csf', 'primary') ?>
				<?= $form('restartall', 'Restart csf + lfd') ?>
				<?= $form('lfdrestart', 'Restart lfd') ?>
				<?= $form('enable', 'Enable csf + lfd') ?>
				<?= $form('disable', 'Disable csf + lfd', 'danger', 'Disable the firewall? The server will not be protected until it is enabled again.') ?>
			</div>
		</div>

		<div class="kn-csf-card">
			<h3>IP address</h3>
			<form method="post" action="<?= self::h(self::url('tools')) ?>" class="kn-csf-ipform">
				<input type="hidden" name="kn_csf_token" value="<?= self::h($token) ?>">
		<input type="hidden" name="frm_token" value="<?= self::h(self::panelToken()) ?>">
				<label>IP or network <input type="text" name="ip" placeholder="203.0.113.7 or 203.0.113.0/24" required></label>
				<label>Comment <input type="text" name="comment" maxlength="80" placeholder="optional"></label>
				<label>Duration (temporary, seconds) <input type="number" name="ttl" value="3600" min="60"></label>
				<div class="kn-csf-row">
					<button type="submit" name="kn_csf_action" value="grep" class="kn-csf-btn">Search</button>
					<button type="submit" name="kn_csf_action" value="allow" class="kn-csf-btn">Allow</button>
					<button type="submit" name="kn_csf_action" value="deny" class="kn-csf-btn danger">Block</button>
					<button type="submit" name="kn_csf_action" value="denyrm" class="kn-csf-btn">Unblock</button>
					<button type="submit" name="kn_csf_action" value="allowrm" class="kn-csf-btn">Remove from allow</button>
					<button type="submit" name="kn_csf_action" value="tempallow" class="kn-csf-btn">Allow temporarily</button>
					<button type="submit" name="kn_csf_action" value="tempdeny" class="kn-csf-btn danger">Block temporarily</button>
					<button type="submit" name="kn_csf_action" value="temprm" class="kn-csf-btn">Remove temporary</button>
				</div>
			</form>
		</div>

		<div class="kn-csf-card">
			<h3>View</h3>
			<div class="kn-csf-row">
				<?= $form('templist', 'Temporary bans / allows') ?>
				<?= $form('tempflush', 'Remove all temporary entries', '', 'Remove every temporary ban and allow?') ?>
				<?= $form('rules', 'Firewall rules (iptables)') ?>
				<?= $form('lfdlog', 'lfd log') ?>
			</div>
		</div>
	</div>
<?php
	}

	function printStyle()
	{
?>
<style>
.kn-csf { --c-bg: var(--kn-surface, #fff); --c-bg2: var(--kn-surface-2, #f8fafc); --c-bd: var(--kn-border, #e3e8f0);
	--c-tx: var(--kn-text, #0f172a); --c-tx2: var(--kn-text-2, #475569); --c-pri: var(--kn-primary, #2563eb);
	--c-on: var(--kn-on-primary, #fff); --c-ok: var(--kn-success, #15803d); --c-oks: var(--kn-success-soft, #e7f6ec);
	--c-err: var(--kn-danger, #c62828); --c-errs: var(--kn-danger-soft, #fdecec); --c-warn: var(--kn-warning, #b45309);
	color: var(--c-tx); font-size: 14px; max-width: 1200px; }
.kn-csf h3 { margin: 0 0 8px; font-size: 16px; }
.kn-csf-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
.kn-csf-badge { display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid var(--c-bd); }
.kn-csf-badge.good { color: var(--c-ok); background: var(--c-oks); border-color: transparent; }
.kn-csf-badge.bad { color: var(--c-err); background: var(--c-errs); border-color: transparent; }
.kn-csf-badge.warn { color: var(--c-warn); }
.kn-csf-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--c-bd); margin-bottom: 14px; flex-wrap: wrap; }
.kn-csf-tabs a { padding: 8px 14px; text-decoration: none; color: var(--c-tx2); border-bottom: 2px solid transparent; }
.kn-csf-tabs a.on { color: var(--c-pri); border-bottom-color: var(--c-pri); font-weight: 600; }
.kn-csf-msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; }
.kn-csf-msg.ok { background: var(--c-oks); color: var(--c-ok); }
.kn-csf-msg.err { background: var(--c-errs); color: var(--c-err); }
.kn-csf-out { background: #0b1120; color: #e2e8f0; padding: 12px; border-radius: 8px; max-height: 420px; overflow: auto; white-space: pre-wrap; font: 12px/1.45 var(--kn-mono, monospace); margin-bottom: 14px; }
.kn-csf-bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px; background: var(--c-bg2); border: 1px solid var(--c-bd); border-radius: 10px; margin-bottom: 12px; }
.kn-csf-bar input[type=search] { flex: 1 1 260px; }
.kn-csf input[type=text], .kn-csf input[type=search], .kn-csf input[type=number], .kn-csf select, .kn-csf textarea {
	box-sizing: border-box; padding: 6px 8px; border: 1px solid var(--c-bd); border-radius: 6px; background: var(--c-bg); color: var(--c-tx); font: inherit; max-width: 100%; }
.kn-csf textarea { width: 100%; font-family: var(--kn-mono, monospace); font-size: 13px; resize: vertical; }
.kn-csf-section { border: 1px solid var(--c-bd); border-radius: 10px; padding: 14px; margin-bottom: 14px; background: var(--c-bg); scroll-margin-top: 70px; }
.kn-csf-sdesc { color: var(--c-tx2); margin: 0 0 10px; font-size: 13px; }
.kn-csf-opt { display: grid; grid-template-columns: minmax(160px, 260px) 1fr; gap: 10px; padding: 8px 0; border-top: 1px solid var(--c-bd); }
.kn-csf-opt.changed .kn-csf-name { color: var(--c-pri); }
.kn-csf-opt.changed input, .kn-csf-opt.changed textarea { border-color: var(--c-pri); }
.kn-csf-name { font-family: var(--kn-mono, monospace); font-size: 13px; font-weight: 600; word-break: break-all; padding-top: 6px; }
.kn-csf-field input[type=text] { width: 100%; max-width: 420px; }
.kn-csf-field details { margin-top: 4px; }
.kn-csf-field summary { cursor: pointer; color: var(--c-tx2); font-size: 12px; }
.kn-csf-help { color: var(--c-tx2); font-size: 12.5px; padding: 6px 0 2px; }
.kn-csf-changed { color: var(--c-pri); font-weight: 600; }
.kn-csf-chk { display: inline-flex; gap: 4px; align-items: center; color: var(--c-tx2); }
/* nexus paints every button (with !important): these keep their own meaning */
.kn-main .kn-csf button.kn-csf-btn { padding: 7px 12px; border-radius: 7px; border: 1px solid var(--c-bd) !important; background: var(--c-bg) !important; color: var(--c-tx) !important; cursor: pointer; font: inherit; font-weight: 500; box-shadow: none !important; }
.kn-main .kn-csf button.kn-csf-btn:hover { border-color: var(--c-pri) !important; }
.kn-main .kn-csf button.kn-csf-btn.primary { background: var(--c-pri) !important; color: var(--c-on) !important; border-color: var(--c-pri) !important; }
.kn-main .kn-csf button.kn-csf-btn.danger { color: var(--c-err) !important; border-color: var(--c-err) !important; }
.kn-csf-files { display: grid; grid-template-columns: 280px 1fr; gap: 14px; }
.kn-csf-flist { list-style: none; margin: 0; padding: 0; border: 1px solid var(--c-bd); border-radius: 10px; overflow: hidden; align-self: start; }
.kn-csf-flist li a { display: block; padding: 8px 12px; text-decoration: none; color: var(--c-tx); border-top: 1px solid var(--c-bd); }
.kn-csf-flist li:first-child a { border-top: 0; }
.kn-csf-flist li a span { display: block; color: var(--c-tx2); font-size: 12px; }
.kn-csf-flist li.on a { background: var(--c-bg2); box-shadow: inset 3px 0 0 var(--c-pri); }
.kn-csf-fedit textarea { margin: 6px 0 10px; }
.kn-csf-tools { display: grid; gap: 14px; }
.kn-csf-card { border: 1px solid var(--c-bd); border-radius: 10px; padding: 14px; background: var(--c-bg); }
.kn-csf-row { display: flex; flex-wrap: wrap; gap: 8px; }
.kn-csf-row form { margin: 0; }
.kn-csf-ipform { display: grid; gap: 10px; }
.kn-csf-ipform label { display: grid; gap: 4px; color: var(--c-tx2); max-width: 420px; }
@media (max-width: 760px) {
	.kn-csf-opt, .kn-csf-files { grid-template-columns: 1fr; }
}
</style>
<?php
	}
}
