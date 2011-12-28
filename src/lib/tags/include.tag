<? if (strlen($tag["attributes"]["file"])>0) { $event = explode("/", $tag["attributes"]["file"]); ?>
	<? $path = "templates/<?=$event[0]?>/html/<?=$event[1]?>.html"; ?>
	<? if (!file_exists($path)) { 
		// we got a module's template file
		$tbl = new TblClass("tbl_prailsbase_");
		$res = @array_pop($tbl->SqlQuery("SELECT * FROM tbl_handler AS a, tbl_module AS b WHERE b.fk_user_id='".$_SESSION["builder"]["user_id"]."' AND b.module_id=a.fk_module_id AND event='".$event[1]."' AND name='".$event[0]."'"));
		$path = "templates/".$event[0].$res["module_id"]."/html/".$event[1].".html";
	} ?>
	<@=Generator::getInstance()->includeTemplate("<?=$path?>", $arr_param)@>
<? } else if (strlen($tag["attributes"]["event"])>0) { ?>
	<@=invoke("<?=$tag['attributes']['event']?>", $arr_param)@>
<? } else if (strlen($tag['attributes']['template']) > 0) { ?>
	<@=Generator::getInstance()->includeTemplate("<?=str_replace('.html', '.'.$tag['attributes']['template'].".html", $this->template)?>", $arr_param);@>
<? } ?>
