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
class Cacheable {
	
	private $shmId = Array();
	private $shmLen = 0;
	private $shmMode = false;
	private $cachePath = "cache/shm/";
	
	function __construct() {
		$this->_init();
	}
	
	function _init() {
		if (!is_dir($this->cachePath)) {
			@mkdir($this->cachePath);
		}
		
		array_push($this->shmId, "fs");
		if (!is_dir($this->cachePath."fs"))	@mkdir($this->cachePath."fs");
		$this->shmLen = count($this->shmId);
		
		if (!$this->_exists($this->shmId[0], 101)) {
			$this->_set($this->shmId[0], 101, Array());
		}
	}
	
	function _exists($pos, $var) {
		$name = $this->cachePath . $pos . "/" . $var;
		if (DBCACHE_ENABLED === false) {
			return false;
		}
        return (file_exists($name));
	}
	
	function _set($pos, $var, $val, $tryAgain = true) {
        $name = $this->cachePath . $pos . "/" . $var;
		if (DBCACHE_ENABLED === false) {
			return;
		}
		$val = serialize($val);
		
		// atomically write data to disc
		$temp = tempnam("cache", "temp");
		if (!($fp = @fopen($temp, "wb"))) {
			$temp = "cache/" . uniqid("temp");
 			if (!($fp = @fopen($temp, 'wb'))) { 
            	trigger_error("Cacheable::_set() : error writing temporary file '".$temp."'", E_USER_WARNING); 
            	return false; 
         	}
		}
		fwrite($fp, $val);
		fclose($fp);
		
		if (!@rename($temp, $name)) {
			@unlink($name);
			@rename($temp, $name);
		}
		@chmod($name, 0644); 
	}
	
	function _get($pos, $var) {
        $name = $this->cachePath . $pos . "/" . $var;
		return @unserialize(file_get_contents($name));
	}
	
	function _remove($pos, $var) {
		if ($this->_exists($pos, $var)) {
            $name = $this->cachePath . $pos . "/" . $var;
			@unlink($name);
		}
	}
	
	function __sleep(){
    }
    	
	function __destruct(){
	}

	function __wakeup(){
    }
    
    function flush() {
   		foreach ($this->shmId as $shm) {
   			removeDir($this->cachePath.$shm, true);
   		}
    }
    	
	protected function cleanCacheBlock($str_query, $prefix = false) {
		if (!$prefix) $prefix = $this->prefix;
		if (DBCACHE_ENABLED === false) {
			return;
		}
		$tableReference = $this->_get($this->shmId[0], 101);
		preg_match_all("/ [a-zA-Z0-9_.]*(".$this->prefix."[a-z0-9A-Z_]+) /i", $str_query, $arr_matches);
		if (count($arr_matches[0]) > 0) {
			// loop through each match
			foreach ($arr_matches[1] as $table) {
			    if (is_array($tableReference[$table])) {
			    	foreach ($tableReference[$table] as $entry) {
			    		$this->_remove($this->shmId[$entry % $this->shmLen], $entry);
			    	}
			    	$tableReference[$table] = false;
			    }
			}
		}
		
	   	$this->_set($this->shmId[0], 101, $tableReference);
	}
	
	protected function isCached($str_query, $cacheTime = 0) {
		$id = crc32($str_query);
		return $this->_exists($this->shmId[$id % $this->shmLen], $id);
	}
	
	protected function getCached($str_query) {
		if ($this->isCached($str_query)) {
			$id = crc32($str_query);
			return $this->_get($this->shmId[$id % $this->shmLen], $id);
		} else {
			return Array();
		}
	}
	
	protected function setCache($str_query, $arr_result, $prefix = false) {
		if (!is_array($arr_result)) return;
		if (DBCACHE_ENABLED === false) {
			return;
		}
		if (!$prefix) $prefix = $this->prefix;
		$id = crc32($str_query);
		$this->_set($this->shmId[$id % $this->shmLen], $id, $arr_result);
		if ($this->_exists($this->shmId[0], 101)) {
			$tableReference = $this->_get($this->shmId[0], 101);
		} else {
			$tableReference = Array();
		}
		
		// get affected tables
		preg_match_all("/ [a-zA-Z0-9_.]*(".$prefix."[a-z0-9A-Z_]+) /i", $str_query, $arr_matches);
		if (count($arr_matches[0]) > 0) {
		    // loop through each
		    foreach ($arr_matches[1] as $table) {
		    	if (!is_array($tableReference[$table])) {
		    		$tableReference[$table] = Array();
		    	}
		    	array_push($tableReference[$table], crc32($str_query));
		    }
		}
		$this->_set($this->shmId[0], 101, $tableReference);		
	}
	
}
?>