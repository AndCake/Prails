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

/** Class DBEntry
 * This class provides direct means to access data returned from the database
 * in a more convenient way. It supports normal array access as with other associative 
 * arrays and enhances the functionality by implicit and explicit data retrieval 
 * functionality. A `DBEntry` resembles everything that belongs to a single row in 
 * the underlying database.  
 *
 * The array access not only allows to access the different columns of a retrieved row,
 * but also to get access to rows that reside in other tables, but are linked to the 
 * current table by foreign keys. 
 **/
class DBEntryObject implements IteratorAggregate, ArrayAccess, Serializable, Countable {
    private $arr_data;
    private $iterator_class;
    
    public function __construct($input = Array(), $flag = 0, $iterator_class = "ArrayIterator") {
        $this->arr_data = $input;
        $this->iterator_class = $iterator_class;
    }
    
    public function append($value) {
        $this->arr_data[] = $value;
    }
    
    public function asort() {
        asort($this->arr_data);
    }
    
    public function count() {
        return count($this->arr_data);
    }

    public function exchangeArray($input) {
        $arr_result = $this->getArrayCopy();
        $this->arr_data = $input;
        
        return $arr_result;
    }
    
    public function getArrayCopy() {
        $arr_result = Array();
        
        foreach ($this->arr_data as $key => $value) {
            $arr_result[$key] = $value;
        }
        return $arr_result;
    }
    
    public function getIterator() {
        $it = $this->iterator_class;
        if (strlen($it) <= 0) $it = "ArrayIterator";
        return new $it($this->arr_data);
    }
    
    public function getIteratorClass() {
        return $this->iterator_class;
    }
    
    public function ksort() {
        ksort($this->arr_data);
    }

    public function keys() {
	return array_keys($this->arr_data);
    }

    public function natcasesort() {
        natcasesort($this->arr_data);
    }

    public function natsort() {
        natsort($this->arr_data);
    }

    public function offsetExists($index) {
        return isset($this->arr_data[$index]);
    }

    public function offsetGet($index) {
        return $this->arr_data[$index];
    }
    
    public function offsetSet($index, $newval) {
        $this->arr_data[$index] = $newval;
    }

    public function offsetUnset($index) {
        unset($this->arr_data[$index]);
    }    
    
    public function serialize() {
        return serialize($this->arr_data);
    }
    
    public function unserialize($serialized) {
        $this->arr_data = unserialize($serialized);
    }

    public function setIteratorClass($iterator_class) {
        $this->iterator_class = $iterator_class;
    }

    public function uasort($cmp_function) {
        uasort($this->arr_data, $cmp_function);
    }
    
    public function uksort($cmp_function) {
        uksort($this->arr_data, $cmp_function);
    }
}

class DBEntry extends DBEntryObject {
	private $obj_tbl = null;
	private $prefix = null;
	private $flags = null;
	private $iterator_class = null;	
	
	/**
	 * new DBEntry($data)
	 * new DBEntry($table, $data) 
	 * - $table (String) - the table's name to which this new data record should belong
	 * - $data (Array) - an associative array containing the data that should be represented by the `DBEntry`
	 * 
	 * Creates a new `DBEntry`. With the creation, it's not yet sent to the database. In order to persist the
	 * record, call it's `[DBEntry]save` method. When using the second call method of this constructor, it
	 * will set the record's primary key to be 0, thus when saving, it will create a new record in the database.
	 *
	 * *Example:*
	 * {{{
	 * $mynewuser = new DBEntry("user", Array(
	 * 	"firstName" => "Test",
	 * 	"lastName" => "User",
	 * 	"email" => "test@example.org"
	 * ));
	 * $mynewuser->save();
	 *
	 * // the following snippet will do exactly the same as the above
	 * $mynewuser = new DBEntry(Array(
	 * 	"user_id" => 0,
	 * 	"firstName" => "Test",
	 * 	"lastName" => "User",
	 * 	"email" => "test@example.org"
	 * ));
	 * $mynewuser->save();
	 * }}}
	 * This example creates two DBEntry objects with the same user information. Both are persisted, which 
	 * results in two new database records.
	 **/
	function __construct($arr_data, $flags = 0, $iterator_class = "ArrayIterator", $prefix = "tbl_") {
		if (is_string($arr_data)) {
			$table = $arr_data;
			if (strpos($table, $prefix) === 0) $table = str_replace($prefix, "", $table);
			$arr_data = $flags;
			$arr_data[$table."_id"] = 0;
			$flags = 0;
		}	
		$this->obj_tbl = new Database($prefix);
		$this->prefix = $prefix;		
		$this->flags = $flags;
		$this->iterator_class = $iterator_class;

		parent::__construct($arr_data, $flags, $iterator_class);
	}

    function serialize() {
    	$data = parent::serialize();
        return serialize(Array("data" => $data, "prefix" => $this->prefix, "flags" => $this->flags, "iterator_class" => $this->iterator_class));
	}
    
