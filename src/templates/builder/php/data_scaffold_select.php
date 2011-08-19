$id = func_get_arg(0);

return @array_pop($this->get("<?=$arr_table['name']?>", "<?=$arr_table['name']?>_id='".$id."'"));
