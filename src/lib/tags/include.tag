<? if (strlen($tag["attributes"]["file"])>0) { ?>
	<@=Generator::getInstance()->includeTemplate("<?=$tag['attributes']['file']?>", $arr_param)@>
<? } else if (strlen($tag["attributes"]["event"])>0) { ?>
	<@=invoke("<?=$tag['attributes']['event']?>", $arr_param)@>
<? } ?>
