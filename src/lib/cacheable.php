<?php
class Cacheable {
	
	private $shmId = Array();
	private $shmLen = 0;
	
	function __construct() {
		$fname = "shmcache";
		$i = 0;
		do {
			touch("cache/".$fname.$i);
	        $key = ftok(realpath("cache/".$fname.$i), 'p');
	        $shm = shm_attach($key, DB_CACHE_SIZE);
	        if(!$shm) {
	        	@unlink("cache/".$fname.$i);
	        } else {
	        	array_push($this->shmId, $shm);
	        }
	        $i++;
		} while ($i < 8);
		$this->shmLen = count($this->shmId);
        foreach ($this->shmId as $shm) {
	        $counter = shm_get_var($shm, 1);
	        if (!$counter) $counter = 0;		
	        shm_put_var($shm, 1, $counter + 1);
        }
        if (!shm_has_var($this->shmId[0], 101)) {
			shm_put_var($this->shmId[0], 101, Array());
        }
	}
	
	function __sleep(){
        foreach ($this->shmId as $shm) {
			shm_detach($shm);
	        shm_put_var($shm, 1, shm_get_var($shm, 1) - 1);
        }
    }
    	
	function __destruct(){
        foreach ($this->shmId as $shm) {
			shm_detach($shm);
	        shm_put_var($shm, 1, shm_get_var($shm, 1) - 1);
        }
	}

	function __wakeup(){
		$fname = "shmcache";
		$i = 0;
		do {
	        $key = ftok(realpath("cache/".$fname.$i), 'p');
	        $this->shmId[$i] = shm_attach($key, DB_CACHE_SIZE);
        	shm_put_var($this->shmId[$i], 1, shm_get_var($this->shmId[$i], 1) + 1);
		} while ($i < 8);
    }
    	
	protected function cleanCacheBlock($str_table) {
	    $name = $this->str_cachePath . $str_table;
		$tableReference = shm_get_var($this->shmId[0], 101);
	    if (is_array($tableReference[$str_table])) {
	    	foreach ($tableReference[$str_table] as $entry) {
	    		shm_remove_var($this->shmId[$entry % $this->shmLen], $entry);
	    	}
	    	$tableReference[$str_table] = false;
	    	shm_put_var($this->shmId[0], 101, $tableReference);
	    }
	}
	
	protected function isCached($str_query, $cacheTime = 0) {
		$id = crc32($str_query);
		return shm_has_var($this->shmId[$id % $this->shmLen], $id);
	}
	
	protected function getCached($str_query) {
		if ($this->isCached($str_query)) {
			$id = crc32($str_query);
			return shm_get_var($this->shmId[$id % $this->shmLen], $id);
		} else {
			return Array();
		}
	}
	
	protected function setCache($str_query, $arr_result) {
		if (!is_array($arr_result)) return;
		$id = crc32($str_query);
		shm_put_var($this->shmId[$id % $this->shmLen], $id, $arr_result);
		if (shm_has_var($this->shmId[0], 101)) {
			$tableReference = shm_get_var($this->shmId[0], 101);
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
		shm_put_var($this->shmId[0], 101, $tableReference);		
	}
	
}
?>