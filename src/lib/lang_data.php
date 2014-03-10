<?php
/**
 Prails Web Framework
 Copyright (C) 2013  Robert Kunze

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

/** Class Language
 * 
 * This class allows for access to localization data and content assets. Content assets are
 * static texts that can be entered via the Prails IDE. They are referenced through a so-called
 * identifier. You can insert these identifiers as placeholders in templates at any position where
 * localized content should be displayed, depending on the language chosen.
 *
 * In a template the identifiers should usually be placed inside curly braces, which are called 
 * "language tags". Their syntax looks like so:
 * {{{ 
 * {&lt;identifier&gt;}
 * }}}
 * The identifier itself contains a hierarchical path information, which always has the following
 * structure:
 * {{{
 * &lt;top section&gt;.[&lt;sub section&gt;.]&lt;text name&gt;
 * }}}
 * In many cases it turned out to be useful to reflect the module's structure in the hierarchical
 * view of the translations section, as then it is much easier to create, find and change their 
 * translations. For texts that are globally used, it is encouraged to structure and group them 
 * according to their abstract intention under a "global" top level section.
 *
 * For development environments the translation that replaces it's language tag when executed
 * will be surrounded by HTML comments so that it's easier to find the corresponding tag in the 
 * "Static Contents" section of the IDE. If you, however don't want this to happen (maybe because you 
 * are using this language tag in a Javascript string or some other critical position), you can tell 
 * Prails to only output the actual translation without HTML comments by adding a $ sign at the end of the tag name.
 * 
 * _Note:_ All identifiers are case sensitive.
 * 
 * *Example:*
 * {{{
 * ...
 * &lt;div class="context-box-head"&gt;
 *    &lt;h1&gt;{customer.login.title}&lt;/h1&gt;
 * &lt;/div&gt;
 * &lt;div class="context-box-body"&gt;
 *    &lt;div class="input-labelling"&gt;{customer.login.username}&lt;/div&gt;
 *    &lt;div class="inputfield"&gt;&lt;input type="text" class="required" name="login[username]" /&gt;
 * ...
 *    &lt;div class="input-labelling"&gt;{customer.login.password}&lt;/div&gt;
 *    &lt;div class="inputfield"&gt;
 *       &lt;!-- the password hint should not be surrounded by HTML comments in development --&gt;
 *       &lt;input class="required" title="{customer.login.passwordHint$}" name="login[password]" type="password" value="" /&gt;
 *    &lt;/div&gt;
 * &lt;/div&gt;
 * &lt;div class="content-box-footer"&gt;
 *    &lt;button class="submit" type="submit"&gt;{customer.login.login}&lt;/button&gt;
 * &lt;/div&gt;
 * ...
 * }}}
 * This example demonstrates how it is actually used within templates (like the event handler's output code).
 * 
 * _Note:_ Within `[Handler]Handler` code, you always have access to the language and CMS API via `$currentLang`.
 * 
 *
 * *The CMS*
 * 
 * The CMS let's you create and manage static pages. These static pages can be edited using a WYSIWYG editor 
 * and be decorated by any existent decorator that has been created in the project. See `Decorator` for more 
 * information on creating decorators.
 *
 * In order to create a new page in the CMS, you just need to add a new text inside the section called `pages` within the 
 * translations area in the IDE. Within this top section any text created will have automatically a URL 
 * it can be opened as a new html page.
 *
 *
 * *The Bookmarklet Helper Utility*
 *
 * When visiting the Prails Home Tab a bookmarklet link can be found right at the bottom of the page. 
 * After installing it via drag'n'drop into the bookmark bar, visit any event handler that produces output 
 * and click the bookmarklet. All language tags that exist on that page will be shown with a simple red dot 
 * at it's top left corner. When hovering over it, the tag's name will appear which easily let's you find 
 * out what the path in Prails to that very language tag definition within the CMS is. By clicking it, you will
 * be instantly transferred to that content asset in Prails. In case it did not exist, everything you need
 * to create it, will be prefilled.
 **/
class LangData
{
	var $obj_sql;
	var $language_id;
	var $uid;

	var $arr_item_cache;

	function LangData($str_lang)
	{
		if (IS_SETUP)
			$this->uid = if_set($_SESSION['builder']['user_id'], crc32("devel"));
		else
			$this->uid = -1;
		$this->obj_sql = new Database("tbl_prailsbase_");
		$this->setLanguage($str_lang);
		$this->arr_item_cache = Array();
	}
	
