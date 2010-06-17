<?php

class LangData
{
   var $obj_sql;
   var $language_id;

   var $arr_item_cache;

   function LangData($str_lang)
   {
      $this->obj_sql = new TblClass();
      $arr_result = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM ".tbl_language." WHERE abbreviation='".$str_lang."'"));
      $this->language_id = $arr_result["language_id"];
      if (!$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang]) $_SESSION["LangData_LANGUAGE_SETTING"][$str_lang] = Array();
      $this->arr_item_cache = &$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang];
   }

   function getText($str_item)
   {
    	if (!$this->arr_item_cache[$str_item])
    	{
	     	$arr_result = @array_pop($this->obj_sql->SqlQuery(
	      	"SELECT " .
	      	"  content " .
	      	"FROM ".tbl_texts." " .
	      	"WHERE " .
	      	"	fk_language_id='".$this->language_id."' " .
	      	" AND " .
	      	"  identifier='".$str_item."'"
	    	));
	    	if (!is_array($arr_result)) $arr_result["content"] = "{".$str_item."}";
	 		$arr_result["content"] = stripslashes(preg_replace('/^(.*)(<br>|<br\/>)$/i', '$1', $arr_result["content"]));
	    	$this->arr_item_cache[$str_item] = $arr_result["content"];
    	} else
    	{
    		$arr_result["content"] = $this->arr_item_cache[$str_item];
    	}
      return $arr_result["content"];
   }

   function listLanguages()
   {
     return $this->obj_sql->SqlQuery("SELECT * FROM ".tbl_language." WHERE 1 ORDER BY name");
   }

}

?>