<?php

include_once "driver/pserver/ffile__commonlib.php";

class ffile__linux extends lxDriverClass
{
	function dbactionUpdate($subaction)
	{
		global $gbl, $sgbl, $login, $ghtml;

		$this->aux = new Ffile__common(null, null, $this->nname);
		$this->aux->main = $this->main;

		if ($this->main->isOn('readonly')) {
			throw new lxException($login->getThrow('file_manager_is_readonly'));
		}

		$chownug = "{$this->main->__username_o}:{$this->main->__username_o}";

		switch ($subaction) {
			case "fancyedit":
			case "edit":
				lxuser_put_contents($chownug, $this->main->getFullPath(), $this->main->content);
				lxuser_return($chownug, "dos2unix", $this->main->getFullPath());
			//	lxshell_return("dos2unix", $this->main->getFullPath());
				lxuser_chmod($chownug, $this->main->getFullPath(), "0644");

				break;

			case "upload_s":
				$filename = $this->aux->uploadDirect();

				if (preg_match('/^.*\.(pl|cgi|py|rb)$/i', $filename)) {
					lxuser_chmod($chownug, $filename, "0755");
				} else {
					lxuser_chmod($chownug, $filename, "0644");
				}

				lxfile_generic_chown($filename, $chownug);

				break;

			case "rename":
				$this->aux->reName();

				break;

			case "paste":
				$this->aux->filePaste();

				break;

			case "perm":
				$path = $this->main->getFullpath();

				$perm = $this->main->newperm;
				$perm = 0 . $perm;

				// KloxoNext - only an octal mode; the path is passed as one quoted argument
				if (!preg_match('/^0?[0-7]{3,4}$/', (string)$perm)) {
					throw new lxException($login->getThrow('invalid_permission'), '', $perm);
				}

				$qpath = escapeshellarg($path);

				if ($this->main->isOn('recursive_f')) {
					if ($this->main->target_f === 'all') {
						exec("chmod -R {$perm} -- {$qpath}");
					} elseif ($this->main->target_f === 'dir') {
						exec("find {$qpath} -type d -exec chmod {$perm} {} +");
					} elseif ($this->main->target_f === 'file') {
						exec("find {$qpath} -type f -name \"*.*\" -exec chmod {$perm} {} +");
					}
				} else {
					exec("chmod {$perm} -- {$qpath}");
				}

				break;

			case "own":
				$path = $this->main->getFullpath();

				$user = $this->main->user_f;
				$group = $this->main->group_f;

				// KloxoNext - account names only; the path is passed as one quoted argument
				$namere = '/^[A-Za-z0-9_][A-Za-z0-9._-]*$/';

				if (!preg_match($namere, (string)$user) || !preg_match($namere, (string)$group)) {
					throw new lxException($login->getThrow('invalid_user_or_group'), '', "{$user}:{$group}");
				}

				$qpath = escapeshellarg($path);

				if ($this->main->isOn('recursive_f')) {
					exec("chown -R {$user}:{$group} -- {$qpath}");
				} else {
					exec("chown {$user}:{$group} -- {$qpath}");
				}

				break;

			case "newdir":
				$path = $this->aux->newDir();
				lxfile_unix_chown($path, $chownug);

				break;

			case "content":
				if ($this->main->is_image()) {
					$this->aux->resizeImage();
				} else {
					throw new lxException($login->getThrow('can_not_save_content'));
				}

				break;

			case "thumbnail":
				$this->aux->createThumbnail();

				break;

			case "convert_image":
				$this->aux->convertImage();

				break;

			case "zip_file":
				$zipfile = $this->aux->zipFile();
				break;

			case "filedelete":
				$this->aux->moveAllToTrash();

				break;

			case "filerealdelete":
				$this->aux->fileRealDelete();

				break;

			case "restore_trash":
				$this->aux->restoreTrash();
				
				break;

			case "clear_trash":
				$this->aux->clearTrash();

				break;

			case "download_from_http":
				$fullpath = $this->aux->downloadFromHttp();
				lxfile_unix_chown($fullpath, $chownug);

				break;

			case "download_from_ftp":
				$fullpath = $this->aux->downloadFromFtp();
				lxfile_unix_chown($fullpath, $chownug);

				break;

			case "zipextract":
				$dir = $this->aux->zipExtract();

				break;
		}
	}
}

