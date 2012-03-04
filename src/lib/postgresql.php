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

class PostgreSQL extends Cacheable {

	var $constructs = Array(
		"pk" => "SERIAL PRIMARY KEY"
	);
	
	static $instance = null;
	var $arr_links = null;
	var $int_MySqlErrNo;
	var $int_affectedId;
	var $int_affectedRows;
	var $bol_stripSlashes = false;
	var $lastError = "";
	var $prefix = null;

	function PostgreSQL($prefix = "tbl_") {
		parent::__construct();
		$this->arr_links = Array();
		$this->prefix = $prefix;
	}

	static function getInstance($prefix = "tbl_") {
		if (PostgreSQL::$instance == null) PostgreSQL::$instance = new PostgreSQL($prefix);

		return PostgreSQL::$instance;
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
			$this->arr_links[$id]["link"] = pg_connect("host=".$arr_dbs[$str_db]["host"]." dbname=".$arr_dbs[$str_db]["name"]." user=".$arr_dbs[$str_db]["user"]." password=".$arr_dbs[$str_db]["pass"]);
			if (!$this->arr_links[$id]["link"]) {
				throw new Exception($arr_dbs[$str_db]["user"]."@".$arr_dbs[$str_db]["host"].":".$arr_dbs[$str_db]["name"]);
			}
		} catch (Exception $ex) {
			global $log;
			$log->fatal("Unable to connect to PostgreSQL Database: ".$ex->getMessage()."\n\n");
		}
	}

	function _prepareQuery($str_query, $linkId = 0) {
		// apply table override settings
		if (is_array($this->arr_links[(int)$linkId]["overrides"])) {
			foreach ($this->arr_links[(int)$linkId]["overrides"] as $table=>$newTable) {
				$str_query = str_replace(" ".$table." ", " ".$newTable." ", $str_query);
			}
		}
		
		if (preg_match('/^REPLACE\s+INTO\s+([^\(]+)\s+\(([^\)]+)\)\s+VALUES\s+/mi', $str_query, $match)) {
			$data = trim(str_replace($match[0], "", $str_query), '()');
			$fields = explode(",", $match[2]);
			$i = 0;
			foreach ($fields as $pos => $field) {
				if (trim($field) == str_replace($this->prefix, "", $match[1])."_id") {
					$i = $pos;
					break;
				}
			}
			$id = array_slice(explode(",", $data), $i, 1);
			$this->query("DELETE FROM ".$match[1]." WHERE ".str_replace($this->prefix, "", $match[1])."_id='".trim($id[0], "'")."'");
			$this->query("INSERT INTO ".$match[1]." (".$match[2].") VALUES (".$data.")");
			$str_query = "";
		} else if (strtoupper(substr($str_query, 0, 6)) != "INSERT") {
			if (strtoupper(substr($str_query, 0, 6)) == "UPDATE") {
				$front = substr($str_query, 0, strripos($str_query, " WHERE "));
				$where = substr($str_query, strripos($str_query, " WHERE "));
				$str_query = $front . preg_replace('/\s+UNIX_TIMESTAMP\s*\(\s*\)/mi', 'ROUND(DATE_PART(\'epoch\',NOW()))', $where);
				$str_query = $front . preg_replace('/\s+ISNULL\(([^)]+)\)/mi', " (\$1 IS NULL)", $where);
			} else {
				$str_query = preg_replace('/\s+ISNULL\(([^)]+)\)/mi', " (\$1 IS NULL)", $str_query);
				$str_query = str_replace(Array(" LONGTEXT", " MEDIUMTEXT", " DOUBLE", " FLOAT", " TINYINT", " LONGBLOB"), 
										 Array(" TEXT", " TEXT", " DOUBLE PRECISION", " REAL", " SMALLINT", " BYTEA"), $str_query);
				$str_query = preg_replace('/\s+LIMIT\s+([^,]+),\s*([0-9]+)/mi', " LIMIT \$1 OFFSET \$2", $str_query);
				$str_query = preg_replace('/\s+UNIX_TIMESTAMP\s*\(\s*\)/mi', 'ROUND(DATE_PART(\'epoch\',NOW()))', $str_query);
				// @todo change/alter column doesn't work as nicely as for mysql... (different syntax & semantics)
				$str_query = preg_replace('/\s+CHANGE COLUMN\s+([^ ]+) ([^ ]+) /mi', ' ALTER COLUMN \1 TYPE ', $str_query);
				$str_query = preg_replace('/\s+ISNULL\(([^)]+)\)/mi', " (\$1 IS NULL)", $str_query);
			}
		}

		return $str_query;
	}

	function listColumns($str_table) {
		$arr_cols = $this->query("SELECT a.attnum, a.attname AS field, t.typname AS type, ".
       							 "a.attlen AS length, a.atttypmod AS length_var, ".
       							 "a.attnotnull AS not_null, a.atthasdef as has_default ".
  								 "FROM pg_class c, pg_attribute a, pg_type t ".
 								 "WHERE c.relname = '".$str_table."' ".
   								 "AND a.attnum > 0 ".
   								 "AND a.attrelid = c.oid ".
   								 "AND a.atttypid = t.oid ".
 								 "ORDER BY a.attnum");

		$arr_result = Array();
		$arr_mapping = Array("field" => "Field", "type" => "Type");
		foreach ($arr_cols as $col) {
			$line = Array();
			foreach ($col as $key=>$value) {
				if ($arr_mapping[$key]) {
					$line[$arr_mapping[$key]] = $value;
				} else {
					$line[$key] = $value;
				}
			}
			// map everything back to the correct data types
			/* attnum field 	type 		length 	length_var 	not_null 	has_default
			 * 1 	  "test_id" "int4" 		4 		-1 			t 			t
			 * 2 	  "name" 	"varchar" 	-1 		259 		f 			f
			 */		
			$line["Type"] = str_replace(
				Array("int4", "int8", "text", "varchar", "float4", "float8", "int2", "bytea"), 
				Array("INTEGER", "BIGINT", "TEXT", "VARCHAR(255)", "FLOAT", "DOUBLE", "TINYINT", "LONGBLOB"), 
				$line["Type"]
			);
			if ($line["not_null"] == "t") {
				$line["Type"] .= " NOT NULL";
			}
			array_push($arr_result, $line);
		}
		
		return $arr_result;
	}

	function tableExists($str_table) {
		$res = $this->query("SELECT tablename FROM pg_tables WHERE tablename = '".$str_table."'");
		return (count($res) > 0);
	}

	function escape($str) {
		return pg_escape_string($str);
	}

	function query($str_query, $cacheTime = 0) {
		global $profiler;
		$link = $this->arr_links[0]["link"];

		$str_query = $this->_prepareQuery($str_query);
		
		if (empty($str_query)) return;
		if ($this->isCached($str_query, $cacheTime)) {
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
			$dbr_queryResult = pg_query($link, $str_query);

			// if query successful
			if (is_resource($dbr_queryResult)) {
				$this->int_affectedId = 0;
				if (preg_match('/^CREATE TABLE ([^\(]+)\(([a-zA-Z_\-]+_id) '.$this->constructs["pk"].'/mi', $str_query, $match)) {
					$table = trim(str_replace("IF NOT EXISTS", "", $match[1]));
					$pk = $match[2];
					$res = pg_query($link, "CREATE OR REPLACE RULE ".$table."_".$pk."_seq AS ON INSERT TO ".$table." DO SELECT currval('".$table."_".$pk."_seq'::text) AS id");
					if (!is_resource($res)) {
						$this->lastError = pg_last_error($link);
						pushError($this->lastError);
					}
				} else if (preg_match('/^INSERT\s+INTO\s+(?!tbl_prailsbase_session)/mi', trim($str_query))) {
					$this->int_affectedId = @array_pop(@pg_fetch_assoc($dbr_queryResult));
				}
				$int_resultCounter = 0;
				$arr_result = Array();

				if (pg_result_status($dbr_queryResult) == PGSQL_TUPLES_OK && $this->int_affectedId <= 0) {
					$this->int_affectedRows = @pg_num_rows ($dbr_queryResult);
					while ($this->int_affectedRows > 0 && $arr_fetchedResult = @pg_fetch_assoc($dbr_queryResult)) {
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
						$int_resultCounter++;
					}

					@pg_free_result($dbr_queryResult);
					if ($profiler) $profiler->logEvent("query_add_cache"); 
					$this->setCache($str_query, $arr_result, $this->prefix);
				} else {
					if ($profiler) $profiler->logEvent("query_clean_cache");
					$this->cleanCacheBlock($str_query, $this->prefix);
				}

				return ($arr_result);
			} else {
				$this->lastError = pg_last_error($link);
				pushError($this->lastError);
			}
		}
	}
}
?>
