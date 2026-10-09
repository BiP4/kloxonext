<?php

// KloxoNext - SSH Terminal page: a real terminal in the browser (ttyd behind the panel
// nginx at /terminal/, see init/kloxo-terminal.service and sbin/kn-terminal).
//   server page (admin)  -> root shell
//   client page          -> the client's shell; with 'jailed' shell access it only sees
//                           its own home directory (/script/kn-jail)
// Every page view creates a one-time token valid for 60 seconds.

class sshclient extends lxclass
{
	static $__desc = array("", "", "ssh_client");
	static $__desc_nname = array("", "", "ssh_client");

	static $__acdesc_show = array("", "",  "ssh_terminal");

	function get() {}
	function write() {}

	function showRawPrint($subaction = null)
	{
		global $gbl, $sgbl, $login, $ghtml;

		$parent = $this->getParentO();
		$note = '';

		if ($parent->getClass() === 'pserver') {
			if (!$login->isAdmin() || !$parent->isLocalhost('nname')) {
				$this->printMessage("The terminal is available for the local server and the administrator only.");

				return;
			}

			$user = 'root';
			$note = 'Root shell of this server.';
		} elseif ($parent->getClass() === 'client') {
			if ($parent->isDisabled('shell') || !$parent->shell) {
				$this->printMessage("Shell access is disabled for this account. The administrator can enable it on the 'Shell Access' page of the client.");

				return;
			}

			$user = $parent->username;
			$note = ($parent->shell === '/usr/bin/lxjailshell')
				? "Jailed shell: only the home directory of {$user} is visible."
				: "Shell of {$user}.";
		} else {
			$this->printMessage("No terminal for this object.");

			return;
		}

		if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/', (string)$user)) {
			$this->printMessage("Invalid user.");

			return;
		}

		// one-time token, read and deleted by sbin/kn-terminal
		$tok = bin2hex(random_bytes(24));
		$dir = "{$sgbl->__path_program_root}/session";

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}

		// drop tokens nobody used
		foreach ((array)glob("{$dir}/term_*") as $old) {
			if (is_file($old) && (filemtime($old) < time() - 300)) {
				@unlink($old);
			}
		}

		file_put_contents("{$dir}/term_{$tok}", "{$user}|" . (time() + 60) . "\n");
		chmod("{$dir}/term_{$tok}", 0600);

		$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?>
<div class="kn-terminal">
	<div class="kn-terminal-bar"><?= $h($note) ?> <span>Reload the page for a new session.</span></div>
	<iframe class="kn-terminal-frame" src="/terminal/?arg=<?= $h($tok) ?>" title="Terminal"></iframe>
</div>
<style>
.kn-terminal { max-width: 1200px; }
.kn-terminal-bar { margin: 0 0 8px; font-size: 13px; color: var(--kn-text-2, #475569); }
.kn-terminal-bar span { color: var(--kn-text-3, #64748b); }
.kn-terminal-frame { width: 100%; height: min(70vh, 640px); border: 1px solid var(--kn-border, #ddd); border-radius: 10px; background: #000; }
</style>
<?php
	}

	function printMessage($msg)
	{
		print("<div style='padding: 16px'>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</div>");
	}

	static function initThisObjectRule($parent, $class) { return "sshclient"; }

	static function initThisObject($parent, $class, $name = null) { return "sshclient"; }
}