        /**
         * setLanguage($lang) -> void
         * - $lang (String) - the language identifier (abbreviation) of the language to set as active.
         * 
         * This method set's the currently active language.
         **/
	function setLanguage($str_lang) {
		if (IS_SETUP) {
			
			$arr_result = @array_pop($this->obj_sql->query("SELECT * FROM ".tbl_prailsbase_language." WHERE (fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0)) AND ".(strlen($str_lang) > 0 ? "abbreviation='".$str_lang."'" : "isDefault=1")));
			if (strlen($str_lang) <= 0 && DEFAULT_LANGUAGE != $arr_result["language_id"]) {
				try {
					  $conf = getConfiguration();
					  $conf["DEFAULT_LANGUAGE"] = (int)$arr_result["language_id"];
					  $conf["DEFAULT_LANGUAGE_ABBR"] = $arr_result["abbreviation"];
					  $toSave = Array();
					  foreach ($conf as $name => $val) {
					  	$toSave[] = Array("name" => $name, "value" => $val);
					  }
					  updateConfiguration($toSave);
				  } catch(Exception $ex) {};				  
			}
			$str_lang = $arr_result["abbreviation"];
			$this->language_id = $arr_result["language_id"];
			setcookie("defaultLangAbbr", $str_lang);
			setcookie("defaultLang", $this->language_id);
		}
	}

