<?php
/**
    Prails Web Framework
    Copyright (C) 2012  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
$path = str_replace(dirname($_SERVER["SCRIPT_NAME"])."/", '', $_SERVER["REQUEST_URI"]);
$ht = file_get_contents(".htaccess");
$start = strpos($ht, "<IfModule rewrite_module>") + strlen("<IfModule rewrite_module>");
$end = strpos($ht, "</IfModule>", $start);
$content = substr($ht, $start, $end - $start);
$requestFileName = basename($path);


$lines = explode("\n", $content);
$cond = false;
$recond = 0;
foreach ($lines as $line) {
	if (preg_match('/RewriteCond\\b(.*)$/mi', $line, $match)) {
		$recond++;
		$parts = explode(" ", trim($match[1]));
		if (preg_match('/%\\{([^\\}]+)\}/m', $parts[0], $varName)) {
			if (strpos($varName[1], ":") !== false) {
				$varParts = explode(":", $varName[1]);
				if (strtoupper($varParts[0]) == "HTTP") {
					$varName[1] = strtoupper("HTTP_".preg_replace('/[^a-z]/mi', '_', $varParts[1]));
				}
			}
			$parts[0] = str_replace($varName[0], ($varName[1] == "REQUEST_FILENAME" ? $requestFileName : $_SERVER[$varName[1]]), $parts[0]);
			if ($parts[3] == "[OR]") {
				if ($parts[1] == "!-f" || $parts[1] == "!-d") {
					$cond = $cond || true;
				} else {
					$cond = $cond || (preg_match('/'.str_replace('/', '\\/', $parts[2]).'/', $parts[0]));
				}
			} else {
				if ($recond == 1) $cond = true;
				if ($parts[1] == "!-f" || $parts[1] == "!-d") {
					$cond = $cond && true;
				} else {
					$cond = $cond && (preg_match('/'.str_replace('/', '\\/', $parts[2]).'/', $parts[0]));
				}	
			}
		}
	} else if (preg_match('/RewriteRule\\b(.*)$/mi', $line, $match)) {
		$parts = explode(" ", trim($match[1]));
		if ((($recond > 0 && $cond) || $recond == 0) && preg_match('/'.str_replace('/', '\\/', $parts[0]).'/', $path, $match)) {
			preg_match_all('/\\$[0-9]+/m', $parts[1], $matches);
			foreach ($matches[0] as $m) {
				$parts[1] = str_replace($m, $match[intval(str_replace('$', '', $m))], $parts[1]);
			}
			if (strpos($parts[2], "L") !== false) {
				$url = parse_url($parts[1]);
				$url["query"] = str_replace("?", "&", $url["query"]);
				$parameters = explode("&", $url["query"]);
				foreach ($parameters as $param) {
					list($name, $value) = explode("=", $param);
                                        $name = urldecode($name);
                                        if ($name[0] == "?") $name = substr($name, 1);
                                        $name = explode("[", str_replace("]", "", $name));
                                        $nn = &$_GET;
                                        foreach ($name as $n) {
                                                $nn[$n] = Array();
                                                $nn = &$nn[$n];
                                        }
                                        $nn = urldecode($value);
				}
				header("HTTP/1.1 200 OK");
				require("index.php");
				die();
			}
		}
		$cond = false;
		$recond = 0;
	}
}
?>
