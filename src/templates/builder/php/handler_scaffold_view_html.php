<? $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1);
   $name = $arr_table['name'];
   $arr_fields = explode(":", $arr_table['field_names']);
   $arr_types = explode(":", $arr_table['field_types']);
?>
<h2><?=$title?> Details</h2>

<table border="1" cellspacing="0" cellpadding="5">
<? foreach ($arr_fields as $key=>$field) { ?>
<tr>
    <th><?=strtoupper($field[0]).substr($field, 1)?>: </th>
    <td>
        <? if ($arr_types[$key] == "INT(20)") { ?><?="<?=date('Y-m-d', \$arr_param['".$name."']['".$field."'])?>"?><? } else { ?>#<?=$name?>.<?=$field?><? } ?>
    </td>
</tr>
<? } ?>
</table>

<? if ($_POST["h_scaffold"]["view"] && $_POST["d_scaffold"]["select"]) { ?>	
<a href="<?=$arr_module['name']?>/edit<?=$title?>/#<?=$name?>.<?=$name?>_id">edit</a> |
<? } ?>
<? if ($_POST["h_scaffold"]["edit"] && $_POST["d_scaffold"]["insert"] && $_POST["d_scaffold"]["update"]) { ?> 
<a href="<?=$arr_module['name']?>/delete<?=$title?>/#<?=$name?>.<?=$name?>_id">delete</a> |
<? } ?> 
<? if ($_POST["h_scaffold"]["list"] && $_POST["d_scaffold"]["list"]) { ?>
<a href="<?=$arr_module['name']?>/list<?=$title?>">back</a>
<? } ?>