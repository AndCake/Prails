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

class DBEntry extends ArrayObject {
	private $obj_tbl = null;
	
	function __construct($arr_data, $flags = 0, $iterator_class = "ArrayIterator") {
		$this->obj_tbl = new TblClass();		
		parent::__construct($arr_data, $flags, $iterator_class);
	}
	
	function offsetGet($index) {
		if (strlen($id = parent::offsetGet("fk_".$index."_id")) > 0) {
			if (parent::offsetGet($index) == null) {
				parent::offsetSet($index, @array_pop($this->obj_tbl->SqlQuery("SELECT * FROM tbl_".$index." WHERE ".$index."_id='".$id."'")));
			}
			return parent::offsetGet($index); 
		} else if (substr($index, -5) == "_list") {
			$collection_name = preg_replace("/_list\$/", "", $index);
			if (parent::offsetGet($index) == null) {
				$cols = $this->obj_tbl->obj_mysql->listColumns("tbl_".$collection_name);
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
				parent::offsetSet($index, $this->obj_tbl->SqlQuery("SELECT * FROM tbl_".$collection_name." WHERE (".if_set(implode(" OR ", $pairs), "FALSE").")"));
			}
			return parent::offsetGet($index); 
		} else {
			return parent::offsetGet($index);
		}
	} 
}

?>
