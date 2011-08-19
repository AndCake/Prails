$arr_param = Array(
    "<?=$arr_table['name']?>s" => $data->list<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>()
);
	
return out($arr_param);