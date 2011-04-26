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
		if (!Quartz::_checkApp("crontab")) return false;
		return Quartz::_getFirstAvailable() != false;
	}

	/**
	 * schedules a new job, if it already existed, the new one isn't added
	 * @param ARRAY $time array ("min" => "0", "hour" => "5", "day" => "*", "month" => "*", "week" => "*")
	 * @param STRING $event event's name (for example user:notify)
	 * @return job's ID if the job has been scheduled successfully, else FALSE
	 */
	static function addJob($time, $event) {
		global $SERVER, $log;
		if (!Quartz::isAvailable()) {
			$log->error("Tried to schedule job ".$event." but Quartz found no full cron support. Please check if crontab and lynx, w3m or wget is available");
			return false;
		}
		$entries = Array("min", "hour", "day", "month", "week");
		if (!is_array($time)) $time = Array();
		foreach ($entries as $entry) { if (!$time[$entry]) $time[$entry] = "*"; }
		
		$id = md5(implode($time).$event);
		
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
		$mail = "2>&1 > ".$base."/log/quartz.log";
		$prog = (Quartz::_getFirstAvailable())." '".$SERVER."?event=".$event."'";
		$cron[] = $time["min"]." ".$time["hour"]." ".$time["day"]." ".$time["month"]." ".$time["week"]." ".$prog." ".$mail." # \$id: ".$id;
		file_put_contents("cache/temp.cron", implode("\n", $cron));
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
	static function removeJob($idTime, $event = false) {
		if (!Quartz::isAvailable()) {
			$log->error("Tried to remove scheduled job ".$event." but Quartz found no full cron support.");
			return false;
		}
		if (!$event) {
			$id = $idTime; 
		} else {
			$entries = Array("min", "hour", "day", "month", "week");
			foreach ($entries as $entry) { if (!$idTime[$entry]) $idTime[$entry] = "*"; }
			$id = md5(implode($idTime).$event);
		}
		exec("crontab -l > cache/temp.cron");
		$cron = file("cache/temp.cron");
		$file = "";
		$found = false;
		foreach ($cron as $line) {
			if (preg_match('@\s*#\s*[$]id:\s*([a-zA-Z0-9]+)\s*$@', $line, $match)) {
				if ($id != $match[1]) { 
					$file .= $line;
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
	
	static function _getFirstAvailable() {
		$apps = Array("lynx -dump", "w3m -dump", "wget -O -");
		foreach ($apps as $app) {
			if (Quartz::_checkApp($app)) return $app;
		}
		return null;
	}
	
	static function _checkApp($name) {
		$result = exec($name." 2>&1");
		return !preg_match('@^\s*[a-zA-Z]+:\s*[a-zA-Z]+:\s*command not found$@', $result);
	}
}
?>