	/**
	 * getText($identifier) -> String
	 * - $identifier (String) - the content asset identifier to be retrieved. This usually consists of different parts, separated by a dot.
	 * 
	 * returns the text in the currently active language that corresponds to the content asset identifier given. If no such text was found, 
	 * the content asset identifier itself is returned.
         **/
	function getText($str_item)
	{
		if (!$this->arr_item_cache[$str_item] || !ENV_PRODUCTION)
		{
			$res = $this->obj_sql->query(
	      	"SELECT " .
	      	"  content " .
	      	"FROM ".tbl_prailsbase_texts." " .
	      	"WHERE " .
	      	"	fk_language_id=".(int)$this->language_id." " .
	      	" AND " .
	      	"  identifier='".$str_item."'"
	      	);
	      	if ($res != null) $arr_result = @array_pop($res);
	      	if (!$arr_result) {
			if (!ENV_PRODUCTION) {
				$arr_result["content"] = "{".$str_item."}";
			} else $arr_result["content"] = "";
		}
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
	
	/**
	 * selectTextByIdentifier($identifier) -> DBEntry
	 * - $identifier (String) - the content asset identifier to be retrieved. This usually consists of different parts, separated by a dot.
	 * 
	 * This method will retrieve the content asset object associated to the content asset identifier in the current language. If no such content asset
	 * exists, the `content` attribute of the `DBEntry` will be set to the content asset identifier. Any custom attributes that are attached to the 
	 * content asset are located in an attribute called `custom`. 
	 **/
	function selectTextByIdentifier($str_item) {
		$arr_result = @array_pop($this->obj_sql->query(
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
          	
          	$arr_result["custom"] = $this->_decodeCustom($arr_result["custom"]);
          	return $arr_result;
	}

	/** 
	 * listLanguages() -> Array
	 * 
	 * this method will return a list of all currently existing languages, ordered by language name.
	 **/
	function listLanguages() {
		return $this->obj_sql->query("SELECT * FROM ".tbl_prailsbase_language." WHERE fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0) ORDER BY name");
	}
	 
	/**
	 * listTexts() -> Array
	 * 
	 * get a list of all content assets across all languages
	 **/
	function listTexts() {
		$arr_result = $this->obj_sql->query("SELECT a.* FROM tbl_prailsbase_texts AS a, tbl_prailsbase_language AS b WHERE a.fk_language_id=b.language_id AND (b.fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel").") AND b.fk_user_id=0) GROUP BY a.identifier, a.texts_id, a.fk_language_id, a.content");
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
	
	/**
	 * listAllTextsFromRoot($rootNode) -> Array
	 * - $rootNode (String) - the root node starting from which all texts should be retrieved.
	 * 
	 * retrieves all content assets that reside in the context of the given root node. Usually helpful if you want texts of a certain
	 * folder to be listed for a menu or create an order on those.
	 **/
	function listAllTextsFromRoot($rootNode) {
		$arr_result = $this->obj_sql->query("SELECT a.* FROM tbl_prailsbase_texts AS a, tbl_prailsbase_language AS b WHERE a.fk_language_id=b.language_id AND (b.fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND b.fk_user_id=0)) AND a.identifier LIKE '".$rootNode.".%'");
		foreach($arr_result as &$item) {
			$item["custom"] = $this->_decodeCustom($item["custom"]);
		}
		return $arr_result;
	}
	 
	/**
	 * findTextByContent($keyword) -> Array
	 * - $keyword (String) - the keyword to search for
	 *
	 * Returns all content assets that contain the keyword in some way - regardless of active language.
	 **/
	function findTextByContent($word) {
		$arr_result = $this->obj_sql->query("SELECT a.* FROM tbl_prailsbase_texts AS a, tbl_prailsbase_language AS b WHERE a.fk_language_id=b.language_id AND (b.fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND b.fk_user_id=0)) AND content LIKE '%".$word."%'");
		$arr_return = Array();
		foreach ($arr_result as $res) {
			array_push($arr_return, Array("id" => "text_.".$res['identifier'], "name" => $res["identifier"], "type" => "text", "custom" => $this->_decodeCustom($res["custom"])));
		}
		return $arr_return;
	}

	/**
	 * getAllTextsByIdentifier($identifier) -> Array
	 * - $identifier (String) - the identifier for which all content assets should be returned.
	 *
	 * This method fetches all content assets across all languages it exists in that have the given identifier.
	 **/
	function getAllTextsByIdentifier($ident) {
		$texts = $this->obj_sql->query("SELECT * FROM tbl_prailsbase_language AS b LEFT JOIN tbl_prailsbase_texts AS a ON identifier='".$ident."' AND b.language_id=a.fk_language_id WHERE b.fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND b.fk_user_id=0)");
		foreach ($texts as &$text) {
			$text["default"] = $text["isDefault"];
			$text["custom"] = $this->_decodeCustom($text["custom"]);
		}

		return $texts;
	}
	 
	function getAllTextsById($id) {
		$arr_text = @array_pop($this->obj_sql->query("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));

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
			$entry["custom"] = $this->_encodeCustom($arr_data["custom"]);
			if (strlen($arr_data["old_identifier"]) > 0) {
				$exists = @array_pop($this->obj_sql->query("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id=".(int)$lang." AND identifier='".$arr_data["old_identifier"]."'"));
			} else {
				$exists = @array_pop($this->obj_sql->query("SELECT * FROM tbl_prailsbase_texts WHERE fk_language_id=".(int)$lang." AND identifier='".$entry["identifier"]."'"));
			}
			if ($exists != null) {
				$this->obj_sql->update(tbl_prailsbase_texts, $entry, "texts_id=".(int)$exists["texts_id"]);
			} else {
				$this->obj_sql->add(tbl_prailsbase_texts, $entry);
			}
			if (strlen($text) < 1024) {
          		$this->arr_item_cache[$arr_data["identifier"]] = $text;
          	}			
		}

		return true;
	}
	 
	function updateTextType($id, $type) {
		$exists = @array_pop($this->obj_sql->query("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));
		if ($exists != null) {
			$entry["type"] = $type;
			$this->obj_sql->update(tbl_prailsbase_texts, $entry, "identifier='".$exists["identifier"]."'");
			return true;
		}
		return false;
	}
	 
	function insertText($arr_data) {
		$existing = $this->selectTextByIdentifier($arr_data["identifier"]);
		if (empty($existing["texts_id"])) {
			$arr_data["custom"] = $this->_encodeCustom($arr_data["custom"]);
			$this->obj_sql->add(tbl_prailsbase_texts, $arr_data);
		}
	}
	 
	function deleteTexts($id) {
		$arr_text = @array_pop($this->obj_sql->query("SELECT * FROM tbl_prailsbase_texts WHERE texts_id=".(int)$id));
		$this->deleteTextByIdentifier($arr_text["identifier"]);
	}
	 
	function deleteTextByIdentifier($ident) {
		$this->obj_sql->delete(tbl_prailsbase_texts, "identifier='".$ident."' OR fk_language_id=0");
	}
	 
	function deleteSection($section) {
		$this->obj_sql->delete(tbl_prailsbase_texts, "identifier LIKE '".$section.".%' OR fk_language_id=0");
	}
	 
	function updateLanguage($id, $arr_data) {
		$arr_data["isDefault"] = $arr_data["default"];
//		$arr_languages = $this->listLanguages();
        $this->obj_sql->update(tbl_prailsbase_language, Array("isDefault" => "0"), "fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0)");
/*
        foreach ($arr_languages as $lang) {
			if ($lang["isDefault"] == 0 && $id == $lang["language_id"]) {
				$arr_data["isDefault"] = 1;
			}
		}//*/
		$this->obj_sql->update(tbl_prailsbase_language, $arr_data, "language_id=".(int)$id." AND fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0)");
	}

	function insertLanguage($arr_data) {
		$arr_data["isDefault"] = $arr_data["default"];
		$arr_data['fk_user_id'] = if_set($_SESSION['builder']['user_id'], crc32("devel"));
		if (($count = count($this->listLanguages())) <= 0) {
          $arr_data["isDefault"] = 1;
      }
      if ($arr_data["isDefault"] == 1 && $count > 0) {
          // if the to-be-created language should be the new default, then 
          // set all other default languages to non-default
          $this->obj_sql->update(tbl_prailsbase_language, Array("isDefault" => "0"), "fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0)");
      }
      $id = $this->obj_sql->add(tbl_prailsbase_language, $arr_data);
      return $id;
   }
   
   function deleteLanguage($id) {
      $this->obj_sql->delete(tbl_prailsbase_texts, "fk_language_id=".(int)$id." OR fk_language_id=0");      
   	  $this->obj_sql->delete(tbl_prailsbase_language, "language_id=".(int)$id." AND (fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0))");
   }

   function deleteLanguageOnly($id) {
      $this->obj_sql->delete(tbl_prailsbase_language, "language_id=".(int)$id." AND (fk_user_id=".$this->uid." OR (".$this->uid."=".crc32("devel")." AND fk_user_id=0))");
   }
   
   function _encodeCustom($data) {
   		return base64_encode(json_encode($data));
   }
   
   function _decodeCustom($field) {
   		return json_decode(base64_decode($field), true);
   }
   
}

?>