    function unserialize($data) {
        $data = unserialize($data);
		parent::unserialize($data["data"]);
        $this->prefix = $data["prefix"];
		$this->flags = $data["flags"];
		$this->iterator_class = $data["iterator_class"];
		$this->obj_tbl = new TblClass($this->prefix);
    }	

    /**
     * getArrayCopy() -> Array
     * 
     * this method will return an associative array that corresponds to the structure
     * of the `DBEntry` object, thus reflecting exactly the same data, but leaving out
     * the dynamic functionality of retrieving additional information.
     **/

        /**
         * get($index[, $filter[, $name]]) -> Array|DBEntry|String|Number
         * - $index (String) - the column name to get value(s) for.
         * - $filter (String|Array) - a where clause or an array whereas the key-value pairs are joined by an `AND`
         * - $name (String) - the name as which it should be stored in the current `DBEntry` instance.
         * 
         * retrieves the value for a given column. If this column does not exist, it checks whether there exists a 
         * foreign key for it and resolves it to the row in the linked table it references. For reverse foreign key
         * lookups, the column name convention is `&lt;tablename&gt;_list`. This will look up all rows that have 
         * referenced the current row as foreign key.
         * 
         * *Example:* 
         * {{{
         * <p>Comment for post #comment.post.title</p>
         * <p>Comment for post <%=$arr_param['comment']['post']['title']%></p>
         * <p>Comment for post <%=$arr_param['comment']->get('post')->get('title')%></p>
         * }}} 
         * ![Example's DB structure](static/images/doc/example-db.png)The above three statements all do exactly the same: access the `DBEntry` in `$arr_param['comment']` (which
         * would have been retrieved in the handler code) and fetch the row referenced through `fk_post_id` in table 
         * `post`, which will result in a `DBEntry` object, of which in turn the `title` attribute is printed out. 
         * 
         * *Example 2:*
         * {{{
         * <%=count($arr_param['post']->get("comment_list", "content<>''", "mycomments"))%>
         * <c:foreach var="post.mycomments" name="comment">
         *    <p>#comment.content</p>
         * </c:foreach>
         * }}}
         * In this example the handler code would have fetched a `DBEntry` `post`, which is used here to retrieve
         * all comments that have a non-empty content attribute. The result (which is an array of `DBEntry` objects)
         * will be returned immediately but also stored to the `post` object to be accessible via the name `mycomments`, 
         * which is afterwards used to loop over it and print out the comment's contents.
         **/	
	function get($index, $filter = "", $name = "") {
		if (strlen($id = parent::offsetGet("fk_".$index."_id")) > 0) {
			if (is_array($filter)) {
				$res = "";
				foreach ($filter as $key => $value) {
					if (strlen($res) > 0) $res .= " AND ";
					$res .= $key."='".$this->obj_tbl->escape($value)."'";
				}
				$filter = $res;
			}		
			$mix = $index.md5($filter);
			if (parent::offsetGet($mix) == null) {
				parent::offsetSet($mix, @array_pop($this->obj_tbl->SqlQuery("SELECT * FROM ".$this->prefix.$index." WHERE ".$index."_id='".$id."' AND ".if_set($filter, "1")."")));
				if (strlen($name) > 0) {
					parent::offsetSet($name, parent::offsetGet($mix));					
				}
			}
			return parent::offsetGet($mix); 
		} else if (substr($index, -5) == "_list") {
			if (is_array($filter)) {
				$res = "";
				foreach ($filter as $key => $value) {
					if (strlen($res) > 0) $res .= " AND ";
					$res .= $key."='".$this->obj_tbl->escape($value)."'";
				}
				$filter = $res;
			}		
			$collection_name = preg_replace("/_list\$/", "", $index);
			$mix = $index.md5($filter);
			if (parent::offsetGet($mix) == null) {
				$cols = $this->obj_tbl->obj_mysql->listColumns($this->prefix.$collection_name);
				$lCols = Array();
				foreach ($cols as $col) {
					array_push($lCols, $col["Field"]);
				}
				$list = parent::keys();
				$pairs = Array();
				foreach ($list as $entry) {
					if (preg_match("/^([^f]|f[^k]|fk[^_])[a-zA-Z0-9_]*_id\$/", $entry) > 0 && in_array("fk_".$entry, $lCols)) {
						array_push($pairs, "fk_".$entry."='".parent::offsetGet($entry)."'");
					}
				}
				parent::offsetSet($mix, $this->obj_tbl->SqlQuery("SELECT * FROM ".$this->prefix.$collection_name." WHERE (".if_set(implode(" OR ", $pairs), "1=0").") AND ".if_set($filter, "1").""));
				if (strlen($name) > 0) {
					parent::offsetSet($name, parent::offsetGet($mix));					
				}
			}
			return parent::offsetGet($mix); 
		} else {
			return parent::offsetGet($index);
		}
	}
	
