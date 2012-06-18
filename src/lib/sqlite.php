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

class SQLite extends Cacheable {

	var $constructs = Array(
		"pk" => "INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL"
	);
	
	static $instance = null;
	var $links = null;
	var $affectedId;
	var $affectedRows;
	var $stripSlashes = false;
	var $lastError = "";
	var $prefix = null;

	function SQLite($prefix = "tbl_") {
		parent::__construct();
		$this->links = Array();
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

	function connect($db = "offline") {
		global $arr_dbs;
		$id = count($this->links);
		$this->links[$id]["overrides"] = $dbs[$db]["table_overrides"];
		$this->links[$id]["name"] = $dbs[$db]["name"];
		try {
			$this->links[$id]["link"] = new SQLite3($arr_dbs[$db]["name"].".".$arr_dbs[$db]["host"]);
			$this->links[$id]["link"]->createFunction("CONCAT", Array($this, "_ext_concat"));
			$this->links[$id]["link"]->createFunction("REPLACE", Array($this, "_ext_replace"));
			$this->links[$id]["link"]->createFunction("MD5", "md5");
			$this->links[$id]["link"]->createFunction("FLOOR", "floor");
			$this->links[$id]["link"]->createFunction("CEIL", "ceil");
			$this->links[$id]["link"]->createFunction("TESTNULL", Array($this, "_ext_isnull"));
			$this->links[$id]["link"]->createFunction("UNIX_TIMESTAMP", "time");
			$this->links[$id]["link"]->createFunction("RAND", "mt_rand");
		} catch (Exception $ex) {
			global $log;
			$log->fatal("Unable to connect to SQLite Database. Please check if your web server and PHP have write access for the Prails directory. Caused by exception: ".$ex->getMessage()."\n\n");
		}
	}

	function _ext_replace() {
		$args = func_get_args();
		return str_replace($args[1], $args[2], $args[0]);
	}

	function _ext_concat() {
		$args = func_get_args();
		return implode("", $args);
	}
	function _ext_isnull() {
		$data = func_get_arg(0);
		return ($data == null);
	}

	function _prepareQuery($query, $linkId = 0) {
		// apply table override settings
		if (is_array($this->links[(int)$linkId]["overrides"])) {
			foreach ($this->links[(int)$linkId]["overrides"] as $table=>$newTable) {
				$query = str_replace(" ".$table." ", " ".$newTable." ", $query);
			}
		}

		if (strtoupper(substr($query, 0, 6)) != "INSERT") {
			if (strtoupper(substr($query, 0, 6)) == "UPDATE") {
				$query = substr($query, 0, strripos($query, " WHERE ")) . str_replace(" ISNULL(", " TESTNULL(", substr($query, strripos($query, " WHERE ")));
			} else {
				$query = str_replace(" ISNULL(", " TESTNULL(", $query);
			}
		}

		if (strtoupper(substr($query, 0, 12)) == "ALTER TABLE ") {
			$query = str_replace(" VARCHAR(255)", " TEXT", $query);
		}

		return $query;
	}

	function listColumns($table) {
		$cols = $this->query("PRAGMA table_info(".$table.")");

		$result = Array();
		$mapping = Array("name" => "Field", "type" => "Type");
		foreach ($cols as $col) {
			$line = Array();
			foreach ($col as $key=>$value) {
				if ($mapping[$key]) {
					$line[$mapping[$key]] = $value;
				} else {
					$line[$key] = $value;
				}
			}
			array_push($result, $line);
		}

		return $result;
	}

	function tableExists($table) {
		$res = $this->query("SELECT * FROM sqlite_master WHERE name='" . $table . "'");
		return (count($res) > 0);
	}

	function escape($str) {
		$link = $this->links[0]["link"];
		if (!$link) {
			$this->connect();
			$link = $this->links[0]["link"];
		}
		return $link->escapeString($str);
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

			if (strtoupper(substr($query, 0, 12)) == "ALTER TABLE " && stripos($query, " ADD COLUMN ") === false) return null;

			// send SQL statement to database
			if ($profiler) $profiler->logEvent("query_no_cache_hit"); 
			if (in_array(strtoupper(substr($query, 0, 7)), Array("INSERT ", "DELETE ", "UPDATE ", "REPLACE"))) {
				$queryResult = $link->exec($query);
			} else {
				$queryResult = $link->query($query);
			}
			// if query successful
			if ($queryResult) {
				$this->affectedId = $link->lastInsertRowID();
				$this->affectedRows = 0;
				$resultCounter = 0;
				$result = Array ();

				while ($queryResult !== true && $fetchedResult = $queryResult->fetchArray(SQLITE3_ASSOC)) {
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
					$resultCounter ++;
				}

				if (in_array(strtoupper(substr(trim($query), 0, 7)), Array("SELECT ", "PRAGMA "))) {
					// cache result
					if ($profiler) $profiler->logEvent("query_add_cache"); 
					$this->setCache($query, $result, $this->prefix);
				} else {
					if ($profiler) $profiler->logEvent("query_clean_cache"); 
					$this->cleanCacheBlock($query, $this->prefix);
				}

				return ($result);
			} else {
				$this->lastError = $link->lastErrorMsg();
				pushError($link->lastErrorMsg());
			}
		}
	}
}
?>
