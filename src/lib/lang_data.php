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
		$this->obj_sql = new TblClass("tbl_prailsbase_");
/*		
		if (strlen($_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"]) > 0) {
			$str_lang = $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"];
			$this->language_id = $_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguageId"];
		} else { //*/
			$this->setLanguage($str_lang);
//		}
		$this->arr_item_cache = Array(); //&$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang];
	}
	 
	function setLanguage($str_lang) {
		if (IS_SETUP) {
			$arr_result = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM ".tbl_prailsbase_language." WHERE ".(strlen($str_lang) > 0 ? "abbreviation='".$str_lang."'" : "isDefault=1")));
			if (strlen($str_lang) <= 0 && DEFAULT_LANGUAGE != $arr_result["language_id"]) {
				try {
					  $conf = getConfiguration();
					  $conf["DEFAULT_LANGUAGE"] = (int)$arr_result["language_id"];
					  $toSave = Array();
					  foreach ($conf as $name => $val) {
					  	$toSave[] = Array("name" => $name, "value" => $val);
					  }
					  updateConfiguration($toSave);
				  } catch(Exception $ex) {};				  
			}
			$str_lang = $arr_result["abbreviation"];
			$_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguage"] = $str_lang;
			$this->language_id = $arr_result["language_id"];
			$_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguageId"] = $this->language_id;
			setcookie("defaultLang", $this->language_id);
			if (!$_SESSION["LangData_LANGUAGE_SETTING"][$str_lang]) $_SESSION["LangData_LANGUAGE_SETTING"][$str_lang] = Array();
		}
	}

	function getText($str_item)
	{
		if (!$this->arr_item_cache[$str_item] || !ENV_PRODUCTION)
		{
			$res = $this->obj_sql->SqlQuery(
	      	"SELECT " .
	      	"  content " .
	      	"FROM ".tbl_prailsbase_texts." " .
	      	"WHERE " .
	      	"	fk_language_id=".(int)$this->language_id." " .
	      	" AND " .
	      	"  identifier='".$str_item."'"
	      	);
	      	if ($res != null) $arr_result = @array_pop($res);
	      	if (!$arr_result) $arr_result["content"] = "{".$str_item."}";
	      	$arr_result["content"] = stripslashes(preg_replace('/^(.*)(<br>|<br\/>)$/i', '$1', $arr_result["content"]));
	      	if (strlen($arr_result["content"]) < 1024) {
	      		$this->arr_item_cache[$str_item] = $arr_result["content"];
	      	}
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
          	"FROM ".tbl_prailsbase_texts." " .
          	"WHERE " .
          	"	fk_language_id=".(int)$this->language_id." " .
          	" AND " .
          	"  identifier='".$str_item."'"
          	));
          	if (!$arr_result) $arr_result["content"] = "{".$str_item."}";
          	$arr_result["content"] = stripslashes(preg_replace('/^(.*)(<br>|<br\/>)$/i', '$1', $arr_result["content"]));
          	if (strlen($arr_result["content"]) < 1024) {
          		$this->arr_item_cache[$str_item] = $arr_result["content"];
          	}
          	
          	$arr_result["custom"] = json_decode($arr_result["custom"], true);
          	return $arr_result;
	}

	function listLanguages() {
		return $this->obj_sql->SqlQuery("SELECT * FROM ".tbl_prailsbase_language." WHERE 1=1 ORDER BY name");
	}
	 
	function listTexts() {
		$arr_result = $this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id > 0 GROUP BY identifier, texts_id, fk_language_id, content");
		$arr_return = Array();
		foreach ($arr_result as $arr_entry) {
			$parts = explode(".", $arr_entry["identifier"]);
			$curr = &$arr_return;
			foreach ($parts as $p) {
				if (!is_array($curr[$p])) {
					$curr[$p] = Array();
				}
				$curr = &$curr[$p];
			}
			$curr = $arr_entry["content"];
		}

		return $arr_return;
	}
	 
	function listAllTextsFromRoot($rootNode) {
		$arr_result = $this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id > 0 AND identifier LIKE '".$rootNode.".%'");
		foreach($arr_result as &$item) {
			$item["custom"] = json_decode($item["custom"], true);
		}
		return $arr_result;
	}
	 
	function findTextByContent($word) {
		$arr_result = $this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id > 0 AND content LIKE '%".$word."%'");
		$arr_return = Array();
		foreach ($arr_result as $res) {
			array_push($arr_return, Array("id" => "text_.".$res['identifier'], "name" => $res["identifier"], "type" => "text", "custom" => json_decode($res["custom"], true)));
		}
		return $arr_return;
	}

	function getAllTextsByIdentifier($ident) {
		$texts = $this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_language AS b LEFT JOIN tbl_prailsbase_texts AS a ON identifier='".$ident."' AND b.language_id=a.fk_language_id WHERE 1=1");
		foreach ($texts as &$text) {
			$text["default"] = $text["isDefault"];
			$text["custom"] = json_decode($text["custom"], true);
		}

		return $texts;
	}
	 
	function getAllTextsById($id) {
		$arr_text = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));

		return $this->getAllTextsByIdentifier($arr_text["identifier"]);
	}
	 
	function updateTexts($arr_data) {
		if (!is_array($arr_data["content"])) return false;
		foreach ($arr_data["content"] as $lang=>$text) {
			$entry["fk_language_id"] = $lang;
			$entry["title"] = $arr_data["title"];
			$entry["description"] = $arr_data["description"];
			$entry["identifier"] = $arr_data["identifier"];
			$entry["decorator"] = $arr_data["decorator"];
			$entry["type"] = $arr_data["type"];
			$entry["content"] = $text;
			$entry["custom"] = json_encode($arr_data["custom"]);
			if (strlen($arr_data["old_identifier"]) > 0) {
				$exists = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id=".(int)$lang." AND identifier='".$arr_data["old_identifier"]."'"));
			} else {
				$exists = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id=".(int)$lang." AND identifier='".$entry["identifier"]."'"));
			}
			if ($exists != null) {
				$this->obj_sql->UpdateQuery(tbl_prailsbase_texts, $entry, "texts_id=".(int)$exists["texts_id"]);
			} else {
				$this->obj_sql->InsertQuery(tbl_prailsbase_texts, $entry);
			}
			if (strlen($text) < 1024) {
          		$this->arr_item_cache[$arr_data["identifier"]] = $text;
          	}			
		}

		return true;
	}
	 
	function updateTextType($id, $type) {
		$exists = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));
		if ($exists != null) {
			$entry["type"] = $type;
			$this->obj_sql->UpdateQuery(tbl_prailsbase_texts, $entry, "identifier='".$exists["identifier"]."'");
			return true;
		}
		return false;
	}
	 
	function insertText($arr_data) {
		$arr_data["custom"] = json_encode($arr_data["custom"]);
		$this->obj_sql->InsertQuery(tbl_prailsbase_texts, $arr_data);
	}
	 
	function deleteTexts($id) {
		$arr_text = @array_pop($this->obj_sql->SqlQuery("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));
		$this->deleteTextByIdentifier($arr_text["identifier"]);
	}
	 
	function deleteTextByIdentifier($ident) {
		$this->obj_sql->DeleteQuery(tbl_prailsbase_texts, "identifier='".$ident."' OR fk_language_id=0");
	}
	 
	function deleteSection($section) {
		$this->obj_sql->DeleteQuery(tbl_prailsbase_texts, "identifier LIKE '".$section.".%' OR fk_language_id=0");
	}
	 
	function updateLanguage($id, $arr_data) {
		$arr_data["isDefault"] = $arr_data["default"];
//		$arr_languages = $this->listLanguages();
        $this->obj_sql->UpdateQuery(tbl_prailsbase_language, Array("isDefault" => "0"), "1=1");
/*
        foreach ($arr_languages as $lang) {
			if ($lang["isDefault"] == 0 && $id == $lang["language_id"]) {
				$arr_data["isDefault"] = 1;
			}
		}//*/
		$this->obj_sql->UpdateQuery(tbl_prailsbase_language, $arr_data, "language_id=".(int)$id."");
	}

	function insertLanguage($arr_data) {
		$arr_data["isDefault"] = $arr_data["default"];
		if (($count = count($this->listLanguages())) <= 0) {
          $arr_data["isDefault"] = 1;
      }
      if ($arr_data["isDefault"] == 1 && $count > 0) {
          // if the to-be-created language should be the new default, then 
          // set all other default languages to non-default
          $this->obj_sql->UpdateQuery(tbl_prailsbase_language, Array("isDefault" => "0"), "1=1");
      }
      $id = $this->obj_sql->InsertQuery(tbl_prailsbase_language, $arr_data);
      return $id;
   }
   
   function deleteLanguage($id) {
      $this->obj_sql->DeleteQuery(tbl_prailsbase_texts, "fk_language_id=".(int)$id." OR fk_language_id=0");      
   	  $this->obj_sql->DeleteQuery(tbl_prailsbase_language, "language_id=".(int)$id."");
   }

   function deleteLanguageOnly($id) {
      $this->obj_sql->DeleteQuery(tbl_prailsbase_language, "language_id=".(int)$id."");
   }
   
}

?>
