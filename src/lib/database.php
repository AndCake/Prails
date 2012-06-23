<?php
/**
 Prails Web Framework
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

/** Class Database
 *
 * The Database Manager handles all requests that have something to do with database queries. 
 * It manages the connection to the underlying database automatically, manages the database 
 * cache, which speeds up large queries, constantly synchronizes database contents with other 
 * (external) databases and provides access for easier usability.
 *
 * ![data queries section](static/images/doc/data-queries-section-large.png) Each "data query", which is the section in the Prails IDE below the event handlers,
 * are actually an instance of the class `Database`. So accessing the methods below can be
 * achieved by using the `$this` keyword. The data queries do not explicitly define parameters
 * that can be used to call them. Instead you can use the functions `func_get_arg()` / `func_get_args()` and 
 * `func_num_args()` to determine a specific parameter passed to it and find out the number of
 * parameters that were given to it. 
 * 
 * *Example:*
 * {{{
 * $id = func_get_arg(0);	// first parameter is required
 * if (func_num_args() &gt; 1) {
 *     $sorting = func_get_arg(1);
 * }
 * return $this->get('user', 'fk_friend_id='.$id, if_set($sorting, "name ASC"));
 * }}}
 * This example of a data query fetches the first parameter and then checks whether or not 
 * there is another parameter given to it. If so, it will use the second one for controlling 
 * the sorting rule applied to the `[Database]get` method.
 **/
class Database {
	var $prefix = null;
	var $isCached = true;
	var $sql = null;
	var dumpSqlQuery = DEBUG_MYSQL;
	
	function Database($prefix = "tbl_") {
		$this->prefix = $prefix;
		$this->sql = call_user_func(Array(DB_TYPE, "getInstance"), $prefix);
	}
	
    /** 
     * sets the caching policy (use a cache or don't use a cache)
     *
     * @param BOOLEAN $isCache use a cache or don't use it (defaults to true)
     */
    function setCache($isCache = true) {
        $this->isCached = $isCache;
    }
   
    /** escape($string) -> String
     * - $string (String) - the string to be escaped
     *
     * escapes a string and returns a properly safe string that can be used for sending it to the DB. 
     **/ 
    function escape($str) {
    	return $this->sql->escape($str);
    }
 
	/**
	 * query($query) -> Array
	 * - $query (String) - The complete SQL query to be sent to the database.
	 *
	 * Sends a query to the database and returns it's result. _Hint:_ you need to add the 
	 * table's prefix manually (which is always "tbl_"). The method will return an array 
	 * of `DBEntry` objects, or an empty Array in case the query has no result set.
	 * 
     * *Example:* 
     * {{{
     * $arr_result = $this->query("SELECT * FROM tbl_user LEFT JOIN tbl_story ON fk_user_id=user_id WHERE NOT ISNULL(photo)");
     * }}}
     * The result in `$arr_result` will look like that:
     * {{{
     * $arr_result = Array(
     *      0 => Array(
     *          "user_id" => "4",
     *          "photo" => "test.jpg",
     *          "story_id" => "",
     *          "fk_user_id" => "",
     *          "title" => ""
     *     ),
     *     1 => Array(
     *          "user_id" => "19",
     *          "photo" => "mypicture.jpg",
     *          "story_id" => "25",
     *          "fk_user_id" => "19",
     *          "title" => "My Test Story"    
     *     ),
     *     ...
     * );
     * }}}
	 **/
	function query($query) {
    	global $profiler;

      	// dump query if needed
    	if ($this->dumpSqlQuery != 0) echo ($query."<br/>");
    	$this->sql->setPrefix($this->prefix);
    	if ($profiler) $profiler->logEvent("queryStart");
    	$result = $this->sql->query($query, ($this->isCached ? DB_CACHE_TTL : 0));
    	$this->affectedId = $this->sql->affectedId;
    	$this->affectedRows = $this->sql->affectedRows;
    	if ($profiler) $profiler->logEvent("queryEnd");
    	
    	return $result;
	}
	function SqlQuery($query) { return $this->query($query); }

