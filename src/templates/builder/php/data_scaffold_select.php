
	$id = func_get_arg(0);
	return @array_pop($this->SqlQuery("SELECT * FROM tbl_<?=$arr_table['name']?> WHERE <?=$arr_table['name']?>_id='".$id."'"));
