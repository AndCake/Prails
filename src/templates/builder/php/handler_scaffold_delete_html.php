<? $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1); 
   $name = $arr_table['name'];
   $arr_fields = explode(":", $arr_table['field_names']);
?>
<h2>Delete <?=$title?></h2>

<p>Do you really want to remove this <?=$name?>?</p>

<table border="0">
   <? foreach ($arr_fields as $field) { ?>
   <tr>
      <th><?=$field?></th>
      <td><? if ($arr_types[$key] == "INT(20)") { ?><?="<?=date('Y-m-d', \$arr_param['".$name."']['".$field."'])?>"?><? } else { ?>#<?=$name?>.<?=$field?><? } ?></td>
   </tr>
   <? } ?>
</table>

<form method="post" action="<?=$arr_module['name']?>/delete<?=$title?>/#<?=$name?>.<?=$name?>_id">
   <fieldset>
      <a href="<?=$arr_module['name']?>/list<?=$title?>">No, better not.</a>
      <button type="submit" name="confirm">Yes!</button>
   </fieldset>		
</form>

<br/>
<? if ($_POST["h_scaffold"]["list"] && $_POST["d_scaffold"]["list"]) { ?>
<a href="<?=$arr_module['name']?>/list<?=$title?>">back</a>
<? } ?>

