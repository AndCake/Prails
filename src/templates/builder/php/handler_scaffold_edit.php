<? $field_types = explode(":", $arr_table["field_types"]);
   $field_names = explode(":", $arr_table["field_names"]);
   
   $names = Array(); $fks = Array();
   foreach ($field_types as $key => $type) {
      if (!in_array($type, Array("INT(11) NOT NULL", "INTEGER NOT NULL")) && substr($field_names[$key], 0, 3) != "fk_") {
      	$names[$key] = $field_names[$key];
      } else {
      	array_push($fks, Array("field" => $field_names[$key], "table" => preg_replace("/fk_([a-zA-Z0-9]+)_id/", "\\1", $field_names[$key]), "id" => str_replace("fk_", "", $field_names[$key])));
      }
   }
   $field_names = implode('", "', $names);
?>
/*[BEGIN POST-save]*/
if (isset($_POST["new"])) { 
	/*[ACTUAL]*/
$arr_data = $_POST["<?=$arr_table['name']?>"];
		
if (checkFields($arr_data, Array("<?=$field_names?>"))) {
    if ($_SESSION["<?=$arr_table['name']?>_id"] > 0) {
        $data->update<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_SESSION["<?=$arr_table['name']?>_id"], $arr_data);
    } else {
        $_SESSION["<?=$arr_table['name']?>_id"] = $data->insert<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($arr_data);
    }
			
    $arr_param["message"] = "success";
} else {
    $arr_param["message"] = "error";
}

$_SESSION["<?=$arr_table['name']?>_id"] = if_set($_GET["<?=$arr_table['name']?>_id"], $_SESSION["<?=$arr_table['name']?>_id"]);
$arr_param["<?=$arr_table['name']?>"] = $data->select<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_SESSION["<?=$arr_table['name']?>_id"]);
<? foreach ($fks as $fk) { ?>$arr_param["<?=$fk['table']?>s"] = $data->list<?=strtoupper($fk['table'][0]).substr($fk['table'],1)?>();<? } ?>
	   
return out($arr_param);
/*[END ACTUAL]*/
	session_write_close();
	die();
}
/*[END POST-save]*/
$_SESSION["<?=$arr_table['name']?>_id"] = if_set($_GET["<?=$arr_table['name']?>_id"], $_SESSION["<?=$arr_table['name']?>_id"]);
	
$arr_param["<?=$arr_table['name']?>"] = $data->select<?=strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1)?>($_SESSION["<?=$arr_table['name']?>_id"]);
<? foreach ($fks as $fk) { ?>$arr_param["<?=$fk['table']?>s"] = $data->list<?=strtoupper($fk['table'][0]).substr($fk['table'],1)?>();<? } ?>
   
return out($arr_param);