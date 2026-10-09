<?php

// KloxoNext - Postfix queue (postqueue -j / postsuper / postcat)
class mailqueue__postfix extends lxDriverClass
{
	static function QueueFlush()
	{
		exec("postqueue -f >/dev/null 2>&1");
	}

	static function QueueDelete($list)
	{
		foreach ((array)$list as $id) {
			if (preg_match('/^[A-Za-z0-9]+$/', $id)) {
				exec("postsuper -d " . escapeshellarg($id) . " >/dev/null 2>&1");
			}
		}
	}

	static function readSingleMail($name)
	{
		if (!preg_match('/^[A-Za-z0-9]+$/', $name)) {
			return array('message' => null, 'log' => null);
		}

		$ret['message'] = shell_exec("postcat -hq " . escapeshellarg($name) . " 2>/dev/null");

		$log = file_exists('/var/log/maillog') ? '/var/log/maillog' : '/var/log/mail.log';
		$ret['log'] = shell_exec("grep -h " . escapeshellarg($name) . " " . escapeshellarg($log) . " 2>/dev/null | tail -5");

		return $ret;
	}

	static function readMailqueue()
	{
		$ret = null;
		$i = 0;

		foreach (explode("\n", (string)shell_exec("postqueue -j 2>/dev/null")) as $line) {
			$q = json_decode($line, true);

			if (!is_array($q)) {
				continue;
			}

			$to = array();
			$remote = false;

			foreach ((array)($q['recipients'] ?? array()) as $r) {
				$to[] = $r['address'];
				$remote = $remote || !empty($r['delay_reason']);
			}

			$ret[$i]['nname'] = $q['queue_id'];
			$ret[$i]['type'] = $remote ? 'remote' : 'local';
			$ret[$i]['to'] = implode(', ', $to);
			$ret[$i]['from'] = $q['sender'] ?? '';
			$ret[$i]['date'] = isset($q['arrival_time']) ? date('Y-m-d H:i:s', $q['arrival_time']) : '';
			$ret[$i]['size'] = $q['message_size'] ?? 0;
			$ret[$i]['subject'] = '';
			$i++;
		}

		return $ret;
	}
}
