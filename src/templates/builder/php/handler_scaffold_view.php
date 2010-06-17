   $_SESSION["<?=$arr_table['name']?>_id"] = if_set($_GET["<?=$arr_table['name']?>_id"], $_SESSION["<?=$arr_table['name']?>_id"]);

   $arr_param = Array(
      "<?=$arr_table['name']?>" => $data->select<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_SESSION["<?=$arr_table['name']?>_id"])
   );
	
   return out($arr_param);