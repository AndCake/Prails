
	$arr_data = func_get_arg(0);
	return $this->InsertQuery("tbl_<?=$arr_table['name']?>", $arr_data);