<? $title = strtoupper($arr_table['name'][0]).substr($arr_table['name'], 1); 
   $name = $arr_table['name'];
?>
<h2>Listing <?=$name?>s</h2>

<? $arr_fields = explode(":", $arr_table['field_names']); $arr_types = explode(":", $arr_table["field_types"]); ?>

<table border="0">
    <tr>
        <? foreach ($arr_fields as $field) { ?>
        <th><?=strtoupper($field[0]).substr($field, 1)?></th>
        <? } ?>
    </tr>
    <c:foreach var="<?=$name?>s" name="<?=$name?>">
    <tr>		
        <? foreach ($arr_fields as $key => $field) { ?>
        <td><? if ($arr_types[$key] == "INT(20)" || $arr_types[$key] == "BIGINT") { ?><?="<?=date('Y-m-d', \$arr_param['".$name."']['".$field."'])?>"?><? } else { ?>#<?=$name?>.<?=$field?><? } ?></td>
        <? } ?> 
        <td>
            <? if ($_POST["h_scaffold"]["view"] && $_POST["d_scaffold"]["select"]) { ?>
            <a href="<?=$arr_module['name']?>/view<?=$title?>/#<?=$name?>.<?=$name?>_id">view</a> |
            <? } ?>
            <? if ($_POST["h_scaffold"]["edit"] && $_POST["d_scaffold"]["insert"] && $_POST["d_scaffold"]["update"]) { ?> 
            <a href="<?=$arr_module['name']?>/edit<?=$title?>/#<?=$name?>.<?=$name?>_id">edit</a> |
            <? } ?>
            <? if ($_POST["h_scaffold"]["delete"] && $_POST["d_scaffold"]["delete"]) { ?>
            <a href="<?=$arr_module['name']?>/delete<?=$title?>/#<?=$name?>.<?=$name?>_id">delete</a>
            <? } ?>
        </td>
    </tr>
    <c:else />
    <tr>
        <td colspan="<?=count($arr_fields)?>">Currently no <?=$name?>s here.</td>
    </tr>
    </c:foreach>
</table>

<a href="<?=$arr_module['name']?>/edit<?=$title?>/0">New <?=$name?></a>
