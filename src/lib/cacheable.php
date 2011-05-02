<?php
class Cacheable {
	
	private $shmId = Array();
	private $shmLen = 0;
	private $shmMode = false;
	private $cachePath = "cache/shm/";
	
	function __construct() {
		if (!is_dir($this->cachePath)) {
			@mkdir($this->cachePath);
		}
		if (function_exists("shm_attach")) {
			$fname = "shmcache";
			$i = 0;
			do {
				touch($this->cachePath.$fname.$i);
		        $key = ftok(realpath("cache/".$fname.$i), 'p');
		        $shm = shm_attach($key, DB_CACHE_SIZE);
		        if(!$shm) {
		        	@unlink($this->cachePath.$fname.$i);
		        } else {
		        	array_push($this->shmId, $shm);
		        }
		        $i++;
			} while ($i < 8);
			$this->shmLen = count($this->shmId);
	        foreach ($this->shmId as $shm) {
		        $counter = $this->_get($shm, 1);
		        if (!$counter) $counter = 0;		
		        $this->_set($shm, 1, $counter + 1);
	        }
		}
		
		if ($this->shmLen > 0) {
			$this->shmMode = true;
		} else {
			array_push($this->shmId, "fs");
			if (!is_dir($this->cachePath."fs"))	@mkdir($this->cachePath."fs");
			$this->shmLen = count($this->shmId);
		}
		
		if (!$this->_exists($this->shmId[0], 101)) {
			$this->_set($this->shmId[0], 101, Array());
		}
	}
	
	function _exists($pos, $var) {
		if ($this->shmMode) {
			return shm_has_var($pos, $var);
		} else {
            $name = $this->cachePath . $pos . "/" . $var;
            return (file_exists($name));
		}
	}
	
	function _set($pos, $var, $val) {
		if ($this->shmMode) {
			shm_put_var($pos, $var, $val);
		} else {
            $name = $this->cachePath . $pos . "/" . $var;
			file_put_contents($name, serialize($val), LOCK_EX);
		}
	}
	
	function _get($pos, $var) {
		if ($this->shmMode) {
			return shm_get_var($pos, $var);			
		} else {
            $name = $this->cachePath . $pos . "/" . $var;
			return @unserialize(file_get_contents($name));
		}
	}
	
	function _remove($pos, $var) {
		if ($this->shmMode) {
			shm_remove_var($pos, $var);
		} else {
            $name = $this->cachePath . $pos . "/" . $var;
			@unlink($name);
		}
	}
	
	function __sleep(){
		if ($this->shmMode) foreach ($this->shmId as $shm) {
			shm_detach($shm);
	        shm_put_var($shm, 1, shm_get_var($shm, 1) - 1);
        }
    }
    	
	function __destruct(){
        if ($this->shmMode) foreach ($this->shmId as $shm) {
			shm_detach($shm);
	        shm_put_var($shm, 1, shm_get_var($shm, 1) - 1);
        }
	}

	function __wakeup(){
		if ($this->shmMode)  {
			$fname = "shmcache";
			$i = 0;
			do {
		        $key = ftok(realpath($this->cachePath.$fname.$i), 'p');
		        $this->shmId[$i] = shm_attach($key, DB_CACHE_SIZE);
	        	shm_put_var($this->shmId[$i], 1, shm_get_var($this->shmId[$i], 1) + 1);
			} while ($i < 8);
		}
    }
    	
	protected function cleanCacheBlock($str_table) {
		$tableReference = $this->_get($this->shmId[0], 101);
	    if (is_array($tableReference[$str_table])) {
	    	foreach ($tableReference[$str_table] as $entry) {
	    		$this->_remove($this->shmId[$entry % $this->shmLen], $entry);
	    	}
	    	$tableReference[$str_table] = false;
	    	$this->_set($this->shmId[0], 101, $tableReference);
	    }
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
	
	protected function setCache($str_query, $arr_result) {
		if (!is_array($arr_result)) return;
		$id = crc32($str_query);
		$this->_set($this->shmId[$id % $this->shmLen], $id, $arr_result);
		if ($this->_exists($this->shmId[0], 101)) {
			$tableReference = $this->_get($this->shmId[0], 101);
		} else {
			$tableReference = Array();
		}
		
		// get affected tables
		preg_match_all("/ [a-zA-Z0-9_.]*".$this->prefix."([a-z0-9A-Z_]+) /i", $str_query, $arr_matches);
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