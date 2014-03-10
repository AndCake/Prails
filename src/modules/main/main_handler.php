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
        
		$obj_gen = OutputGenerator::getInstance();
        $this->obj_print = new MainPrinter($this->str_lang);
    }

    function home()
    {
    	if (!IS_SETUP) {
    		return invoke("main:setup");
    	}
    	
        $results = HookCore::notify("global-home");
    	if (count($results) > 0) {
    		foreach ($results as $res) {
    			if (gettype($res) === "string") {
    				return $res;
    			}
    		}
    	}
    	
		/** BEGIN_CODE **/    	
   $arr_param = Array(
      "modules" => $this->obj_data->listModules(if_set($_SESSION['builder']['user_id'], crc32("devel")))
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
			$arr_settings["SNOW_MODE"] = ($arr_project['snow'] == 'on' ? true : false);
			$arr_settings["USER_SALT"] = $salt = "" . rand(1000, 9999) . "\$" . microtime(true);
			
			$ht = file_get_contents(".htaccess");
			$installDir = dirname($_SERVER["SCRIPT_NAME"]);
			if ($installDir[strlen($installDir) - 1] != "/") $installDir .= "/";
			$ht = str_replace("ErrorDocument 404 /rewrite.php", "ErrorDocument 404 ".$installDir."rewrite.php", $ht);
			if (!$arr_settings["MOD_REWRITE"]) {
				$ht = str_replace("<IfModule rewrite_module>", "<IfModule rewrite_module_deactivated>", $ht);
			}
			$success = $success && @file_put_contents(".htaccess", $ht);

			foreach ($arr_settings as $key => $value) {
				array_push($arr_save, Array("name" => $key, "value" => $value));
			}
			updateConfiguration($arr_save);
			
			// save  database info
			if ($arr_db["type"] != "SQLITE") {
				$conf = file_get_contents("conf/configuration.php");
				$conf = str_replace(Array(
					'define ("DB_TYPE", SQLITE);',
					'"host"=>"localhost",',
   	  				'"name"=>"test",			// database name - change this',
					'"user"=>"root",			// database user - change this',
					'"pass"=>"",				// database password - change this'
				), Array(
					'define ("DB_TYPE", '.$arr_db["type"].');',
					'"host"=>"'.$arr_db['host'].'",',
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
					array_push($users, $value.":".md5($arr_user["pass"][$key].$salt));
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
		} else if (isset($_GET["test"])) {
			$db = $_GET['db'];
			global $arr_dbs;
			$arr_dbs["test"] = $db;
			if ($db['type'] == 'MYSQL') {
				include_once("lib/mysql.php");
				$sql = new MySQL();
			} else if ($db['type'] == 'POSTGRESQL') { 
				include_once("lib/postgresql.php");
				$sql = new PostgreSQL();
			}
			try {
				$res = $sql->connect("test");
				if (!$res) throw new Exception("error connecting to DB server");
			} catch(Exception $e) {
				var_dump($e);
				header("HTTP/1.0 404 Not Found");
				header("Status: 404 Not Found");
				die("-");
			}
			header("HTTP/1.0 200 OK");
			header("Status: 200 OK");
			header("Content-Type: image/gif");
			echo base64_decode("R0lGODlhMwAxAIAAAAAAAP/// yH5BAAAAAAALAAAAAAzADEAAAK8jI+pBr0PowytzotTtbm/DTqQ6C3hGX ElcraA9jIr66ozVpM3nseUvYP1UEHF0FUUHkNJxhLZfEJNvol06tzwrgd LbXsFZYmSMPnHLB+zNJFbq15+SOf50+6rG7lKOjwV1ibGdhHYRVYVJ9Wn k2HWtLdIWMSH9lfyODZoZTb4xdnpxQSEF9oyOWIqp6gaI9pI1Qo7BijbF ZkoaAtEeiiLeKn72xM7vMZofJy8zJys2UxsCT3kO229LH1tXAAAOw==");
			die();
		}
		@mkdir("cache", 0755, true);
		@mkdir("static/images", 0755, true);
		@mkdir("log");
		$arr_param["test"] = touch("cache/test");
		list($main_version, $sub_version) = explode(".", phpversion("sqlite"));
		$arr_param["permissions"] = touch("conf/configuration.php") && touch(".groups") && touch(".users");
		$arr_param["db"]["type"] = (version_compare(PHP_VERSION, "5.3.0") >= 0 && $main_version >= 2 ? "SQLITE" : "MYSQL");
		$arr_param['db']['host'] = "localhost";
		
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
        $arr_param["text"] = OutputGenerator::getInstance()->getLanguage()->selectTextByIdentifier("cms.".str_replace("/", ".", $_GET["page"]));
        
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
