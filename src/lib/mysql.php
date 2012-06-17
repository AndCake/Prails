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

class MySQL extends Cacheable {

	var $constructs = Array(
		"pk" => "INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY"
	);	

	static $instance = null;
	var $links = null;
	var $affectedId;
	var $affectedRows;
	var $stripSlashes = false;
	var $lastError = "";
	var $prefix = null;

	function MySQL($prefix = "tbl_") {
		parent::__construct();
		$this->links = Array();
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

	function connect($db = "offline") {
		global $dbs;
		global $log;
		$id = count($this->links);
		$this->links[$id]["link"] = @mysql_connect($dbs[$db]["host"], $dbs[$db]["user"], $dbs[$db]["pass"]);
		$this->links[$id]["overrides"] = $dbs[$db]["table_overrides"];
		$this->links[$id]["name"] = $dbs[$db]["name"];
		if ($this->links[$id]["link"]) {
			if ( @mysql_select_db ($dbs[$db]["name"], $this->links[$id]["link"]) ) {
				return (TRUE);
			} else {
				$mySqlError .= mysql_error () . " Error code: " . mysql_errno();
				$log->fatal($mySqlError);
				return false;
			}
		} else {
			// set error code and leave method
			$mySqlError .= mysql_error () . " Error code: " . mysql_errno();
			$log->fatal($mySqlError);
		}

	}


	function _prepareQuery($query, $linkId = 0) {
		// apply table override settings
		if (is_array($this->links[(int)$linkId]["overrides"])) {
			foreach ($this->links[(int)$linkId]["overrides"] as $table=>$newTable) {
				$query = str_replace(" ".$table." ", " ".$newTable." ", $query);
			}
		}

		if ((strtoupper(substr($query, 0, 12)) == "ALTER TABLE " || strtoupper(substr($query, 0, 13)) == "CREATE TABLE ") && 
			stripos($query, " REFERENCES ") !== false) {
			// clean out foreign key constraints as MySQL MyISAM does not support that...
			$query = preg_replace('/ REFERENCES [a-zA-Z0-9_]+/mi', '', $query);
		}
		
		return $query;
	}

	function listColumns($table) {
		return $this->query("SHOW COLUMNS FROM ".$table."");
	}

	function tableExists($table) {
		$res = $this->query("SHOW TABLES");
		foreach ($res as $r) {
			$tbl = array_values($r->getArrayCopy());
			if ($tbl[0] == $table) return true;
		}
		return false;
	}

	function escape($str) {
		return str_replace(Array("'", "\\"), Array("''", "\\\\"), $str);
	}

	function query($query, $cacheTime = 0) {
		global $profiler;
		$link = $this->links[0]["link"];

		$query = $this->_prepareQuery($query);

		if (/*$cacheTime > 0 && */$this->isCached($query, $cacheTime)) {
			if ($profiler) $profiler->logEvent("query_cache_hit"); 
			return $this->getCached($query);
		} else {
			// if we currently have no connection, connect
			if (!$link) {
				$this->connect();
				$link = $this->links[0]["link"];
				$query = $this->_prepareQuery($query);
			}

			if ($profiler) $profiler->logEvent("query_no_cache_hit");
			 
			// send SQL statement to database
			$queryResult = @mysql_query ($query, $link);
			// if query successful
			if ($queryResult) {
				$this->affectedId = @mysql_insert_id ();
				$resultCounter = 0;
				$result = Array ();

				if (is_resource($queryResult)) {
					$this->affectedRows = @mysql_num_rows ($queryResult);
					while ($fetchedResult = @mysql_fetch_array ($queryResult, MYSQL_ASSOC)) {
						// remove slashes if needed
						if ($this->stripSlashes) {
							foreach ($fetchedResult as &$val) {
								if (gettype ($val) == "string" ) {
									$val = stripslashes($val);
								}
							}
						}
						// create resulting array
						$result[] = new DBEntry($fetchedResult, 0, "ArrayIterator", $this->prefix);
					}
	
					@mysql_free_result($queryResult);
					if ($profiler) $profiler->logEvent("query_add_cache"); 
					$this->setCache($query, $result, $this->prefix);
				} else {
					if ($profiler) $profiler->logEvent("query_clean_cache"); 
					$this->cleanCacheBlock($query, $this->prefix);
				}

				return ($result);
			} else {
				$mySqlError .= mysql_error () . " Error-Code: " . mysql_errno ();
				$this->lastError = $mySqlError;
				pushError($mySqlError);
			}
		}
	}
}
?>