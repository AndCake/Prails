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

class MySQL extends Cacheable {

	static $instance = null;
	var $arr_links = null;
	var $int_MySqlErrNo;
	var $int_affectedId;
	var $int_affectedRows;
	var $bol_stripSlashes = false;
	var $lastError = "";
	var $prefix = null;

	var $constructs = Array(
		"pk" => "INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY"
	);	

	function MySQL($prefix = "tbl_") {
		parent::__construct();
		$this->arr_links = Array();
		$this->prefix = $prefix;
	}

	static function getInstance($prefix = "tbl_") {
		if (MySQL::$instance == null) MySQL::$instance = new MySQL($prefix);

		return MySQL::$instance;
	}

	function setPrefix($prefix = "tbl_") {
		$this->prefix = $prefix;
	}

	function getPrefix() {
		return $this->prefix;
	}

	function connect($str_db = "offline") {
		global $arr_dbs;
		global $log;
		$id = count($this->arr_links);
		$this->arr_links[$id]["link"] = @mysql_connect($arr_dbs[$str_db]["host"], $arr_dbs[$str_db]["user"], $arr_dbs[$str_db]["pass"]);
		$this->arr_links[$id]["overrides"] = $arr_dbs[$str_db]["table_overrides"];
		$this->arr_links[$id]["name"] = $arr_dbs[$str_db]["name"];
		if ($this->arr_links[$id]["link"]) {
			if ( @mysql_select_db ($arr_dbs[$str_db]["name"], $this->arr_links[$id]["link"]) ) {
				return (TRUE);
			} else {
				$str_mySqlError .= mysql_error () . " Error code: " . mysql_errno();
				$this->int_MySqlErrNo = mysql_errno ();
				$log->fatal($str_mySqlError);
				return false;
			}
		} else {
			// set error code and leave method
			$str_mySqlError .= mysql_error () . " Error code: " . mysql_errno();
			$this->int_MySqlErrNo = mysql_errno ();
			$log->fatal($str_mySqlError);
		}

	}


	function _prepareQuery($str_query, $linkId = 0) {
		// apply table override settings
		if (is_array($this->arr_links[(int)$linkId]["overrides"])) {
			foreach ($this->arr_links[(int)$linkId]["overrides"] as $table=>$newTable) {
				$str_query = str_replace(" ".$table." ", " ".$newTable." ", $str_query);
			}
		}

		if ((strtoupper(substr($str_query, 0, 12)) == "ALTER TABLE " || strtoupper(substr($str_query, 0, 13)) == "CREATE TABLE ") && 
			stripos($str_query, " REFERENCES ") !== false) {
			// clean out foreign key constraints as MySQL MyISAM does not support that...
			$str_query = preg_replace('/ REFERENCES [a-zA-Z0-9_]+/mi', '', $str_query);
		}
		
		return $str_query;
	}

	function listColumns($str_table) {
		return $this->query("SHOW COLUMNS FROM ".$str_table."");
	}

	function tableExists($str_table) {
		$res = $this->query("SHOW TABLES");
		foreach ($res as $r) {
			$tbl = array_values($r->getArrayCopy());
			if ($tbl[0] == $str_table) return true;
		}
		return false;
	}

	function escape($str) {
		return str_replace(Array("'", "\\"), Array("''", "\\\\"), $str);
	}

	function query($str_query, $cacheTime = 0) {
		global $profiler;
		$link = $this->arr_links[0]["link"];

		$str_query = $this->_prepareQuery($str_query);

		if (/*$cacheTime > 0 && */$this->isCached($str_query, $cacheTime)) {
			if ($profiler) $profiler->logEvent("query_cache_hit"); 
			return $this->getCached($str_query);
		} else {
			// if we currently have no connection, connect
			if (!$link) {
				$this->connect();
				$link = $this->arr_links[0]["link"];
				$str_query = $this->_prepareQuery($str_query);
			}

			if ($profiler) $profiler->logEvent("query_no_cache_hit");
			 
			// send SQL statement to database
			$dbr_queryResult = @mysql_query ($str_query, $link);
			// if query successful
			if ($dbr_queryResult) {
				$this->int_affectedId = @mysql_insert_id ();
				$int_resultCounter = 0;
				$arr_result = Array ();

				if (is_resource($dbr_queryResult)) {
					$this->int_affectedRows = @mysql_num_rows ($dbr_queryResult);
					while ($arr_fetchedResult = @mysql_fetch_array ($dbr_queryResult, MYSQL_ASSOC)) {
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
					}
	
					@mysql_free_result($dbr_queryResult);
					if ($profiler) $profiler->logEvent("query_add_cache"); 
					$this->setCache($str_query, $arr_result, $this->prefix);
				} else {
					if ($profiler) $profiler->logEvent("query_clean_cache"); 
					$this->cleanCacheBlock($str_query, $this->prefix);
				}

				return ($arr_result);
			} else {
				$str_mySqlError .= mysql_error () . " Error-Code: " . mysql_errno ();
				$this->int_MySqlErrNo = mysql_errno ();
				$this->lastError = $str_mySqlError;
				pushError($str_mySqlError);
			}
		}
	}
}
?>
