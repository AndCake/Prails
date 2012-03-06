<?
/** Section Tags
 * <c:include (event="<event-name>" | file="<event-name>" | template="<template-name>")/>
 *
 * Includes a whole event handler's result or simply a template of another event handler. In case that 
 * just a template should be included, the path to that template is `&lt;module-name&gt;/&lt;event-handler-name&gt;`. 
 * It then has some similar characteristics to a decorator, except it does not embed something, but is 
 * embedded into something. 
 * 
 * *Example:*
 * {{{
 * &lt;!-- calls the "user:list" event handler and renders it's result --&gt;
 * &lt;c:include event="user:list"/&gt;
 * &lt;!-- includes the default template from module "user" and event handler "detail", it is evaluated immediately --&gt;
 * &lt;c:include file="user/detail"/&gt;
 * &lt;!-- includes the template "mail" from the current event handler --&gt;
 * &lt;c:include template="mail"/&gt;
 * }}}
 *
 **/
?><? if (strlen($tag["attributes"]["file"])>0) { $event = explode("/", $tag["attributes"]["file"]); ?>
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
