<? global $title, $name, $mod; 
   $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1); 
   $name = $arr_table['name'];
   $arr_fields = explode(":", $arr_table['field_names']);
   $arr_types = explode(":", $arr_table['field_types']);
   $mod = $arr_module['name'];
 
	foreach ($arr_types as $key => $type) {
   		if (in_array($type, Array("VARCHAR(1024)", "VARCHAR(255)", "FLOAT", "INT(11)", "DOUBLE", "INTEGER"))) {
   			$arr_types[$key] = "<c:input type=\"text\" label=\"{title}\" name=\"{name}\" value=\"{value}\" />\n";
   		} else if (in_array($type, Array("TEXT", "LONGTEXT"))) {
   			$arr_types[$key] = "<c:input name=\"{name}\" multiple=\"8\" value=\"{value}\" label=\"{title}\"/>\n";
   		} else if (in_array($type, Array("INT(1) NOT NULL", "TINYINT", "TINYINT NOT NULL"))) {
			$arr_types[$key] = "<c:input type=\"radio\" name=\"{name}\" value=\"{value}\" values=\"onoff\" label=\"{title}\"/>\n";
   		} else if ($type == "INT(20)" || $type == "BIGINT") {
   			$arr_types[$key] = "<c:input type=\"date\" name=\"{name}\" value=\"{value}\" label=\"{title}\" />\n";
   		} else if (in_array(preg_replace('/\\s+REFERENCES\\s+\\w+/mi', '', $type), Array("INT(11) NOT NULL", "INTEGER NOT NULL")) && substr($arr_fields[$key], 0, 3) == "fk_") {
   			$table = preg_replace("/fk_([a-zA-Z0-9]+)_id/", "\\1", $arr_fields[$key]);
   			$arr_types[$key] = "<c:input type=\"select\" name=\"{name}\" values=\"".$table."s\" value=\"{value}\" label=\"{title}\"/>";
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

<c:if cond="$arr_param['message'] == 'success'">
    <p class="success">Your changes have been saved successfully.</p>
<c:else/>
    <c:if cond="$arr_param['message'] == 'error'"> 
        <p class="error">There was an error saving your changes. Please check if you filled in all necessary fields.</p>
    </c:if>
</c:if>

<form method="post" action="<?=getUrl($mod.'/edit'.$title)?>#<?=$name?>.<?=$name?>_id">
    <fieldset>
<? foreach ($arr_fields as $key => $field) { ?>
        <?=str_replace(Array("{id}", "{name}", "{value}", "{phpvalue}", "{title}"), Array($field."-id", $name."[".$field."]", "#".$name.".".$field, "\$arr_param[\"".$name."\"][\"".$field."\"]", $field), $arr_types[$key])?>
<? } ?>

        <div class="actions">
            <button type="submit" name="save">save</button>
        </div>
    </fieldset>
</form>
<br/>
<? if ($_POST["h_scaffold"]["view"] && $_POST["d_scaffold"]["select"]) { ?>
	<a href="<?=getUrl($mod.'/view'.$title)?>#<?=$name?>.<?=$name?>_id">show</a> | 
<? } ?>
<? if ($_POST["h_scaffold"]["list"]) { ?>
<a href="<?=getUrl($mod.'/list'.$title)?>">back</a>
<? } ?>