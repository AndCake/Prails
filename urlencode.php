#!/usr/bin/env php -q
<?php
	$fp = fopen($argv[1], "r");
	while (!feof($fp)) {
		$data = fread($fp, 1024);
		$res = "";
		for ($i = 0; $i < strlen($data); $i++) {
			$c = ord($data[$i]);
			switch ($c) {
				case 0: case 9: case 13: case 10: case 61: case 46: case 60: case 62:
					$res .= "=";
					$c = ($c + 64) % 256;
			}
			$res .= chr($c);
		}
		echo $res;
	}
	fclose($fp);
?>