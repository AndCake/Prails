<?php
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
	
	function _set($pos, $var, $val, $tryAgain = true) {
		if ($this->shmMode) {
			if ($pos !== null) { 
				if (!@shm_put_var($pos, $var, $val) && $tryAgain) {
					// sort the list of data in cache by usage rate
					//uasort($metaMap, create_function('$a, $b', 'if ($a["used"] == $b["used"]) return 0; else if ($a["used"] < $b["used"]) return -1; else return 1;'));
					// remove a random number of least used cache entries
					//$goal = rand(1, 5);foreach ($metaMap as $cvar => $cacheEntry) { $this->_remove($pos, $cvar); if ($i > $goal) break; }
					// if we were unable to set the var, try flushing and rebuilding the cache
					$this->flush();
					$this->_init();
					$this->_set($pos, $var, $val, false);
				}
//				if ($var != 101) {
//					$metaMap = shm_get_var($pos, 102);
//					if (!is_array($metaMap)) $metaMap = Array();
//					$metaMap["_".$var] = Array(
//						"used" => 1,
//						"added" => $_SERVER["REQUEST_TIME"]
//					);
//					shm_put_var($pos, 102, $metaMap);
//				}
			}
		} else {
            $name = $this->cachePath . $pos . "/" . $var;
			file_put_contents($name, serialize($val), LOCK_EX);
		}
	}
	
	function _get($pos, $var) {
		if ($this->shmMode) {
			if ($pos !== null) {
//				$metaMap = shm_get_var($pos, 102);
//				$metaMap["_".$var]["used"]++;
//				shm_put_var($pos, 102, $metaMap);
				return shm_get_var($pos, $var);
			}
			return null;			
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
    
    function flush() {
    	if ($this->shmMode) {
    		foreach ($this->shmId as $shm) {
    			shm_remove($shm);
    			shm_detach($shm);
    		}
    	} else {
    		foreach ($this->shmId as $shm) {
    			removeDir($this->cachePath.$shm, true);
    		}
    	}
    }
    	
	protected function cleanCacheBlock($str_query, $prefix = false) {
		if (!$prefix) $prefix = $this->prefix;
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