<?php
/*
 * KloxoNext 'nexus' skin - closes the shell opened by layout_begin.php
 */
$kn_js = rtrim($login->getSkinDir(), '/') . "/js/nexus.js";
?>
		</main>
	</div>
</div>
<script src="<?= htmlspecialchars($kn_js, ENT_QUOTES, 'UTF-8') ?>?v=<?= @filemtime(getcwd() . $kn_js) ?>"></script>
</body>
</html>
