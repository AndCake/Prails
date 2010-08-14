<?php
/**
    PRails Web Framework
    Copyright (C) 2010  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class LangData
{
   var $obj_sql;
   var $language_id;

   var $arr_item_cache;

   function LangData($str_lang)
   {
      $this->obj_sql = new TblClass();
      if (strlen($_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"]) > 0) {
          $str_lang = $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"];
          $this->language_id = $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguageId"];
      } else {
         $this->setLanguage($str_lang);
      }
      $this->arr_item_cache = &$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang];
   }
   
   function setLanguage($str_lang) {
       $arr_result = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM ".tbl_language." WHERE ".(strlen($str_lang) > 0 ? "abbreviation='".$str_lang."'" : "default=1")));
       $str_lang = $arr_result["abbreviation"];
       $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"] = $str_lang;
       $this->language_id = $arr_result["language_id"];
       $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguageId"] = $this->language_id;
       if (!$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang]) $_SESSION["LangData_LANGUAGE_SETTING"][$str_lang] = Array();
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
   
   function selectTextByIdentifier($str_item) {
     	$arr_result = @array_pop($this->obj_sql->SqlQuery(
          	"SELECT " .
          	"  * " .
          	"FROM ".tbl_texts." " .
          	"WHERE " .
          	"	fk_language_id='".$this->language_id."' " .
          	" AND " .
          	"  identifier='".$str_item."'"
    	));
    	if (!$arr_result) $arr_result["content"] = "{".$str_item."}";
 		$arr_result["content"] = stripslashes(preg_replace('/^(.*)(<br>|<br\/>)$/i', '$1', $arr_result["content"]));
    	$this->arr_item_cache[$str_item] = $arr_result["content"];
        return $arr_result;
   }

   function listLanguages()
   {
     return $this->obj_sql->SqlQuery("SELECT * FROM ".tbl_language." WHERE 1 ORDER BY name");
   }
   
   function listTexts() {
      $arr_result = $this->obj_sql->SqlQuery("SELECT * FROM tbl_texts WHERE fk_language_id > 0 GROUP BY identifier");
      $arr_return = Array();
      foreach ($arr_result as $arr_entry) {
         eval("\$arr_return[\"".str_replace(".", "\"][\"", $arr_entry["identifier"])."\"] = \"".addslashes($arr_entry["content"])."\";");
      }
      
      return $arr_return;
   }

   function getAllTextsByIdentifier($ident) {
        $texts = $this->obj_sql->SqlQuery("SELECT * FROM tbl_language AS b LEFT JOIN tbl_texts AS a ON identifier='".$ident."' AND b.language_id=a.fk_language_id WHERE 1");
        
        return $texts;
   }
   
   function getAllTextsById($id) {
        $arr_text = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_texts WHERE texts_id='".$id."'"));
        
        return $this->getAllTextsByIdentifier($arr_text["identifier"]);
   }
   
   function updateTexts($arr_data) {
      if (!is_array($arr_data["content"])) return false;
      foreach ($arr_data["content"] as $lang=>$text) {
         $entry["fk_language_id"] = $lang;
         $entry["identifier"] = $arr_data["identifier"];
         $entry["decorator"] = $arr_data["decorator"];
         $entry["type"] = $arr_data["type"];
         $entry["content"] = $text;
         $exists = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_texts WHERE fk_language_id='".$lang."' AND identifier='".$entry["identifier"]."'"));
         if ($exists != null) {
             $this->obj_sql->UpdateQuery(tbl_texts, $entry, "texts_id='".$exists["texts_id"]."'");
         } else {
             $this->obj_sql->InsertQuery(tbl_texts, $entry);            
         }
      }
      
      return true;
   }
   
   function updateTextType($id, $type) {
         $exists = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_texts WHERE texts_id='".$id."'"));
         if ($exists != null) {
             $entry["type"] = $type;
             $this->obj_sql->UpdateQuery(tbl_texts, $entry, "identifier='".$exists["identifier"]."'");
             return true;
         }
         return false;
   }
   
   function deleteTexts($id) {
        $arr_text = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_texts WHERE texts_id='".$id."'"));
        $this->deleteTextByIdentifier($arr_text["identifier"]);
   }
   
   function deleteTextByIdentifier($ident) {
        $this->obj_sql->DeleteQuery(tbl_texts, "identifier='".$ident."'");
   }
   
   function deleteSection($section) {
        $this->obj_sql->DeleteQuery(tbl_texts, "identifier LIKE '".$section.".%'");
   }
   
   function updateLanguage($id, $arr_data) {
      $this->obj_sql->UpdateQuery(tbl_language, $arr_data, "language_id='".$id."'");
   }

   function insertLanguage($arr_data) {
      if (($count = count($this->listLanguages())) <= 0) {
          $arr_data["default"] = 1;
      }
      if ($arr_data["default"] == 1 && $count > 0) {
          $this->obj_sql->UpdateQuery(tbl_language, Array("default" => "0"), "1");
      }
      return $this->obj_sql->InsertQuery(tbl_language, $arr_data);
   }
   
   function deleteLanguage($id) {
      $this->obj_sql->DeleteQuery(tbl_language, "language_id='".$id."'");
      $this->obj_sql->DeleteQuery(tbl_texts, "fk_language_id='".$id."'");      
   }

}

?>
