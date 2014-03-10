#!/usr/bin/env php -q
<?php
	function renderHTML() {
		?><style type="text/css">body{color:white;}body *{color:black;}</style>
		<h1>Prails Setup</h1><?php if (!is_writable("./")) {?><p style="color:red;">The current directory is not writable. Please make sure it is.</p><?php } ?> 
		<p>Please enter the directory Prails should be installed to.</p>
		<form method="post" action=""><div class="form-entry"><label for="dir">Target:</label>
		<input type="text" name="dir" id="dir" value="<?php echo(isset($_POST["dir"]) ? $_POST["dir"] : "./prails");?>"/></div>
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
		$temp = "tmp".rand(1, 99999)."/";
		@mkdir($temp, 0755, true);
		$tp = @fopen($temp."prails.tar.bz2", "w+");
		if (PHP_OS == "WINNT") 
			$xp = @fopen($temp."7za.exe", "w+");
		if (!$tp || (!$xp && PHP_OS == "WINNT")) die("Unable to extract data! Please enable write access to the current directory.\n"); 
		$fp = fopen(__FILE__, "r");
		if (PHP_OS == "WINNT")
			fseek($fp, __COMPILER_HALT_OFFSET__+1);
		else
			fseek($fp, __COMPILER_HALT_OFFSET__ + 1 + 692581);
		$i = 0;
		$mode = PHP_OS == "WINNT" ? 0 : 1;
		while (!feof($fp)) {
			$buffer = fread($fp, 10240);
			if ($buffer[strlen($buffer)-1] == '=') $buffer .= fread($fp, 1);
			$decoded = decode($buffer);
			$i += strlen($decoded);
			if ($mode == 1)
				fwrite($tp, $decoded);
			else if ($i < 587776) 
				fwrite($xp, $decoded);
			else {
				$diff = $i - 587776;
				fwrite($xp, substr($decoded, 0, strlen($decoded) - $diff));
				fwrite($tp, substr($decoded, strlen($decoded) - $diff));
				$mode = 1;
			}
		}
		fclose($tp);
		fclose($fp);
		if (PHP_OS == "WINNT") {
			fclose($xp);
			chdir($temp);
			exec("7za.exe x prails.tar.bz2");
			exec("7za.exe x prails.tar");
			chdir("..");
			unlink($temp."prails.tar");
			rename($temp."7za.exe", $temp."prails/7za.exe");
		} else 
			exec("cd ".$temp."; tar xvjf prails.tar.bz2");
		if (!file_exists($temp."prails")) die("Unable to extract Prails into directory.\n");
		if ($dir == "./" || $dir == ".") 
			exec("cd ".$temp."prails; mv * .[^.]* ../..; cd ..; rm -rf prails");
		else
			rename($temp."prails", $dir);
		unlink($temp."prails.tar.bz2");
		rmdir($temp);
		if (!$new) {
			if ($dir[strlen($dir) - 1] != '/') $dir .= "/";
			$dir .= "prails/";
		}
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
		if (!isset($_POST["dir"]) || empty($_POST["dir"])) {
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
