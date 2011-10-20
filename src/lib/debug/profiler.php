<?php
class Profiler {
	
	private $name;
	
	function Profiler($name) {
		$this->name = $name;
	}
	
	function logEvent($type) {
		if (PROFILING_ENABLED === true) {
			global $startTime;
			$stack = array_slice(debug_backtrace(), 1, 1);
			$entry = "[".date("Y-m-d H:i:s", $_SERVER["REQUEST_TIME"])."] time:".(microtime(true) - $startTime)." mem:".memory_get_usage()."B [".$type."] ".$stack[0]["file"]."(".$stack[0]["function"].")\n";
			file_put_contents("log/profiler_".$this->name.".log", $entry, FILE_APPEND | LOCK_EX);
		}
	}
	
	function processEvents($type, $callback) {
		if (!file_exists("log/profiler_".$this->name.".log")) return false;
		$fp = fopen("log/profiler_".$this->name.".log", "r");
		$i = 0;
		while (!feof($fp)) {
			$line = fgets($fp, 4096);
			preg_match('/^\[([^\]]+)\] time:([^\s]+) mem:([^B]+)B \[([^\]]+)\] ([^\(]+)\(([^\)]+)\)$/mi', $line, $match);
			if ($match[4] == $type) {
				call_user_func($callback, Array(
					"date" => $match[1],
					"time" => $match[2],
					"memory" => $match[3],
					"file" => $match[5],
					"function" => $match[6]
				));
				$i++;
			}
		}
		fclose($fp);
		return $i;
	}
	
	function processAllEvents($callback) {
		if (!file_exists("log/profiler_".$this->name.".log")) return false;
		$fp = fopen("log/profiler_".$this->name.".log", "r");
		$i = 0;
		while (!feof($fp)) {
			$line = fgets($fp, 4096);
			preg_match('/^\[([^\]]+)\] time:([^\s]+) mem:([^B]+)B \[([^\]]+)\] ([^\(]*)\(([^\)]*)\)$/mi', $line, $match);
			call_user_func($callback, Array(
				"date" => $match[1],
				"time" => $match[2],
				"memory" => $match[3],
				"type" => $match[4],
				"file" => $match[5],
				"function" => $match[6]
			));
			$i++;
		}
		fclose($fp);
		return $i;
	}
}
?>