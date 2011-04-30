<?php
class Cacheable {
	
	private $shmId = null;
	
	function __construct() {
        $key = ftok(realpath("cache"), 'p');
        $this->shmId = shm_attach($key, DB_CACHE_SIZE);
        if(!$this->shmId)
            die('Unable to create shared memory segment');
        $counter = shm_get_var($this->shmId, 1);
        if (!$counter) $counter = 0;		
        shm_put_var($this->shmId, 1, $counter + 1);
	}
	
	function __sleep(){
        shm_detach($this->shmId);
        shm_put_var($this->shmId, 1, shm_get_var($this->shmId, 1) - 1);
    }
    	
	function __destruct(){
        shm_detach($this->shmId);
        shm_put_var($this->shmId, 1, shm_get_var($this->shmId, 1) - 1);
	}

	function __wakeup(){
        $key = ftok(realpath("cache"), 'p');
	 	$this->shmId = shm_attach($key, DB_CACHE_SIZE);
        shm_put_var($this->shmId, 1, shm_get_var($this->shmId, 1) + 1);
    }
    	
	protected function cleanCacheBlock($str_table) {
	    $name = $this->str_cachePath . $str_table;
		$tableReference = shm_get_var($this->shmId, 101);
	    if (is_array($tableReference[$str_table])) {
	    	foreach ($tableReference[$str_table] as $entry) {
	    		shm_remove_var($this->shmId, $entry);
	    	}
	    	$tableReference[$str_table] = false;
	    	shm_put_var($this->shmId, 101, $tableReference);
	    }
	}
	
	protected function isCached($str_query, $cacheTime) {
		$id = crc32($str_query);
		return shm_has_var($this->shmId, $id);
	}
	
	protected function getCached($str_query) {
		$id = crc32($str_query);
		return shm_get_var($this->shmId, $id);
	}
	
	protected function setCache($str_query, $arr_result) {
		if (!is_array($arr_result)) return;
		$id = crc32($str_query);
		shm_put_var($this->shmId, $id, $arr_result);
		$tableReference = shm_get_var($this->shmId, 101);
		
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
	}
	
}
?>