	/**
	 * escape($value) -> String
	 * - $value (String) - the string to be escaped
	 * 
	 * This function will escape the value given, so that it is safe to place it in a query or condition. 
	 * It does so depending on the underlying database in use.
	 * 
	 * *Example:* 
	 * 
	 * For a MySQL database this example will output: `Escaped string: Zak''s Laptop`
	 * {{{
	 * $item = "Zak's Laptop";
	 * $escaped_item = $this->escape($item);
	 * printf("Escaped string: %s\n", $escaped_item);
	 * }}}
	 **/
	 
	/**
	 * get($table[, $filter[, $sort[, $start[, $limit]]]]) -> Array
	 * - $table (String) - table name (with or without prefix)
	 * - $filter (Array|String) - retrieve what? (example: <code>"customer_id=12"</code> or <code>Array("customer_id" => 12)</code>); multiple entries in array will be joined using <code>AND</code>
	 * - $sort (String) - Sorting rule to be used; consists of at least the field name to be sorted and optionally the sorting direction ("ASC" for ascending or "DESC" for descending). Multiple fields can be used for sorting; those need to be seperated by comma. Example: "lastModified ASC"); defaults to ""
     * - $start (Integer) - used for pagination: beginning with which entry the result should be returned (defaults to 0)
     * - $limit (Integer) - used for pagination: how many items should be returned (defaults to 999999).
     *
	 * Retrieve data from a table. 
     *
     * *Example:* 
     * 
     * This example will select all entries from the table "user" which have a photo set and sorts them descending by the fields "last_name" and "first_name".
     * {{{
     * $arr_result = $this->select("user", "NOT ISNULL(photo)", "last_name DESC, first_name DESC");
     * }}}
	 **/
	function get($table, $filter = "", $sort = "", $start=0, $limit=999999) {
		if (strpos($table, $this->prefix) === false) {
			$table = $this->prefix . $table;
		}
		if (is_array($filter)) {
			$res = "";
			foreach ($filter as $key => $value) {
				if (strlen($res) > 0) $res .= " AND ";
				if (substr($key, -3) == "_id") {
					$res .= $key."=".(int)$value;					
				} else {
					$res .= $key."='".$this->escape($value)."'";
				}
			}
			$filter = $res;
		}
		if (strlen($sort) > 0) {
			$sort = " ORDER BY ".$sort;
		}
		return $this->query("SELECT * FROM ".$table." WHERE ".if_set($filter, "1").$sort." LIMIT ".$start.", ".$limit);
	}
	/** 
 	 * select($table[, $filter[, $sort[, $start[, $limit]]]]) -> Array
	 * This method is an alias for `[Database]get`.
	 **/
	function select($table, $filter = "", $sort = "", $start = 0, $limit = 999999) { return $this->get($table, $filter, $sort, $start, $limit); }

    /** 
     * getItem($table, $id) -> DBEntry
     * - $table (String) - table name (with or without prefix)
     * - $id (String|Integer) - the ID value of the primary key for which to retrieve the Database entry.
     *
     * Retrieve a single database entry from a table, specified through it's primary key value.
     **/
	function getItem($table, $id) {
		if (strpos($table, $this->prefix) === false) {
			$table = $this->prefix . $table;
		}
		$pkfield = str_replace($this->prefix, "", $table)."_id";
		return @array_pop($this->query("SELECT * FROM ".$table." WHERE ".$pkfield."=".if_set($id, "0")));
	}
	
