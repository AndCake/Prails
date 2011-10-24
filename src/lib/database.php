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

/**
 * @desc Abstraction layer for database access
 */
class Database extends TblClass {
	 
	function Database($prefix = "tbl_") {
		parent::TblClass($prefix);
	}
	
	/**
	 * Retrieve more complex structured data from the database
	 * @param STRING $query
	 * @return Array of DBEntrys
	 */
	function query($query) {
		return $this->SqlQuery($query);
	}
	 
	/**
	 * Retrieve data from a table
	 * @param STRING $table table name (with or without prefix)
	 * @param MIXED $filter retrieve what? (example: "customer_id=12" or Array("customer_id" => 12))
	 * @param STRING $sort what and how to sort (example: "lastModified ASC")
	 */
	function get($table, $filter = "", $sort = "", $start=0, $limit=999999) {
		if (strpos($table, $this->str_prefix) === false) {
			$table = $this->str_prefix . $table;
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
		return $this->SqlQuery("SELECT * FROM ".$table." WHERE ".if_set($filter, "1").$sort." LIMIT ".$start.", ".$limit);
	}
	function select($table, $filter = "", $sort = "") { return $this->get($table, $filter, $sort); }
	
	/**
	 * Adds data to a table
	 * @param STRING $table table name (with or without prefix)
	 * @param ARRAY $data new data 
	 */
	function add($table, $data) {
		if (strpos($table, $this->str_prefix) === false) {
			$table = $this->str_prefix . $table;
		}
		return $this->InsertQuery($table, $data);
	}
	function insert($table, $data) { return $this->add($table, $data); }
	function ins($table, $data) { return $this->add($table, $data); }
	
	/**
	 * Removes data from a table
	 * @param STRING $table table name (with or without prefix)
	 * @param MIXED $filter remove what?
	 */
	function remove($table, $filter) {
		if (strpos($table, $this->str_prefix) === false) {
			$table = $this->str_prefix . $table;
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
		$this->DeleteQuery($table, if_set($filter, "FALSE"));
	}
	function delete($table, $filter) { $this->remove($table, $filter); }
	function del($table, $filter) { $this->remove($table, $filter); }

	/**
	 * Updates data in a table
	 * @param STRING $table table name (with or without prefix)
	 * @param MIXED $filter update what?
	 * @param ARRAY $data new data
	 */
	function update($table, $data, $filter) {
		if (strpos($table, $this->str_prefix) === false) {
			$table = $this->str_prefix . $table;
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
		$this->UpdateQuery($table, $data, if_set($filter, "FALSE"));
	} 
	function edit($table, $data, $filter) { $this->update($table, $data, $filter); }
	function set($table, $data, $filter = null) { 
		if ($filter == null) 
			return $this->add($table, $data); 
		else 
			$this->update($table, $data, $filter); 
	}
}

?>
