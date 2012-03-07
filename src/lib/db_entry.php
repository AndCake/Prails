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
    
    /**
     * getArrayCopy() -> Array
     * 
     * this method will return an associative array that corresponds to the structure
     * of the `DBEntry` object, thus reflecting exactly the same data, but leaving out
     * the dynamic functionality of retrieving additional information.
     **/
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
	
	function __construct($arr_data, $flags = 0, $iterator_class = "ArrayIterator", $prefix = "tbl_") {
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
				$list = array_keys(parent::getArrayCopy());
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
				$list = array_keys(parent::getArrayCopy());
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
}

?>
