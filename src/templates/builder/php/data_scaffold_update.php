$id = func_get_arg(0);
$arr_data = func_get_arg(1);
	
$this->set("<?=$arr_table['name']?>", $arr_data, "<?=$arr_table['name']?>_id='".$id."'");