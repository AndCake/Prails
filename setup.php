#!/usr/bin/env php -q
<?php
	function renderHTML() {
		?><style type="text/css">body{color:white;}body *{color:black;}</style>
		<h1>Prails Setup</h1><?php if (!is_writable("./")) {?><p style="color:red;">The current directory is not writable. Please make sure it is.</p><? } ?> 
		<p>Please enter the directory Prails should be installed to.</p>
		<form method="post" action=""><div class="form-entry"><label for="dir">Target:</label>
		<input type="text" name="dir" id="dir" value="<?=(isset($_POST["dir"]) ? $_POST["dir"] : "./prails")?>"/></div>
		<button type="submit">Install</button>
		</form><script type="text/javascript">document.getElementById('dir').focus();</script><?php
	}

	function renderCli() {
		echo "Prails Setup\n============\n\n";
		echo "Please enter the directory Prails should be installed to. \nSimply press enter to confirm the default directory, else type in the target directory name and press enter.\n\n";
		echo "Target [./prails]: ";
		define('STDIN',fopen("php://stdin","r"));
		$dir = trim(fgets(STDIN, 256));
		$dir = preg_replace('/[^a-zA-Z0-9_\-.\/]/', '', $dir);
		if (strlen($dir) <= 0) {
			$dir = "./prails";
		}
		startInstall($dir);
	}

	function decode($str) {
		$res = "";
		for ($i = 0; $i < strlen($str); $i++) {
			$c = ord($str[$i]);
			if ($str[$i] == '=') {
				$i++;
				$c = ord($str[$i]);
				$c -= 64;
			}
			if ($c < 0) $c = 256 - $c;
			$res .= chr($c);
		}

		return $res;
	}

	function startInstall($dir) {
		$dir = preg_replace('/[^a-zA-Z0-9_\-.\/]/', '', $dir);
		$new = false;
		if (!file_exists($dir)) {
			$new = true;
		}
		// do the actual stuff...
		$tp = fopen("prails.tar.bz2", "w+");
		$fp = fopen(__FILE__, "r");
		fseek($fp, __COMPILER_HALT_OFFSET__+1);
		$i = 0;
		while (!feof($fp)) {
			$buffer = fread($fp, 10240);
			if ($buffer[strlen($buffer)-1] == '=') $buffer .= fread($fp, 1);
			fwrite($tp, decode($buffer));
		}
		fclose($tp);
		fclose($fp);
		exec("tar xvjf prails.tar.bz2");
		if (!file_exists($dir) || !$new) {
			exec("mv prails ".$dir);
		}
		unlink("prails.tar.bz2");
		if (empty($_SERVER["SHELL"])) {
			$server = "http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
			$server = substr($server, 0, -strlen(basename($server)));
			echo "<meta http-equiv='refresh' content='0; URL=".$server.$dir."'/>";
			die();
		} else {
			echo "Installation complete. In order to run the setup, please visit the ".$dir." directory with your browser.\n";
			die();
		}
	}
	if (empty($_SERVER["SHELL"])) {
		// show the input form page
		if (!$_POST["dir"]) {
			renderHTML();
			die();
		} else {
			startInstall($_POST["dir"]);
		}
	} else {
		// show the CLI input mask
		renderCli();
	}
__HALT_COMPILER();
