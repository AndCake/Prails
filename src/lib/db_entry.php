<?php

class DBEntry extends ArrayObject {
	private $obj_tbl = null;
	
	function __construct($arr_data, $flags = 0, $iterator_class = "ArrayIterator") {
		$this->obj_tbl = new TblClass();		
		parent::__construct($arr_data, $flags, $iterator_class);
	}
	
	function offsetGet($index) {
		if (strlen($id = parent::offsetGet("fk_".$index."_id")) > 0) {
			return @array_pop($this->obj_tbl->SqlQuery("SELECT * FROM tbl_".$index." WHERE ".$index."_id='".$id."'")); 
		} else {
			return parent::offsetGet($index);
		}
	} 
}

?>