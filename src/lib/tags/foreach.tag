<? $var = $this->makeVar($tag["attributes"]["var"]); ?>
<@ if (is_array($arr_param["<?=$var?>"]) && count($arr_param["<?=$var?>"]) > 0) foreach ($arr_param["<?=$var?>"] as <?=($tag["attributes"]["key"] ? "$".$tag["attributes"]["key"]." => " : "")?>$<?=$tag["attributes"]["name"]?>) { @>
<? if ($tag["attributes"]["key"]) { ?>
	<@ $arr_param["local"]["<?=$tag["attributes"]["key"]?>"] = $<?=$tag["attributes"]["key"]?>; @>
<? } ?>
	<@ $arr_param["<?=$tag["attributes"]["name"]?>"] = $<?=$tag["attributes"]["name"]?>; @>
	<?=$tag["body"]?>
<@ } @>
