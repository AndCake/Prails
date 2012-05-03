<? global $title, $name, $mod;
   $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1); 
   $name = $arr_table['name'];
   $arr_fields = explode(":", $arr_table['field_names']);
   $mod = $arr_module['name'];
?>
<h2>Delete <?=$title?></h2>

<p>Do you really want to remove this <?=$name?>?</p>

<table border="0">
   <? foreach ($arr_fields as $field) { ?>
   <tr>
      <th><?=$field?></th>
      <td><? if ($arr_types[$key] == "INT(20)" || $arr_types[$key] == "BIGINT") { ?><?="<?=date('Y-m-d', \$arr_param['".$name."']['".$field."'])?>"?><? } else { ?>#<?=$name?>.<?=$field?><? } ?></td>
   </tr>
   <? } ?>
</table>

<form method="post" action="<?=getUrl($mod.'/delete'.$title)?>#<?=$name?>.<?=$name?>_id">
   <fieldset>
      <a href="<?=getUrl($mod."/".($_POST["h_scaffold"]["list"] ? 'list' : 'view').$title)?>#<?=$name?>.<?=$name?>_id">No, better not.</a>
      <button type="submit" name="confirm">Yes!</button>
   </fieldset>		
</form>

<br/>
<? if ($_POST["h_scaffold"]["list"] && $_POST["d_scaffold"]["list"]) { ?>
<a href="<?=getUrl($mod.'/list'.$title)?>">back</a>
<? } ?>