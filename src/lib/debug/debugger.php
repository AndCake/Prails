<?php
class Debugger {
	static $keepRunning = true;
	
	static function wait($vars = null) {
		$trace = debug_backtrace();
		$tr = $trace[($vars === null ? 2 : 1)];
		$tr["file"] = $trace[($vars === null ? 1 : 0)]["file"];
		$tr["line"] = $trace[($vars === null ? 1 : 0)]["line"];
//		echo $tr["function"].":".$tr["line"]." (class ".$tr["class"].")<br/>";
		$waitForStep = true;
		while (!Debugger::$keepRunning && $waitForStep) {
			$todo = @file_get_contents("cache/debugger.do");
			$todo = trim($todo);
			if ($todo === false || $todo == "run") {
				$waitForStep = false;
				Debugger::$keepRunning = true;
			} else if ($todo == "stop") {
				die();
			} else if ($todo == "next") {
				$waitForStep = false;
				Debugger::$keepRunning = false;
				file_put_contents("cache/debugger.do", "pause");
				$trace = $tr;
				if (!is_array($vars)) $vars = Array();
				$trace["variables"] = array_merge($vars, Array("POST" => $_POST, "GET" => $_GET));
				file_put_contents("cache/debugger.state", json_encode($trace));
			}
			sleep(1);
		}
	}
	
	static function breakpoint() {
		set_time_limit(0);
		Debugger::$keepRunning = false;
		Debugger::wait();
	}
}
?>