<?php
/**
    PRails Web Framework
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

class Logger {
	private $log_file = "";
	
	public function __construct($log_file = PROJECT_LOG) {
		$this->log_file = $log_file;
	}	
	
	///////////////////////// PUBLIC METHODS /////////////////////////////////
	public function trace($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("trace", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[TRACE]";
			$this->addToLog($tag, $msg, $bol_strace);
		}
	}
	
	public function info($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("info", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[INFO]";
			$this->addToLog($tag, $msg, $bol_strace);
		}
	}
	
	public function debug($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("debug", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[DEBUG]";
			$this->addToLog($tag, $msg, $bol_strace);
		}
	}
	
	public function warn($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("warn", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[WARNING]";
			$this->addToLog($tag, $msg, $bol_strace);
		}
	}
	
	public function error($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("error", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[ERROR]";
			$this->addToLog($tag, $msg, $bol_strace);
			if (ERROR_NOTIFICATION) {
				fmail(ERROR_MAIL, "Error in project ".PROJECT_NAME, $msg."\n\n".$this->getStacktrace());
			}
		}
	}
	
	public function fatal($msg, $bol_strace = false) {
		global $ARR_LOGGER_ENABLED_PROPERTIES;
		if (in_array("fatal", $ARR_LOGGER_ENABLED_PROPERTIES)) {
			$tag = "[FATAL]";
			$this->addToLog($tag, $msg, $bol_strace);
			if (ERROR_NOTIFICATION) {
				fmail(ERROR_MAIL, "Fatal Error in project ".PROJECT_NAME, $msg."\n\n".$this->getStacktrace());
			}
			throw new Exception("[FATAL] ".nl2br($msg));
		}
	}
	
	
	////////////////////// PRIVATE METHODS ///////////////////////////////////
	private function addToLog($tag, $msg, $bol_strace) {
		if ($bol_strace) {
			$trace = "\n" . $this->getStacktrace() . "\n";
		} else {
			$trace = "";
		}
		
		$line = implode(" ", Array(
			$this->getTime(),
			$tag,
			$msg,
			$trace
		)) . "\n";
		
		$fp = fopen($this->log_file, "a+");
		if ($fp) {
    		fwrite($fp, $line);
    		fclose($fp);
		} else {
		    die($line."\nError writing to log file. Please check permissions.<br/>");
		}
	}
	
	private function getTime() {
		return "[".date("Y-m-d H:i:s")."]";
	}
	
	private function getStacktrace() {
		$trace = array_reverse(debug_backtrace());
		$str_trace = "";
		$func = "";
		foreach ($trace as $val) {
			$str_trace .= if_set($val["file"], "[PHP core function]")." on line ".$val["line"];
			if ($func) {
				$str_trace .= " in function ".$func;
			} 
			if ($val["function"] == "include" || $val["function"] == "require" || 
				$val["function"] == "include_once" || $val["function"] == "require_once") {
				$func = "";		
			} else {
				$func = $val["function"] . "(";
				if (isset($val["args"][0])) {
					$func .= " ";
					$comma = "";
					foreach ($val["args"] as $arg) {
						$func .= $comma . tostring($arg);
						$comma = ", ";
					}
					$func .= " ";
				}
				$func .= ")";
			}
			$str_trace .= "\n";
		}
		
		return $str_trace;
	}
}
?>
