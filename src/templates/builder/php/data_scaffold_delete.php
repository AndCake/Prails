
	$id = func_get_arg(0);
	$this->DeleteQuery("tbl_<?=$arr_table['name']?>", "<?=$arr_table['name']?>_id='".$id."'");