	function offsetGet($index) {
		if (strlen($id = parent::offsetGet("fk_".$index."_id")) > 0) {
			if (parent::offsetGet($index) == null) {
				parent::offsetSet($index, @array_pop($this->obj_tbl->SqlQuery("SELECT * FROM ".$this->prefix.$index." WHERE ".$index."_id='".$id."'")));
			}
			return parent::offsetGet($index); 
		} else if (substr($index, -5) == "_list") {
			$collection_name = preg_replace("/_list\$/", "", $index);
			if (parent::offsetGet($index) == null) {
				$cols = $this->obj_tbl->obj_mysql->listColumns($this->prefix.$collection_name);
				$lCols = Array();
				foreach ($cols as $col) {
					array_push($lCols, $col["Field"]);
				}
				$list = parent::keys();
				$pairs = Array();
				foreach ($list as $entry) {
					if (preg_match("/^([^f]|f[^k]|fk[^_])[a-zA-Z0-9_]*_id\$/", $entry) > 0 && in_array("fk_".$entry, $lCols)) {
						array_push($pairs, "fk_".$entry."='".parent::offsetGet($entry)."'");
					}
				}
				parent::offsetSet($index, $this->obj_tbl->SqlQuery("SELECT * FROM ".$this->prefix.$collection_name." WHERE (".if_set(implode(" OR ", $pairs), "1=0").")"));
			}
			return parent::offsetGet($index); 
		} else {
			return parent::offsetGet($index);
		}
	} 

	/**
         * save() -> boolean
	 * 
	 * saves the current `DBEntry` back into it's original table. If the `DBEntry` contains the result
	 * of a complex SQL query (one that was joined, unioned or similar), it will return `false` and 
	 * write a warning into the warn log. If saving the record was successful, it will return `true`.
	 * If the primary key is set to 0 (zero), it will create a new database record from the `DBEntry`.
	 *
	 * *Example:* 
	 * {{{
	 * $users = $this->select("user", "last_login > UNIX_TIMESTAMP() - 3600");
	 * foreach ($users as $user) {
	 * 	$user["name"] = "Carl";
	 * 	$user->save();
	 * }
	 * }}}
	 * This example fetches all users that logged in during the last hour and updates their names 
	 * to be "Carl" and saves that back into the database.
	 * 
	 * *Example 2:*
	 * {{{
	 * $user = new DBEntry(Array(
	 *      "user_id" => 0,
	 *      "firstName" => "Test",
	 *      "lastName" => "User"
	 * ));
	 * $user->save();
	 * }}}
	 * This example creates a new `DBEntry` from an array and then creates the corresponding database record
	 * in table `tbl_user` by calling the `save` method.
	 **/
	function save() {
		global $log;
		// check if eligable
		$keys = parent::keys();
		$primaryKeys = 0;
		$lastPK = "";
		foreach ($keys as $key) {
			if (preg_match('/^(?!fk_)([a-z_A-Z]+)_id$/', $key, $match)) {
				$primaryKeys++;
				$lastPK = $match[1];
			}
		}
		if ($primaryKeys > 1) {
			$log->warn("Unable to save DBEntry: it was a complex query result.");
			return false;	// unable to save
		}
		$table = "tbl_".$lastPK;
		$pk = $lastPK."_id";
		if (parent::offsetGet($pk) < 0 || !is_numeric(parent::offsetGet($pk))) {
			$log->warn("Unable to save DBEntry: invalid primary key value.");
			return false;
		} else if (parent::offsetGet($pk) === 0) {
			$arr_data = parent::getArrayCopy();
			unset($arr_data[$pk]);
			$this->obj_tbl->InsertQuery($table, $arr_data);
		} else {
			$this->obj_tbl->UpdateQuery($table, parent::getArrayCopy(), $pk."=".parent::offsetGet($pk));
		}
		return true;
	}

	/**
	 * delete() -> boolean
	 * 
	 * This method removes the underlying database record of the current `DBEntry`. If the `DBEntry` 
	 * contains the result of a complex SQL query (one that was joined, unioned or similar), it 
	 * will return `false` and write a warning into the warn log. If the deletion of this record was
	 * successful, it will return `true`. Please note: in order to actually remove the record, it 
	 * needs to exist.
	 *
	 * *Example:*
	 * {{{
	 * $comment = $this->getItem("comment", 3);
	 * $comment->delete();
	 * }}}
	 * This example will remove the record `3` from the table `comment`. 
	 **/
	function delete() {
		global $log;
		// check if eligable
		$keys = parent::keys();
		$primaryKeys = 0;
		$lastPK = "";
		foreach ($keys as $key) {
			if (preg_match('/^(?!fk_)([a-z_A-Z]+)_id$/', $key, $match)) {
				$primaryKeys++;
				$lastPK = $match[1];
			}
		}
		if ($primaryKeys > 1) {
			$log->warn("Unable to save DBEntry: it was a complex query result.");
			return false;	// unable to save
		}
		$table = "tbl_".$lastPK;
		$pk = $lastPK."_id";
		if (parent::offsetGet($pk) <= 0 || !is_numeric(parent::offsetGet($pk))) {
			$log->warn("Unable to delete DBEntry: invalid primary key value.");
			return false;
		}
		$this->obj_tbl->DeleteQuery($table, $pk."=".parent::offsetGet($pk));
		return true;
	}
}

?>
