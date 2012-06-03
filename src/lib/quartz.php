<?php
/**
 Prails Web Framework
 Copyright (C) 2011  Robert Kunze
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
class Quartz {
	/**
	 * checks if Quartz can be used on current server.
	 */
	static function isAvailable() {
		$disabled = explode(', ', ini_get('disable_functions'));
  		if(in_array('exec', $disabled)) return false;
		if (!Quartz::_checkApp("crontab")) return false;
		return Quartz::_getFirstAvailable() != false;
	}

	/**
	 * schedules a new job, if it already existed, the new one isn't added
	 * @param ARRAY $time array ("min" => "0", "hour" => "5", "day" => "*", "month" => "*", "week" => "*")
	 * @param STRING $event event's name (for example user:notify)
	 * @return job's ID if the job has been scheduled successfully, else FALSE
	 */
	static function addJob($time, $event, $id = null) {
		global $SERVER, $log;
		if (!Quartz::isAvailable()) {
			$log->error("Tried to schedule job ".$event." but Quartz found no full cron support. Please check if crontab and lynx, w3m or wget is available");
			return false;
		}
		$time = Quartz::_normalizeTime($time);

		if (!$id) $id = md5(implode($time).$event);
		
		exec("crontab -l > cache/temp.cron");
		$cron = file("cache/temp.cron");
		foreach ($cron as $line) {
			if (preg_match('@\s*#\s*[$]id:\s*([a-zA-Z0-9]+)\s*$@', $line, $match)) {
				if ($id == $match[1]) { 
					return $id;
				}
			}
		}
		$base = dirname($_SERVER["SCRIPT_FILENAME"]);
		$mail = ">> ".$base."/log/quartz.log 2>&1";
		$prog = (Quartz::_getFirstAvailable())." '".str_replace("://", "://".$_SERVER["PHP_AUTH_USER"].":".$_SERVER["PHP_AUTH_PW"]."@", $SERVER)."?event=".$event."&prailsjob'";
		$cron[] = $time["min"]." ".$time["hour"]." ".$time["day"]." ".$time["month"]." ".$time["week"]." ".$prog." ".$mail." # \$id: ".$id;
		
		file_put_contents("cache/temp.cron", implode("\n", $cron)."\n");
		exec("crontab cache/temp.cron");
		unlink("cache/temp.cron");
		
		return $id;
	}
	
	/**
	 * removes a scheduled job
	 * @param MIXED $idTime job's ID or a time array
	 * @param MIXED $event event's name in case no ID has been specified
	 * @return TRUE if successfully removed, else false
	 */
	static function removeJob($idTime, $event = false, $id = null) {
		if (!Quartz::isAvailable()) {
			$log->error("Tried to remove scheduled job ".$event." but Quartz found no full cron support.");
			return false;
		}
		if (!$event && !$id) {
			$id = $idTime; 
		} else {
			$idTime = Quartz::_normalizeTime($idTime);
			if (!$id) $id = md5(implode($idTime).$event);
		}
		exec("crontab -l > cache/temp.cron");
		$cron = file("cache/temp.cron");
		$file = "";
		$found = false;
		foreach ($cron as $line) {
			if (preg_match('@\s*#\s*[$]id:\s*([a-zA-Z0-9]+)\s*$@', $line, $match)) {
				if ($id != $match[1]) { 
					$file .= $line."\n";
				} else {
					$found = true;
				}
			}
		}
		if ($found) {
			file_put_contents("cache/temp.cron", $file);
			exec("crontab cache/temp.cron");
		}
		unlink("cache/temp.cron");
		
		return $found;
	}
	
	static function getJob($id) {
		$lineData = null;
		exec("crontab -l > cache/temp.cron");
		$cron = file("cache/temp.cron");
		$file = "";
		$found = false;
		foreach ($cron as $line) {
			if (preg_match('@\s*#\s*[$]id:\s*([a-zA-Z0-9]+)\s*$@', $line, $match)) {
				if ($id == $match[1]) {
					$lineItems = explode(" ", $line);
					$lineData = Array(
						"min" => $lineItems[0],
						"hour" => $lineItems[1],
						"day" => $lineItems[2],
						"month" => $lineItems[3],
						"week" => $lineItems[4]
					);
					break;
				}
			}
		}
		unlink("cache/temp.cron");
		return $lineData;
	}
	
	static function _getFirstAvailable() {
        exec("whereis php", $list);
        if (strpos(implode("\n", $list), "php ") === false) return null;
        
		return "/usr/bin/env php -q ".__FILE__;
	}
	
	static function _checkApp($name) {
		$result = exec($name." 2>&1", $result, $returnValue);
		return $returnValue != 127;
	}
	
	static function _normalizeTime($time) {
		$entries = Array("min", "hour", "day", "month", "week");
		if (!is_array($time)) $time = Array();
		$foundStar = false;
		foreach ($entries as $entry) {
			if ($foundStar && ($entry == "day" || $entry == "month" || $entry == "hour")) {
				$time[$entry] = "*";
			} else if (!isset($time[$entry]) || strlen($time[$entry]) <= 0) { 
				$time[$entry] = "*";
				$foundStar = true;
			} else if ($time[$entry][0] == "*") {
				$foundStar = true;
			} else {
				$time[$entry] = preg_replace('/0([0-9]+)/mi', '\1', $time[$entry]);
			}
		}
		return $time;
	}
}
if (defined('STDIN')) { 
	// job is to be executed!
	if ($argc > 1) {
		preg_match('/[^?]+\?event=(.*)/mi', $argv[1], $match);
		$opts = array(
			'http'=>array(
				'method'  => "GET",
				'timeout' => 3600
			)
		);
		$context = stream_context_create($opts);
		$result = file_get_contents($argv[1], NULL, $context);
		echo "[".date("Y-m-d H:i:s")."] [".$match[1]."] ".$result."\n";
	} else {
		echo "[".date("Y-m-d H:i:s")."] ERROR - unable to find job to be executed!\n";
	}
}
?>
