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

class MainHandler
{
    var $obj_data;
    var $obj_print;
    var $str_lang;
    var $arr_param;

    function MainHandler($str_lang = "")
    {
    	if (IS_SETUP) {
	        $this->obj_data = new MainData();
    	}
        $this->str_lang = $str_lang;
		
		// clean up any transferred vars in GET request
		$mod = explode(":", $_GET["event"]);
		$result = Array();
		foreach ($_GET as $key => $value) {
			$result[$key] = $value;
			if (strpos($key, "_id") !== false) {
				if (strpos($mod[1], str_replace("_id", "", $key)) !== false) {
					$result[strtolower($key[0]).substr($key, 1)] = $value;
				}
			}
		}
		$_GET = $result;
        
		$obj_gen = Generator::getInstance();
        $this->obj_print = new MainPrinter($this->str_lang);
    }

    function home()
    {
    	if (!IS_SETUP) {
    		return invoke("main:setup");
    	}
    	
		/** BEGIN_CODE **/
   $arr_param = Array(
      "modules" => $this->obj_data->listModules()
   );
   return $this->_callPrinter("home", $arr_param);

/** END_CODE **/
    }
    
    function setup() {
		$arr_param = Array();
		
		if (isset($_GET["check"])) {
			$arr_project = $_POST["project"];
			$arr_db = $_POST["db"];
			$arr_user = $_POST["user"];
			$success = true;
			// save project settings
			global $arr_settings;
			$arr_save = Array();
			$arr_settings["PROJECT_NAME"] = $arr_project["name"];
			$arr_settings["ENV_PRODUCTION"] = ($arr_project["env"] == "prod");
			$arr_settings["MOD_REWRITE"] = ($_POST["rewrite"] != "false");
			$arr_settings["IS_SETUP"] = true;
			$arr_settings["FIRST_RUN"] = true;
			
			if (!$arr_settings["MOD_REWRITE"]) {
				$ht = file_get_contents(".htaccess");
				$start = strpos($ht, "<IfModule rewrite_module>");
				$end = strpos($ht, "</IfModule>", $start);
				$pre = substr($ht, 0, $start);
				$post = substr($ht, $end + strlen("</IfModule>"));
				$success = $success && @file_put_contents(".htaccess", $pre.$post);
			}

			foreach ($arr_settings as $key => $value) {
				array_push($arr_save, Array("name" => $key, "value" => $value));
			}
			updateConfiguration($arr_save);
			
			// save  database info
			if ($arr_db["type"] != "SQLITE") {
				$conf = file_get_contents("conf/configuration.php");
				$conf = str_replace(Array(
					'define ("DB_TYPE", SQLITE);',
   	  				'"name"=>"test",			// database name - change this',
					'"user"=>"root",			// database user - change this',
					'"pass"=>"",			// database password - change this'
				), Array(
					'define ("DB_TYPE", MYSQL);',
					'"name"=>"'.$arr_db["name"].'",			// database name - change this',
					'"user"=>"'.$arr_db["user"].'",			// database user - change this',
					'"pass"=>"'.$arr_db["pass"].'",			// database password - change this'
				), $conf);
				$success = $success && @file_put_contents("conf/configuration.php", $conf);
			}
			
			$groups = Array();
			$users = Array();
			foreach ($arr_user["name"] as $key=>$value) {
				if (strlen($value) > 0 && strlen($arr_user["pass"][$key]) > 0) {
					array_push($users, $value.":".md5($arr_user["pass"][$key]));
					if (!is_array($groups[$arr_user["group"][$key]])) {
						$groups[$arr_user["group"][$key]] = Array();
					}
					array_push($groups[$arr_user["group"][$key]], $value);
				}
			}
			$success = $success && file_put_contents(".users", implode("\n", $users));
			$str_grp = "";
			foreach ($groups as $key=>$grp) {
				$str_grp .= $key."=".implode(",", $grp)."\n";
			}
			$success = $success && @file_put_contents(".groups", $str_grp);
			$arr_param = $_POST; 			
			$arr_param["message"] = $success ? "success" : "error";
		}
		
		$arr_param["test"] = touch("cache/test");
		list($main_version, $sub_version) = explode(".", phpversion("sqlite"));
		$arr_param["permissions"] = touch("conf/configuration.php") && touch(".groups") && touch(".users");
		$arr_param["db"]["type"] = (version_compare(PHP_VERSION, "5.3.0") >= 0 && $main_version >= 2 ? "SQLITE" : "MYSQL");
		
		return $this->_callPrinter("setup", $arr_param);
    }
    
    function pageNotFound() {
    	$results = HookCore::notify("404");
    	if (count($results) > 0) {
    		foreach ($results as $res) {
    			if (gettype($res) === "string") {
    				return $res;
    			}
    		}
    	}
    	
    	return $this->_callPrinter("pageNotFound", $arr_param);
    }
    
    
    function cmsHandler() {
        $arr_param["text"] = Generator::getInstance()->getLanguage()->selectTextByIdentifier("cms.".str_replace("/", ".", $_GET["page"]));
        
        if (!$arr_param["text"]["texts_id"]) {
        	
            return $this->pageNotFound();
        }
        
        return $this->_callPrinter("cmsHandler", $arr_param);
    }

   	/**
   	 * @desc calls the corresponding method in printer
   	 * @param $str_func [STRING]   function to call
   	 * @param $arr_param [ARRAY]   some data that may be needed
   	 * @returns [BOOLEAN]    TRUE if call successful, else FALSE
   	 */
   	function _callPrinter ($str_func, $arr_param)
   	{
   	    if (method_exists($this->obj_print, $str_func))
   	    {
   	        return $this->obj_print->$str_func($arr_param);
   	    } else
   	    {
   	        $error = "Could not call ".$str_func." in MainPrinter.";
   	        pushError($error);

   	        return false;
   	    }
   	}
}

?>
