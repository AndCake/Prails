<? $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1); 
   $name = $arr_table['name'];
   $arr_fields = explode(":", $arr_table['field_names']);
   $arr_types = explode(":", $arr_table['field_types']);
   
	foreach ($arr_types as $key => $type) {
   		if (in_array($type, Array("VARCHAR(1024)", "VARCHAR(255)", "FLOAT", "INT(11)", "DOUBLE", "INTEGER"))) {
   			$arr_types[$key] = "<input type='text' id='{id}' name='{name}' value='{value}' />\n";
   		} else if (in_array($type, Array("TEXT", "LONGTEXT"))) {
   			$arr_types[$key] = "<textarea id='{id}' name='{name}' cols='40' rows='8'>{value}</textarea>\n";
   		} else if (in_array($type, Array("INT(1) NOT NULL", "TINYINT"))) {
   			$arr_types[$key] = "<input type='radio' id='{id}_on' name='{name}' value='1' <"."?=((int){phpvalue} == '1' ? \" checked='checked'\":\"\")?"."> /><label for='{id}_on'> on</label>&nbsp;&nbsp;".
							   "<input type='radio' id='{id}_off' name='{name}' value='0' <"."?=((int){phpvalue} == '0' ? \" checked='checked'\":\"\")?".">  /><label for='{id}_off'> off</label>\n";			
   		} else if ($type == "INT(20)") {
   			$arr_types[$key] = "<input type='date' id='{id}' name='{name}' value='<"."?=((int){phpvalue} <= 0 ? time() : {phpvalue})?".">' />\n";
   		} else if (in_array($type, Array("INT(11) NOT NULL", "INTEGER NOT NULL")) && substr($arr_fields[$key], 0, 3) == "fk_") {
   			$table = preg_replace("/fk_([a-zA-Z0-9]+)_id/", "\\1", $arr_fields[$key]);
   			$arr_types[$key] = "<select name='{name}' size='1'>\n<option value='' disabled='disabled'>Choose...</option>\n".
							   "<? foreach (\$arr_param[\"".$table."s\"] as \$arr_".$table.") { ?>\n".
							   "<option value='<?=\$arr_".$table."['".$table."_id']?>' <?=(\$arr_".$table."['".$table."_id'] == \$arr_param['".$arr_table['name']."']['fk_".$table."_id'] ? \" selected='selected'\" : \"\")?>><?=\$arr_".$table."['".$table."_id']?></option>\n".
							   "<? } ?>\n".
							   "</select>\n";
		}
   	}
?>
<h2>
   <c:if cond="$arr_param['<?=$arr_table['name']?>']['<?=$arr_table['name']?>_id'] > 0">
      Edit
   <c:else />
      Create
   </c:if>
   <?=$name?>
</h2>

<?="<? if (\$arr_param['message'] == 'success') { ?>\n"?>
   <p class="success">Your changes have been saved successfully.</p>
<?="<? } else if (\$arr_param['message'] == 'error') { ?>"?> 
   <p class="error">There was an error saving your changes. Please check if you filled in all necessary fields.</p>
<?="<? } ?>"?>

<form method="post" action="<?=$arr_module['name']?>/edit<?=$title?>/#<?=$name?>.<?=$name?>_id">
   <fieldset>
      <? foreach ($arr_fields as $key => $field) { ?>
      <div class="form-entry">
         <label for="<?=$field?>-id"><?=strtoupper($field[0]).substr($field, 1)?></label>
         <?=str_replace(Array("{id}", "{name}", "{value}", "{phpvalue}"), Array($field."-id", $name."[".$field."]", "#".$name.".".$field, "\$arr_param[\"".$name."\"][\"".$field."\"]"), $arr_types[$key])?>
      </div>
      <? } ?>
      <button type="submit" name="save">save</button>
   </fieldset>
</form>
<br/>
<? if ($_POST["h_scaffold"]["view"] && $_POST["d_scaffold"]["select"]) { ?>
	<a href="<?=$arr_module['name']?>/view<?=$title?>/#<?=$name?>.<?=$name?>_id">show</a> | 
<? } ?>
<? if ($_POST["h_scaffold"]["list"] && $_POST["d_scaffold"]["list"]) { ?>
<a href="<?=$arr_module['name']?>/list<?=$title?>">back</a>
<? } ?>