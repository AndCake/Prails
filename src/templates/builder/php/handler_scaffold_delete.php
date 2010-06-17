
   if ($_GET["<?=$arr_table['name']?>_id"] > 0 && $_GET["confirmed"] == "1") {
      $data->delete<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_GET["<?=$arr_table['name']?>_id"]);
      $arr_param["message"] = "success";
	  
      return $this->list<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>();
   } else {
      $arr_param["message"] = "error";
   }
   
   $arr_param["<?=$arr_table['name']?>"] = $data->select<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_GET["<?=$arr_table['name']?>_id"]);
	
   return out($arr_param);