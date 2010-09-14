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

class SQLite {
	
	var $constructs = Array(
		"pk" => "INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL"
	);
	
	static $instance = null;
	var $arr_links = null;
   	var $int_MySqlErrNo;
	var $int_affectedId;
	var $int_affectedRows;
	var $bol_stripSlashes = true;
	var $str_cachePath = "";
	var $lastError = "";
	var $prefix = null;
	
	function SQLite($prefix = "tbl_") {
		$this->arr_links = Array();
		$this->str_cachePath = DB_CACHE;
		$this->prefix = $prefix;
	}
	
	static function getInstance($prefix = "tbl_") {
		if (SQLite::$instance == null) SQLite::$instance = new SQLite($prefix);
		
		return SQLite::$instance;
	}

	function setPrefix($prefix = "tbl_") {
		$this->prefix = $prefix;
	}

	function getPrefix() {
		return $this->prefix;
	}
	
	function connect($str_db = "offline") {
		global $arr_dbs;
     	$id = count($this->arr_links);
        $this->arr_links[$id]["overrides"] = $arr_dbs[$str_db]["table_overrides"];
        $this->arr_links[$id]["name"] = $arr_dbs[$str_db]["name"];
		try {
	        $this->arr_links[$id]["link"] = new SQLite3($arr_dbs[$str_db]["name"].".".$arr_dbs[$str_db]["host"]);
			$this->arr_links[$id]["link"]->createFunction("CONCAT", Array($this, "_ext_concat"));
			$this->arr_links[$id]["link"]->createFunction("REPLACE", "str_replace");
			$this->arr_links[$id]["link"]->createFunction("MD5", "md5");
		} catch (Exception $ex) {
			global $log;
			$log->fatal("Unable to connect to SQLite Database. Please check if your web server and PHP have write access for the Prails directory.\n\n");
		}
	}
	
	function _ext_concat() {
		$arr_args = func_get_args();
		return implode("", $arr_args);
	}
	

	function _prepareQuery($str_query, $linkId = 0) {
      	// apply table override settings
	  	if (is_array($this->arr_links[(int)$linkId]["overrides"])) {
	    	foreach ($this->arr_links[(int)$linkId]["overrides"] as $table=>$newTable) {
	      		$str_query = str_replace(" ".$table." ", " ".$newTable." ", $str_query);
	      	}
	  	}
	  	
	  	return $str_query;
	}
	
	function cleanCacheBlock($str_table) {
	    $name = $this->str_cachePath . $str_table;
        if (file_exists($name)) {
            $dp = opendir($name);
            while (($file = readdir($dp)) !== false) {
                if ($file[0] != ".") {
                    @unlink($this->str_cachePath . $file);
                    @unlink($name."/".$file);
                }
            }
            closedir($dp);
        }
	}
	
	function listColumns($str_table) {
		$arr_cols = $this->query("PRAGMA table_info(".$str_table.")");
		
		$arr_result = Array();
		$arr_mapping = Array("name" => "Field", "type" => "Type");
		foreach ($arr_cols as $col) {
			$line = Array();
			foreach ($col as $key=>$value) {
				if ($arr_mapping[$key]) {
					$line[$arr_mapping[$key]] = $value;
				} else {
					$line[$key] = $value;
				}
			}
			array_push($arr_result, $line);
		}
		
		return $arr_result;
	}
	
	function tableExists($str_table) {
		$res = $this->query("SELECT name FROM sqlite_master WHERE name='" . $str_table . "'");
		return (count($res) > 0);
	}
	
	function escape($str) {
     	$link = $this->arr_links[0]["link"];
      	if (!$link) {
      		$this->connect();
	      	$link = $this->arr_links[0]["link"];
		}
		return $link->escapeString($str);
	}
	
	function query($str_query, $cacheTime = 0) {
      	$link = $this->arr_links[0]["link"];
	  	
        $str_query = $this->_prepareQuery($str_query);
      	
	  	if (/*$cacheTime > 0 && */$this->isCached($str_query, $cacheTime)) {
	  		return $this->getCached($str_query);
	  	} else {
	  		// if we currently have no connection, connect
	      	if (!$link) {
	      		$this->connect();
		      	$link = $this->arr_links[0]["link"];
		      	$str_query = $this->_prepareQuery($str_query);
	      	}

	      	// send SQL statement to database
			if (in_array(substr($str_query, 0, 7), Array("INSERT ", "DELETE ", "UPDATE "))) {
				$dbr_queryResult = $link->exec($str_query);
			} else {
	      		$dbr_queryResult = $link->query($str_query);
			}
		    // if query successful
			if ($dbr_queryResult) {
			    $this->int_affectedId = $link->lastInsertRowID();
	         	$this->int_affectedRows = 0;
		        $int_resultCounter = 0;
	         	$arr_result = Array ();
				
	         	while ($dbr_queryResult !== true && $arr_fetchedResult = $dbr_queryResult->fetchArray(SQLITE3_ASSOC)) {
	            	// remove slashes if needed
	            	if ($this->bol_stripSlashes) {
	            		foreach ($arr_fetchedResult as &$mix_val) {
	                  		if (gettype ($mix_val) == "string" ) {
	                  			$mix_val = stripslashes($mix_val);
							}					            			
	            		}
	            	}
		            // create resulting array
					$arr_result[] = new DBEntry($arr_fetchedResult, 0, "ArrayIterator", $this->prefix);
		            $int_resultCounter ++;
		         }
		         
		         if (strtoupper(substr($str_query, 0, 7)) == "SELECT ") {
		         	// cache result
		         	$this->setCache($str_query, $arr_result);
		         } else {
                		preg_match_all("/ [a-zA-Z0-9_.]*".$this->prefix."([a-z0-9A-Z_]+) /i", $str_query, $arr_matches);
		                if (count($arr_matches[0]) > 0) {
            		    // loop through each
            		    foreach ($arr_matches[1] as $table) {
                            $this->cleanCacheBlock($table);
            		    }
            		}
		         }
		         
		         return ($arr_result);
	    	  } else {
	    	  	$this->lastError = $link->lastErrorMsg();
	         	pushError($link->lastErrorMsg());
	      	}
	  	}
	}
	
	private function isCached($str_query, $cacheTime) {
		$name = $this->str_cachePath . md5($str_query);
		return (file_exists($name) && (time() - DB_CACHE_TTL) < filectime($name));
	}
	
	private function getCached($str_query) {
		$name = $this->str_cachePath . md5($str_query);
		$arr_result = unserialize(file_get_contents($name));
		if (!is_array($arr_result)) return Array();
		return $arr_result;
	}
	
	private function setCache($str_query, $arr_result) {
		if (!is_array($arr_result)) return;
		$name = $this->str_cachePath . md5($str_query);
		file_put_contents($name, serialize($arr_result), LOCK_EX);
		
		// get affected tables
		preg_match_all("/ [a-zA-Z0-9_.]*".$this->prefix."([a-z0-9A-Z_]+) /i", $str_query, $arr_matches);
		if (count($arr_matches[0]) > 0) {
		    // loop through each
		    foreach ($arr_matches[1] as $table) {
		        if (!file_exists($this->str_cachePath . $table)) {
		            @mkdir($this->str_cachePath . $table, 0755);
		        }
		        // put a new file with the queries name into the cache dir
		        @touch($this->str_cachePath . $table . "/" . md5($str_query));
		    }
		}
	}
}
?>