	/**
	 * add($table, $data) -> Integer
	 * - $table (String) - table name (with or without prefix) to which to add a row.
	 * - $data (Array) - Data to be inserted. Only entries, whose key matches one of the table's field names, are actually taken into account.
	 *
	 * inserts a tupel into the specified table and returns the ID of the new entry.
	 *
	 * *Example:*
	 * {{{ 
	 *  $user_id = $this->add("user", Array(
	 *      "first_name" => "Test",
	 *      "last_name" => "User",
	 *      "email" => "tester@example.org"
	 *  ));
	 * }}}
	 * This example inserts a user into the "user" table.
	 **/
	function add($table, $data) {
		if (strpos($table, $this->prefix) === false) {
			$table = $this->prefix . $table;
		}
		$columns = $this->sql->listColumns($table);
		
		$fields = Array();
		$values = Array();
		$data = $this->unifyData($data);
		foreach ($columns as $column) {
			$field = strtolower($column["Field"]);
			if (isset($data[$field])) {     	
				array_push($fields, $column["Field"]);
				array_push($values, $this->escape($data[$field]));
		   }
		}
		if (count($fields) <= 0) return 0;
		
		$query = "INSERT INTO ".$table." (";
		$query .= implode(", ", $fields) . ") VALUES ('" . implode("', '", $values)."')";
		$this->query ( $query );
		$this->remoteQuery($query);
		
		return $this->affectedId;
	}
	function InsertQuery($table, $data) { return $this->add($table, $data); }
	
	/** 
     * insert($table, $data) -> Integer
     * This method is an alias for `[Database]add`.
     **/
	function insert($table, $data) { return $this->add($table, $data); }
	/** 
     * ins($table, $data) -> Integer
     * This method is an alias for `[Database]add`.
     **/
	function ins($table, $data) { return $this->add($table, $data); }
	
	/**
     * remove($table, $filter) -> void
	 * - $table (String) - Name of the table, from which the tupel should be removed
	 * - $filter (String|Array) - remove what? (example: <code>"customer_id=12"</code> or <code>Array("customer_id" => 12)</code>); multiple entries in array will be joined using <code>AND</code>	 
     *
     * Deletes one or more tupel from a table.
     *
     * *Example:*
     * {{{
     * $id = func_get_arg(0);
     * $this->remove("story", "story_id='".$id."'");
     * // alternatively the following would do the same thing:
     * // $this->del("story", Array("story_id" => $id));
     * }}}
     * This example - to be run within a data query of a module - removes the story with the ID passed to this function.
	 **/
	function remove($table, $filter) {
		if (strpos($table, $this->prefix) === false) {
			$table = $this->prefix . $table;
		}
		if (is_array($filter)) {
			$res = "";
			foreach ($filter as $key => $value) {
				if (strlen($res) > 0) $res .= " AND ";
				if (substr($key, -3) == "_id") {
					$res .= $key."=".(int)$value;
				} else {
					$res .= $key."='".$this->escape($value)."'";
				}
			}
			$filter = $res;
		}
		
		$query = "DELETE FROM ".$table." WHERE (".if_set($filter, "FALSE").")";
		$this->query($query);
		$this->remoteQuery($query);
	}
	function DeleteQuery($table, $filter) { $this->remove($table, $filter); }
	/** 
     * delete($table, $filter) -> void
     * This method is an alias for `[Database]remove`.
     **/
	function delete($table, $filter) { $this->remove($table, $filter); }
	/** 
     * del($table, $filter) -> void
     * This method is an alias for `[Database]remove`.
     **/
	function del($table, $filter) { $this->remove($table, $filter); }

