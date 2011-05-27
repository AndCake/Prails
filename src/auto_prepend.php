<?php
/**
    Prails Web Framework
    Copyright (C) 2010  Robert Kunze

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
	
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	register_shutdown_function("error_alert");
    ini_set('display_errors', 0);
    
    $logFile = fopen("log/framework.log", "a+");
	
	function error_alert() {
		if (is_null($e = error_get_last()) === false) {
			if (in_array($e["type"], Array(1, 2, 4, 16, 32, 64, 128))) {
				$arr_errors = Array(1 => "Fatal Runtime Error", 2=>"Runtime Warning", 4 =>"Parse Error", 16 =>"Fatal Core Error during PHP startup", 32=>"Core Warning during PHP startup", 64=>"Fatal Compile-time Error", 128 => "Compile-time Warning");
				$arr_file = file($e["file"]);
				$line = $arr_file[$e["line"] - 1];
				$cLine = $e["line"] - 1;
				$found = false; 
				while (!$found && $cLine > 1) {
					$found = (preg_match("/\\s*function ([^(]+)\\(\\) {/", $arr_file[$cLine--], $arr_match) > 0);
				}
				if (preg_match("/\\s*class ([a-zA-Z_0-9]+)\\s+/", $arr_file[1], $match) == 0) {
					preg_match("/\\s*class ([a-zA-Z_0-9]+)\\s+/", $arr_file[2], $match);
				}
				$class = preg_split("/[0-9]+/", $match[1]);

				$module = $class[0];
				switch ($class[1]) {
					case "Handler":
						$type = "event";
						$type = "module '".$module."', ".$type;
						break;
					case "Data":
						$type = "data query";
						$type = "module '".$module."', ".$type;
						break;
					default:
						$type = "library";
						$type = "library '".$module."', function ";
						break;
				}				
				$function = $arr_match[1];
				$error = $arr_errors[$e["type"]];
				global $logFile;
				if ($found) {
					$rline = ($e["line"] - 1) - ($cLine + 1);
					if (in_array($e["type"], Array(2, 32, 128))) {
						fwrite($logFile, "[".date("Y-m-d H:i:s")."] [WARNING] ".$e["message"]." in ".$type." '".$function."' in line ".$rline.": ".$arr_file[$e["line"] - 2]."===>".$line."".$arr_file[$e["line"]]);
					} else {
						echo "<code><b>".$error.": </b>".$e["message"]." <b>in Module '".$module."', ".$type." '".$function."' in line ".$rline."</b>: <br/>". 
							 htmlspecialchars($arr_file[$e["line"] - 2])."<br/><span style='color:red;border-bottom:1px dashed red;'>".htmlspecialchars($line)."</span><br/>".htmlspecialchars($arr_file[$e["line"]])."</code>";
					}
				} else {
					if (in_array($e["type"], Array(2, 32, 128))) {
						fwrite($logFile, "[".date("Y-m-d H:i:s")."] [WARNING] ".$e["message"]." in ".$type." '".$function."' in line ".$rline.": ".$arr_file[$e["line"] - 2]."===>".$line."".$arr_file[$e["line"]]);
					} else {
						echo "<code><b>".$error.": </b>".$e["message"]." <b>in ".(strlen($module)>0?$type." ".$module:"")." in line ".$e["line"].":</b> <br/><code>".
							 htmlspecialchars($arr_file[$e["line"] - 2])."<br/><span style='color:red;border-bottom:1px dashed red;'>".htmlspecialchars($line)."</span><br/>".htmlspecialchars($arr_file[$e["line"]])."</code>";
					}
				}
			} 
		}
	}

?>
