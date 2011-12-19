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

class SQLite extends Cacheable {

	var $constructs = Array(
		"pk" => "INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL"
	);
	
	static $instance = null;
	var $arr_links = null;
	var $int_MySqlErrNo;
	var $int_affectedId;
	var $int_affectedRows;
	var $bol_stripSlashes = false;
	var $lastError = "";
	var $prefix = null;

	function SQLite($prefix = "tbl_") {
		parent::__construct();
		$this->arr_links = Array();
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
			$this->arr_links[$id]["link"]->createFunction("REPLACE", Array($this, "_ext_replace"));
			$this->arr_links[$id]["link"]->createFunction("MD5", "md5");
			$this->arr_links[$id]["link"]->createFunction("FLOOR", "floor");
			$this->arr_links[$id]["link"]->createFunction("CEIL", "ceil");
			$this->arr_links[$id]["link"]->createFunction("TESTNULL", Array($this, "_ext_isnull"));
			$this->arr_links[$id]["link"]->createFunction("UNIX_TIMESTAMP", "time");
			$this->arr_links[$id]["link"]->createFunction("RAND", "mt_rand");
		} catch (Exception $ex) {
			global $log;
			$log->fatal("Unable to connect to SQLite Database. Please check if your web server and PHP have write access for the Prails directory.\n\n");
		}
	}

	function _ext_replace() {
		$arr_args = func_get_args();
		return str_replace($arr_args[1], $arr_args[2], $arr_args[0]);
	}

	function _ext_concat() {
		$arr_args = func_get_args();
		return implode("", $arr_args);
	}
	function _ext_isnull() {
		$data = func_get_arg(0);
		return ($data == null);
	}

	function _prepareQuery($str_query, $linkId = 0) {
		// apply table override settings
		if (is_array($this->arr_links[(int)$linkId]["overrides"])) {
			foreach ($this->arr_links[(int)$linkId]["overrides"] as $table=>$newTable) {
				$str_query = str_replace(" ".$table." ", " ".$newTable." ", $str_query);
			}
		}

		if (strtoupper(substr($str_query, 0, 6)) != "INSERT") {
			if (strtoupper(substr($str_query, 0, 6)) == "UPDATE") {
				$str_query = substr($str_query, 0, strripos($str_query, " WHERE ")) . str_replace(" ISNULL(", " TESTNULL(", substr($str_query, strripos($str_query, " WHERE ")));
			} else {
				$str_query = str_replace(" ISNULL(", " TESTNULL(", $str_query);
			}
		}

		if (strtoupper(substr($str_query, 0, 12)) == "ALTER TABLE ") {
			$str_query = str_replace(" VARCHAR(255)", " TEXT", $str_query);
		}

		return $str_query;
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
		$res = $this->query("SELECT * FROM sqlite_master WHERE name='" . $str_table . "'");
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

			if (strtoupper(substr($str_query, 0, 12)) == "ALTER TABLE " && stripos($str_query, " ADD COLUMN ") === false) return null;

			// send SQL statement to database
			if ($profiler) $profiler->logEvent("query_no_cache_hit"); 
			if (in_array(strtoupper(substr($str_query, 0, 7)), Array("INSERT ", "DELETE ", "UPDATE ", "REPLACE"))) {
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

				if (in_array(strtoupper(substr(trim($str_query), 0, 7)), Array("SELECT ", "PRAGMA "))) {
					// cache result
					if ($profiler) $profiler->logEvent("query_add_cache"); 
					$this->setCache($str_query, $arr_result, $this->prefix);
				} else {
					if ($profiler) $profiler->logEvent("query_clean_cache"); 
					$this->cleanCacheBlock($str_query, $this->prefix);
				}

				return ($arr_result);
			} else {
				$this->lastError = $link->lastErrorMsg();
				pushError($link->lastErrorMsg());
			}
		}
	}
}
?>