	/**
     * update($table, $data, $filter) -> void
	 * - $table (String) - Name of the table to be updated
	 * - $data (Array) -  Data with which the selected rows are updated. Only entries, whose key matches one of the table's field names, are actually taken into account.
	 * - $filter (Array|String) - update what? (example: <code>"customer_id=12"</code> or <code>Array("customer_id" => 12)</code>); multiple entries in array will be joined using <code>AND</code>
     * 
	 * Update one or more tupel of a specified table and returns the number of rows updated.
     *
     * *Example:* 
     * {{{
     *  $id = func_get_arg(0);
     *  $arr_data["first_name"] = "Test";
     *  $arr_data["name"] = "User";
     *  $this->update("user", $arr_data, "user_id='".$id."'");
     * }}}
	 **/
	function update($table, $data, $filter) {
		if (strpos($table, $this->prefix) === false) {
			$table = $this->prefix . $table;
		}
		if (is_array($filter)) {
			$res = "";
			foreach ($filter as $key => $value) {
				if (strlen($res) > 0) $res .= " AND ";
				if (substr($key, -3) == "_id") {
					$res .= $key."=".(int)$value;					
				} else {
					$res .= $key."='".$this->escape($value)."'";
				}
			}
			$filter = $res;
		}
		
		$columns = $this->sql->listColumns($table);
		
		$query = "UPDATE ".$table." SET ";
		$i = 0;
		$data = $this->unifyData($data);
		foreach ($columns as $column) {
			$field = strtolower($column["Field"]);
			if (isset($data[$field])) {
				if ($i > 0) $query .= ", ";
				if (preg_match("/[^\\\]'/", $data[$field])) {
					$data[$column["Field"]] = $this->escape($data[$field]);
				}
				if (!preg_match('/^[0-9.-]+$/', $data[$field])) {
					$query .= $column["Field"]."='".$data[$field]."'";
				} else {
					$query .= $column["Field"]."=".$data[$field];
				}
				$i++;
			}
		}
		// nothing needs to be updated... got no data.
		if ($i == 0) return 0;
	
		$query .= " WHERE (".if_set($filter, "FALSE").")";
		$this->query ( $query );
		$this->remoteQuery($query);
		
		return $this->affectedRows;
	} 
	function UpdateQuery($table, $data, $filter) { return $this->update($table, $data, $filter); }
	
	/** 
     * edit($table, $data, $filter) -> void
     * This method is an alias for `[Database]update`.
     **/
	function edit($table, $data, $filter) { $this->update($table, $data, $filter); }

	/**
     * set($table, $data[, $filter]) -> void|Array
     * - $table (String) - name of the table to be updated
     * - $data (Array) - data with which to update the specified row / which should be inserted.
     * - $filter (Array|String) - update what? (example: <code>"customer_id=12"</code> or <code>Array("customer_id" => 12)</code>); multiple entries in array will be joined using <code>AND</code>; If this parameter is specified, the method will run an update instead of an insert.
     *
     * Inserts or updates data into the database. If no filter is specified, it will insert. Else it will update all the rows that are affected by the filter with the data given.
     **/
	function set($table, $data, $filter = null) { 
		if ($filter == null) 
			return $this->add($table, $data); 
		else 
			$this->update($table, $data, $filter); 
	}

	// ---------------- private methods --------------------	
	private function remoteQuery($query) {
	    global $arr_dbs;
	  
	    $dbList = Array();
	  
	    foreach ($arr_dbs as $title=>$db) {
	        array_push($dbList, $db);
	    }
	  
	    if (count($dbList) > 1) {
	      	for ($i = 1; $i < count($dbList); $i++) {
	      	    $currentQuery = $query;
	            if (is_array($dbList[$i]["table_overrides"])) {
		            foreach ($dbList[$i]["table_overrides"] as $table=>$newTable) {
		      	        $currentQuery = str_replace(" ".$table." ", " ".$newTable." ", $currentQuery);
		            }
	            }
	      	    // add to todo-query_list
	      	    $currentQuery .= "\n";
	      	    $currentQuery = $currentQuery;
	      	    $fp = @fopen("open_queries_".$i.".sql", "a+");
	      	    @fwrite($fp, $currentQuery);
	      	    @fclose($fp);
	      	    @chmod("open_queries_".$i.".sql", 0666);
	      	}
	    }
	}
   
	private function unifyData($data) {
		$res = Array();
		foreach ($data as $key=>$dat) {
			$res[strtolower($key)] = $dat;
		}
		return $res;
	}
}
?>
