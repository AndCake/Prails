#!/usr/bin/env php -q
<?php

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

$in = $argv[1];
if ($argc < 2) $in = STDIN;
$fp = fopen($in, "r");
while (!feof($fp)) {
	$data = fread($fp, 1024);
	if ($data[strlen($data)-1] == '=') $data .= fread($fp, 1);
	echo decode($data);	
}
fclose($fp);

?>
