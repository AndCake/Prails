<?php
class Debugger {
	static $keepRunning = true;
	
	static function wait($vars = null) {
		if (Debugger::$keepRunning) return;
		$trace = debug_backtrace();
		$tr = $trace[($vars === null ? 2 : 1)];
		$tr["file"] = $trace[($vars === null ? 1 : 0)]["file"];
		$tr["line"] = $trace[($vars === null ? 1 : 0)]["line"];
		$waitForStep = true;
		while (!Debugger::$keepRunning && $waitForStep) {
			$todo = @file_get_contents("cache/debugger.do");
			$todo = trim($todo);
			if ($todo === false || $todo == "run" || $todo == "") {
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
		$trace = debug_backtrace();
		$tr = $trace[0];
		$trace = $tr;
		$trace["line"] = $trace["line"] + 1;
		if (!is_array($vars)) $vars = Array();
		$trace["variables"] = array_merge($vars, Array("POST" => $_POST, "GET" => $_GET));
		file_put_contents("cache/debugger.state", json_encode($trace));
		Debugger::wait();
	}
}
?>