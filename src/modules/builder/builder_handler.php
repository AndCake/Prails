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

class BuilderHandler
{
    var $obj_data;
    var $obj_print;
    var $str_lang;

    function BuilderHandler($str_lang = "en")
    {
        $this->obj_data = new BuilderData();
        $this->str_lang = $str_lang;
        $this->obj_print = new BuilderPrinter($str_lang);
//*		
		if (!$_SESSION["builder"]["user_id"]) {
			if (!isset($_SERVER["PHP_AUTH_USER"])) {
				$this->logout();
			} else {
				$passwd = file(".users");
				$groups = file(".groups");
				foreach ($passwd as &$val) {
					$val = trim($val);
				}
				$u_group = -1;
				foreach ($groups as $group) {
					list($grp, $users) = explode("=", $group);
					$users = explode(",", $users);
					if (in_array($_SERVER["PHP_AUTH_USER"], $users)) {
						$u_group = $grp;
						break;
					}
				}
				if (in_array($_SERVER["PHP_AUTH_USER"].":".$_SERVER["PHP_AUTH_PW"], $passwd)) {
					$_SESSION["builder"]["name"] = $_SERVER["PHP_AUTH_USER"];
					$_SESSION["builder"]["user_id"] = crc32($u_group);
				} else {
					$this->logout();
				}
			}
		}//*/
    }

    function home()
    {
        $arr_param = Array();

        $arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["libraries"] = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["tags"] = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["tables"] = $this->obj_data->listTablesFromUser($_SESSION["builder"]["user_id"]);

        foreach ($arr_param["modules"] as $key=>$arr_module)
        {
            $arr_param["modules"][$key]["handlers"] = $this->obj_data->listHandlers($arr_module["module_id"]);
            $arr_param["modules"][$key]["datas"] = $this->obj_data->listDatas($arr_module["module_id"]);
        }
		
		if (!$_SESSION["builder"]["user_code"]) {
			$_SESSION["builder"]["user_code"] = md5(rand());
			// should later be done at login process
			// then change the "active" file (add the current user)
			// update the active.crc (which contains the crc32 checksum of the "active" file)
			// when a user opens (edits) a module, data, handler, taglib or library, the corresponding files
			// (module_<module_id> & module_<module_id>.crc) should be updated with the user
			// when a user focusses an iframe, it should send the information that that iframe is focussed
			// (and stored in the respective file); when a user moves the cursor, it should send the information
			// that the cursor moved to position x,y (and stored in the respective file) - probably a continuus 
			// check every 500 milli seconds or so (for all iframes in one loop); 
		}

        return $this->_callPrinter("home", $arr_param);
    }

    /*<EVENT-HANDLERS>*/
    function run()
    {
        list ($module, $handler) = explode(":", $_GET["builder"]["event"]);
        $arr_module = $this->obj_data->selectModuleByUserAndName($_SESSION["builder"]["user_id"], $module);
        $arr_libraries = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
        $arr_tags = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
		$arr_configuration = $this->obj_data->listConfigurationFromModule($arr_module["module_id"]);
        if ($arr_module != null && count($arr_module) > 0)
        {
            $mod = strtolower($arr_module["name"]).(ENV_PRODUCTION === true ? "" : $arr_module["module_id"]);
            @mkdir("modules/".$mod, 0755);
            @mkdir("modules/".$mod."/lib", 0755);
			$config = "\n";
			foreach ($arr_configuration as $arr_conf) {
				$config .= "if (!defined('".$arr_conf["name"]."')) ";
				$config .= "define('".$arr_conf["name"]."', '".$arr_conf["value"]."');\n";
			}
			
			$libs = "\n";
            foreach ($arr_libraries as $arr_lib)
            {
                if ($arr_lib["fk_module_id"] == $arr_module["module_id"])
                {
                    $libPath = "modules/".$mod."/lib/";
                } else
                {
                    $libPath = "lib/custom/";
                }
                if (!file_exists($libPath.$arr_lib["name"].$arr_lib["library_id"].".php"))
                {
	                $content = "<"."?php\n".$arr_lib["code"]."\n?".">";
	                file_put_contents($libPath.$arr_lib["name"].$arr_lib["library_id"].".php", $content);
                }

                $libs .= "include_once('".$libPath.$arr_lib["name"].$arr_lib["library_id"].".php');\n";				
//                include_once ($libPath.$arr_lib["name"].$arr_lib["library_id"].".php");
            }
            $tagPath = "lib/tags/custom/";
            foreach ($arr_tags as $arr_tag)
            {
                file_put_contents($tagPath.$arr_tag["name"].$arr_tag["fk_user_id"].".tag", $arr_tag["html_code"]);
            }

            $arr_event = $this->obj_data->selectHandlerByNameAndModule($arr_module["module_id"], $handler);
            if ($arr_event != null)
            {
                $arr_handlers = $this->obj_data->listHandlers($arr_module["module_id"]);
                $arr_data = $this->obj_data->listDatas($arr_module["module_id"]);
                if (file_exists("modules/".$mod) && file_exists("modules/".$mod."/".$mod.".php"))
                {
                    return invoke($mod.":".$arr_event["event"]);
                }
                @mkdir("templates/".$mod, 0755);
                @mkdir("templates/".$mod."/html", 0755);
                @mkdir("templates/".$mod."/js", 0755);
                @mkdir("templates/".$mod."/css", 0755);
                $path = "modules/".$mod."/";
                copy("modules/empty/empty.php", $path.$mod.".php");
                copy("modules/empty/empty_data.php", $path.$mod."_data.php");
                copy("modules/empty/empty_handler.php", $path.$mod."_handler.php");
                copy("modules/empty/empty_printer.php", $path.$mod."_printer.php");
                $c = Array(file_get_contents($path.$mod.".php"), file_get_contents($path.$mod."_data.php"),
                	file_get_contents($path.$mod."_handler.php"), file_get_contents($path.$mod."_printer.php")
                );
                $handler = "";
                $printer = "";
                $data = "";
                $handlers = Array();
                if (is_array($arr_handlers)) foreach ($arr_handlers as $arr_handler)
                {
                    $code = preg_replace("/([^a-zA-Z0-9])out\s*\((.*)\)([^a-zA-Z0-9])/", "\$1\$this->_callPrinter(\"".$arr_handler["event"]."\", \$2)\$3", $arr_handler["code"]);
                    $code = preg_replace("/\\\$data->/", "\$this->obj_data->", $code);
                    $handler .= "\nfunction ".$arr_handler["event"]."() {\n".$code."\n}\n";
                    $printer .= "\nfunction ".$arr_handler["event"]."(\$arr_param, \$decorator) {\n";
                    $printer .= "  \$arr_param[\"session\"] = &\$_SESSION;\n";
                    $printer .= "  \$arr_param[\"odict\"] = &\$_SESSION[\"odict\"];\n";
					if ($arr_handler["flag_ajax"] == "1") {
						$printer .= "  Generator::getInstance()->setIsAjax();\n";
					}
                    $printer .= "  \$decoration = (strlen(\$decorator)>0 ? invoke(\$decorator) : \"<!--[content]-->\");\n";
                    $printer .= "  \$str_content = Generator::getInstance()->includeTemplate(\"templates/".$mod."/html/".$arr_handler["event"].".html\", \$arr_param);\n";
                    $printer .= "  \$str_content = str_replace(\"<!--[content]-->\", \$str_content, \$decoration);\n";
                    $printer .= "  return \$str_content;\n}\n";

                    $handlers[$arr_handler["event"]] = $arr_handler["html_code"];
                }
                if (is_array($arr_data)) foreach ($arr_data as $arr_d)
                {
                    $data .= "\nfunction ".$arr_d["name"]."() {\n".$arr_d["code"]."\n}\n";
                }
                $c = str_replace(
						Array("empty", "Empty", "/*<CONFIGURATION>*/", "/*<LIBRARIES>*/", "/*<EVENT-HANDLERS>*/", "/*<PRINTER-METHODS>*/", "/*<DB-METHODS>*/"),
                		Array(strtolower($mod), ucfirst(strtolower($mod)), "/*<CONFIGURATION>*/".$config, "/*<LIBRARIES>*/".$libs, "/*<EVENT-HANDLERS>*/".$handler, "/*<PRINTER-METHODS>*/".$printer, "/*<DB-METHODS>*/".$data), 
						$c
					);
                file_put_contents("templates/".$mod."/css/".$mod.".css", $arr_module["style_code"]);
                file_put_contents("templates/".$mod."/js/".$mod.".js", $arr_module["js_code"]);
                file_put_contents($path.$mod.".php", $c[0]);
                file_put_contents($path.$mod."_data.php", $c[1]);
                file_put_contents($path.$mod."_handler.php", $c[2]);
                file_put_contents($path.$mod."_printer.php", $c[3]);
                foreach ($handlers as $key=>$value)
                {
                    file_put_contents("templates/".$mod."/html/".$key.".html", $value);
                }
				@chmod("templates/".$mod, 0755);
				@chmod("templates/".$mod."/css/", 0755);
				@chmod("templates/".$mod."/css/".$mod.".css", 0755);
                return invoke($mod.":".$arr_event["event"]);
            }
        }

        return false;
    }

    function resetModule()
    {
        $arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);

		if (strlen($arr_param["module"]["name"]) > 0 && $arr_param["module"]["module_id"] > 0) {
	        removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
	        removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
	        removeDir("templates/".$arr_param["module"]["name"], true);
		}

        die ("success");
    }

    function listModules()
    {
        $arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);

        foreach ($arr_param["modules"] as $key=>$arr_module)
        {
            $arr_param["modules"][$key]["handlers"] = $this->obj_data->listHandlers($arr_module["module_id"]);
            $arr_param["modules"][$key]["datas"] = $this->obj_data->listDatas($arr_module["module_id"]);
        }

        return $this->_callPrinter("listModules", $arr_param);
    }

    function deleteModule()
    {
        $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);

        removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
        removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);

        $this->obj_data->deleteModule($_GET["module_id"]);
		$this->obj_data->deleteHandlerFromModule($_GET["module_id"]);
		$this->obj_data->deleteDataFromModule($_GET["module_id"]);		
        die ("success");
    }

    function editModule()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["module"];
            $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
            $arr_data["style_code"] = stripslashes($arr_data["style_code"]);
            $arr_data["js_code"] = stripslashes($arr_data["js_code"]);
            if ($_SESSION["module_id"] > 0)
            {
                $arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);
                removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
                removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);

                $this->obj_data->updateModule($_SESSION["module_id"], $arr_data);
            } else if ((int)$_SESSION["module_id"] == 0)
            {
                $_SESSION["module_id"] = $this->obj_data->insertModule($arr_data);
				$arr_module = $arr_data;
				// also generate an nearly-empty home entry point
				ob_start();
				require("templates/builder/php/handler_scaffold_home.php");
				$code = ob_get_clean();
				ob_start();
				require("templates/builder/php/handler_scaffold_home_html.php");
				$htmlcode = ob_get_clean();
				$hid = $this->obj_data->insertHandler($arr_handler = Array(
					"fk_module_id" => $_SESSION["module_id"],
					"event" => "home",
					"code" => $code,
					"html_code" => $htmlcode
				));
            	$this->obj_data->insertHandlerHistory($hid, $arr_param["handler"], $arr_handler);
            } else if ($_SESSION["module_id"] < 0) {
            	file_put_contents("templates/main/css/global.css", $arr_data["style_code"]);
				file_put_contents("templates/main/js/global.js", $arr_data["js_code"]);
            }
            $this->obj_data->insertModuleHistory($_SESSION["module_id"], $arr_param["module"], $arr_data);
            setcookie("klatcher[kmdtk][module][".$_SESSION["module_id"]."]", 1);
			echo $_SESSION["module_id"]."\n";
			if ($hid > 0) {
				echo $hid."\n";
			}
			
			if ($_SESSION["module_id"] > 0) {
				$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
				$arr_obj["m_style_code_".$_SESSION["module_id"]] = crc32($arr_data["style_code"]);
				$arr_obj["m_js_code_".$_SESSION["module_id"]] = crc32($arr_data["js_code"]);
				file_put_contents("builder.crc32", json_encode($arr_obj));
			}

            die ("success");
        } else if ($_GET["check"] == "2") {
        	$arr_data = $_POST;
			$arr_data["oldDB"] = stripslashes($arr_data["oldDB"]);
			$arr_data["newUser"] = stripslashes($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectModule($_GET["module_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

		if ($_SESSION["module_id"] < 0) {
			$arr_param["module"] = Array(
				"module_id" => "-1",
				"name" => "Global",
				"js_code" => file_get_contents("templates/main/js/global.js"),
				"style_code" => file_get_contents("templates/main/css/global.css")
			);
		} else {
        	$arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);
		}

		// create crc32 files
		$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		if ($_SESSION["module_id"] > 0 && $arr_obj["m_style_code".$_SESSION["module_id"]] != crc32($arr_param["module"]["style_code"]) || $arr_obj["m_js_code".$_SESSION["module_id"]] != crc32($arr_param["module"]["js_code"])) {
			$arr_obj["m_style_code_".$_SESSION["module_id"]] = crc32($arr_param["module"]["style_code"]);
			$arr_obj["m_js_code_".$_SESSION["module_id"]] = crc32($arr_param["module"]["js_code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));
		}
		
        die ($this->_callPrinter("editModule", $arr_param));
    }
	
	function moduleHistory() 
	{
		$_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
		$arr_param["history"] = $this->obj_data->listModuleHistory($_SESSION["module_id"]);	
		
		die($this->_callPrinter("moduleHistory", $arr_param));
	}

    function listHandlers()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
        $arr_param["handlers"] = $this->obj_data->listHandlers($_SESSION["module_id"]);

        return $this->_callPrinter("listHandlers", $arr_param);
    }

    function deleteHandler()
    {
        $this->obj_data->deleteHandler($_GET["handler_id"]);
        die ("success");
    }

    function editHandler()
    {
        $_SESSION["handler_id"] = if_set($_GET["handler_id"], $_SESSION["handler_id"]);
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["handler"];
            $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
            $arr_data["fk_module_id"] = $_SESSION["module_id"];
			$arr_data["flag_ajax"] = (int)$arr_data["flag_ajax"];
            $arr_data["code"] = stripslashes($arr_data["code"]);
            $arr_data["html_code"] = stripslashes($arr_data["html_code"]);
            if ($_SESSION["handler_id"] > 0)
            {
                $arr_param["handler"] = $this->obj_data->selectHandler($_SESSION["handler_id"]);
                $this->obj_data->updateHandler($_SESSION["handler_id"], $arr_data);
            } else if ((int)$_SESSION["handler_id"] == 0)
            {
                $_SESSION["handler_id"] = $this->obj_data->insertHandler($arr_data);
            } else if ($_SESSION["handler_id"] < 0) {
				$arr_param["handler"]["handler_id"] = -1;
				$arr_param['handler']['event'] = "home";
				// parse main handler for handler code
				$content = file_get_contents("modules/main/main_handler.php");
				$match = substr($content, ($start=strpos($content, "/** BEGIN_CODE **/")) + strlen("/** BEGIN_CODE **/"), strpos($content, "/** END_CODE **/", $start) - $start - strlen("/** BEGIN_CODE **/"));
				$arr_param["handler"]["code"] = $match;
				$content = file_get_contents("templates/main/html/home.html");
				$arr_param["handler"]["html_code"] = $content;

				$content = file_get_contents("modules/main/main_handler.php");
				
                $arr_data["code"] = preg_replace("/([^a-zA-Z0-9])out\s*\((.*)\)([^a-zA-Z0-9])/", "\$1\$this->_callPrinter(\"home\", \$2)\$3", $arr_data["code"]);
                $arr_data["code"] = preg_replace("/\\\$data->/", "\$this->obj_data->", $arr_data["code"]);
				
				file_put_contents("modules/main/main_handler.php", 
					substr($content, 0, strpos($content, "/** BEGIN_CODE **/") + strlen("/** BEGIN_CODE **/")) . 
					"\n" . $arr_data["code"] . "\n" . 
					substr($content, strpos($content, "/** END_CODE **/"))
				);
				file_put_contents("templates/main/html/home.html", $arr_data["html_code"]);
            }
            $this->obj_data->insertHandlerHistory($_SESSION["handler_id"], $arr_param["handler"], $arr_data);
			echo $_SESSION["handler_id"]."\n";

			if ($_SESSION["handler_id"] > 0) {
				$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
				$arr_obj["h_html_code_".$_SESSION["handler_id"]] = crc32($arr_data["html_code"]);
				$arr_obj["h_code_".$_SESSION["handler_id"]] = crc32($arr_data["code"]);
				file_put_contents("builder.crc32", json_encode($arr_obj));
			}

            return $this->resetModule();
//           die("success");
        } else if ($_GET["check"] == "2") {
        	$arr_data = $_POST;
			$arr_data["oldDB"] = stripslashes($arr_data["oldDB"]);
			$arr_data["newUser"] = stripslashes($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectHandler($_GET["handler_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

		if ($_SESSION["handler_id"] < 0 && $_SESSION["module_id"] < 0) {
			$arr_param["module"]["module_id"] = -1;
			$arr_param["module"]["name"] = "Global";
			$arr_param["handler"]["handler_id"] = -1;
			$arr_param['handler']['event'] = "home";
			// parse main handler for handler code
			$content = file_get_contents("modules/main/main_handler.php");
			$match = substr($content, ($start=strpos($content, "/** BEGIN_CODE **/")) + strlen("/** BEGIN_CODE **/"), strpos($content, "/** END_CODE **/", $start) - $start - strlen("/** BEGIN_CODE **/"));
			$arr_param["handler"]["code"] = $match;
			$content = file_get_contents("templates/main/html/home.html");
			$arr_param["handler"]["html_code"] = $content;
		} else {
        	$arr_param["handler"] = $this->obj_data->selectHandler($_SESSION["handler_id"]);
			$arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);
		}
		
		// create crc32 files
		$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		if ($_SESSION["handler_id"] > 0 && $arr_obj["h_html_code".$arr_param["handler"]["handler_id"]] != crc32($arr_param["handler"]["html_code"]) || $arr_obj["h_code".$arr_param["handler"]["handler_id"]] != crc32($arr_param["handler"]["code"])) {
			$arr_obj["h_html_code_".$_SESSION["handler_id"]] = crc32($arr_param["handler"]["html_code"]);
			$arr_obj["h_code_".$_SESSION["handler_id"]] = crc32($arr_param["handler"]["code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));
		}

        die ($this->_callPrinter("editHandler", $arr_param));
    }
	
	function handlerHistory() 
	{
		$_SESSION["handler_id"] = if_set($_GET["handler_id"], $_SESSION["handler_id"]);
		$arr_param["history"] = $this->obj_data->listHandlerHistory($_SESSION["handler_id"]);
		
		die($this->_callPrinter("handlerHistory", $arr_param));
	}

    function listDatas()
    {
        $arr_param["datas"] = $this->obj_data->listDatas($_SESSION["builder"]["user_id"]);

        return $this->_callPrinter("listDatas", $arr_param);
    }

    function deleteData()
    {
        $this->obj_data->deleteData($_GET["data_id"]);
        die ("success");
    }

    function editData()
    {
        $_SESSION["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["data"];
            $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
            $arr_data["fk_module_id"] = $_SESSION["module_id"];
            $arr_data["code"] = stripslashes($arr_data["code"]);
            if ($_SESSION["data_id"] > 0)
            {
                $arr_param["data"] = $this->obj_data->selectData($_SESSION["data_id"]);
                $this->obj_data->updateData($_SESSION["data_id"], $arr_data);
            } else
            {
                $_SESSION["data_id"] = $this->obj_data->insertData($arr_data);
            }
            $this->obj_data->insertDataHistory($_SESSION["data_id"], $arr_param["data"], $arr_data);
			echo $_SESSION["data_id"]."\n";
			
 			$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
			$arr_obj["d_code_".$_SESSION["data_id"]] = crc32($arr_data["code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));

            return $this->resetModule();
//           die("success");
        } else if ($_GET["check"] == "2") {
        	$arr_data = $_POST;
			$arr_data["oldDB"] = stripslashes($arr_data["oldDB"]);
			$arr_data["newUser"] = stripslashes($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectData($_GET["data_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
		}

        $arr_param["data"] = $this->obj_data->selectData($_SESSION["data_id"]);

		// create crc32 files
		$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		if ($_SESSION["data_id"] > 0 && $arr_obj["d_code".$_SESSION["data_id"]] != crc32($arr_param["data"]["code"])) {
			$arr_obj["d_code_".$_SESSION["data_id"]] = crc32($arr_param["data"]["code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));
		}

        die ($this->_callPrinter("editData", $arr_param));
    }
	
	function dataModel() 
	{
		global $arr_database;
		$_SESSION["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
		
		$arr_param["db"] = $arr_database;

		die($this->_callPrinter("dataModel", $arr_param));	
	}
	
	function dataHistory() 
	{
		$_SESSION["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
		$arr_param["history"] = $this->obj_data->listDataHistory($_SESSION["data_id"]);
		
		die($this->_callPrinter("dataHistory", $arr_param));
	}

    function deleteLibrary()
    {
        $this->obj_data->deleteLibrary($_GET["library_id"]);
        die ("success");
    }

    function editLibrary()
    {
        $_SESSION["library_id"] = if_set($_GET["library_id"], $_SESSION["library_id"]);
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_library = $_POST["library"];
            $arr_library["code"] = stripslashes($arr_library["code"]);
            if ($_SESSION["library_id"] > 0)
            {
                $arr_param["library"] = $this->obj_data->selectLibrary($_SESSION["library_id"]);
                $this->obj_data->updateLibrary($_SESSION["library_id"], $arr_library);
            } else
            {
                $arr_library["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_library["fk_module_id"] = $_SESSION["module_id"];
                $_SESSION["library_id"] = $this->obj_data->insertLibrary($arr_library);
            }
            $this->obj_data->insertLibraryHistory($_SESSION["library_id"], $arr_param["library"], $arr_library);
			if (strlen($arr_param["library"]["name"]) > 0) {
				@unlink("lib/custom/".$arr_param["library"]["name"].$_SESSION["library_id"].".php");	
			}
			echo $_SESSION["library_id"]."\n";

			$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
			$arr_obj["l_code_".$_SESSION["library_id"]] = crc32($arr_library["code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));

            return $this->resetModule();
//           die("success");
        } else if ($_GET["check"] == "2") {
        	$arr_data = $_POST;
			$arr_data["oldDB"] = stripslashes($arr_data["oldDB"]);
			$arr_data["newUser"] = stripslashes($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectLibrary($_GET["library_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        $arr_param["library"] = $this->obj_data->selectLibrary($_SESSION["library_id"]);

		// create crc32 files
		$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		if ($_SESSION["library_id"] > 0 && $arr_obj["l_code".$_SESSION["library_id"]] != crc32($arr_param["library"]["code"])) {
			$arr_obj["l_code_".$_SESSION["library_id"]] = crc32($arr_param["library"]["code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));
		}

        die ($this->_callPrinter("editLibrary", $arr_param));
    }

    function deleteTag()
    {
        $this->obj_data->deleteTag($_GET["tag_id"]);
        die ("success");
    }

    function editTag()
    {
        $_SESSION["tag_id"] = if_set($_GET["tag_id"], $_SESSION["tag_id"]);
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_tag = $_POST["tag"];
            //	   	   $arr_tag["html_code"] = addslashes($arr_tag["html_code"]);
            if ($_SESSION["tag_id"] > 0)
            {
                $arr_param["tag"] = $this->obj_data->selectTag($_SESSION["tag_id"]);
                $arr_param["tag"]["html_code"] = stripslashes($arr_param["tag"]["html_code"]);
                $this->obj_data->updateTag($_SESSION["tag_id"], $arr_tag);
            } else
            {
                $arr_tag["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_tag["fk_module_id"] = $_SESSION["module_id"];
                $_SESSION["tag_id"] = $this->obj_data->insertTag($arr_tag);
            }
            $this->obj_data->insertTagHistory($_SESSION["tag_id"], $arr_param["tag"], $arr_tag);
			echo $_SESSION["tag_id"]."\n";
			
            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
			$arr_obj["t_html_code_".$_SESSION["tag_id"]] = crc32($arr_tag["html_code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));

            return $this->resetModule();
//           die("success");
        } else if ($_GET["check"] == "2") {
        	$arr_data = $_POST;
			$arr_data["oldDB"] = stripslashes($arr_data["oldDB"]);
			$arr_data["newUser"] = stripslashes($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectTag($_GET["tag_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        $arr_param["tag"] = $this->obj_data->selectTag($_SESSION["tag_id"]);
		
		// create crc32 files
		$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		if ($_SESSION["tag_id"] > 0 && $arr_obj["t_html_code".$_SESSION["tag_id"]] != crc32($arr_param["tag"]["html_code"])) {
			$arr_obj["t_html_code_".$_SESSION["tag_id"]] = crc32($arr_param["tag"]["html_code"]);
			file_put_contents("builder.crc32", json_encode($arr_obj));
		}

        die ($this->_callPrinter("editTag", $arr_param));
    }

    function listResources()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        $arr_param["resources"] = $this->obj_data->listResources($_SESSION["module_id"]);

        die ($this->_callPrinter("listResources", $arr_param));
    }

    function editResource()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
		$_SESSION["resource_id"] = if_set($_GET["resource_id"], $_SESSION["resource_id"]);
		
		if ($_GET["check"] == "1") 
		{
			$id = $_SESSION["resource_id"];
			if ($id > 0) {
				$arr_param["resource"] = $this->obj_data->selectResource($id);
				$arr_param["module"] = $this->obj_data->selectModule($arr_param["resource"]["fk_module_id"]);
				
		        $mod = strtolower($arr_param["module"]["name"]);
				
				$basePath = "templates/".$mod."/images/";
				$path = $basePath.$arr_param["resource"]["name"];
				@unlink($path);
			}
						
			if ($_GET["do"] == "upload") 
			{
				$file = $_FILES["resource"]["tmp_name"]["file"];
				$content = file_get_contents($file);
				$arr_data["fk_module_id"] = $_SESSION["module_id"];
				$arr_data["type"] = $_FILES["resource"]["type"]["file"];
				$arr_data["data"] = base64_encode($content);
				if ($_SESSION["resource_id"] > 0) 
				{
					$this->obj_data->updateResource($_SESSION["resource_id"], $arr_data);
				} else 
				{
					$arr_data["name"] = $_FILES["resource"]["name"]["file"];
					$_SESSION["resource_id"] = $this->obj_data->insertResource($arr_data);
				}
				echo "<img src='?event=builder:previewResource&resource_id=".$_SESSION["resource_id"]."' width='64' />\n";
				echo "OK\n";
				echo $arr_data["name"];
				die();
			}
			
			$arr_data = $_POST["resource"];
			if ($_SESSION["resource_id"] > 0) 
			{
				$this->obj_data->updateResource($_SESSION["resource_id"], $arr_data);
			} else 
			{
				$arr_data["fk_module_id"] = $_SESSION["module_id"];
				$_SESSION["resource_id"] = $this->obj_data->insertResource($arr_data);
			}
            return $this->resetModule();
		}
		
		$arr_param["resource"] = $this->obj_data->selectResource($_SESSION["resource_id"]);
		$arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);
		
		die ($this->_callPrinter("editResource", $arr_param));		
    }
	
	function previewResource() 
	{
		if ($_GET["resource_id"] > 0) 
		{
			$arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"]);
			header("Content-Type: ".$arr_param["resource"]["type"]);
			die(base64_decode($arr_param["resource"]["data"]));
		} else 
		{
			readfile("templates/builder/images/empty_resource.gif");
			die();	
		}
	}
	
	function createResource() 
	{
		$arr_param["module"] = $this->obj_data->selectModuleByUserAndName($_SESSION["builder"]["user_id"], $_GET["mod"]);
		$arr_param["resource"] = $this->obj_data->selectResourceByName($arr_param["module"]["module_id"], $_GET["resource"]);
		
		if ($arr_param["resource"] != null) {
	        $mod = strtolower($arr_param["module"]["name"]);
			
			$basePath = "templates/".$mod."/images/";
			$path = $basePath.$arr_param["resource"]["name"];
			if (!file_exists($basePath)) {
				mkdir($basePath, 0755, true);
				@chmod($basePath, 0755);
				@chmod("templates/".$mod, 0755);
			}
			file_put_contents($path, base64_decode($arr_param["resource"]["data"]));
			@chmod($path, 0755);
			header("Content-Type: ".$arr_param["resource"]["type"]);
			readfile($path);
		}
		die();
	}
	
	function deleteResource()
	{
		if ($_GET["resource_id"] > 0) 
		{
			$arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"]);
			$arr_param["module"] = $this->obj_data->selectModule($arr_param["resource"]["fk_module_id"]);
			
	        $mod = strtolower($arr_param["module"]["name"]);
			
			$basePath = "templates/".$mod."/images/";
			$path = $basePath.$arr_param["resource"]["name"];
	
			$this->obj_data->deleteResource($_GET["resource_id"]);
			@unlink($path);			
		}
	}
	
	function help() {
		if ($_GET["time"]) {
			$fp = fopen("cache/test/".$_SESSION["builder"]["name"].".log", "a+");
			$line = "[".date("Y-m-d H:i:s", time() - $_GET["time"])."] came from ".$_GET["referer"]." to ".$_GET["page"]." stayed ".$_GET["time"]." seconds\n";
			fwrite($fp, $line);
			fclose($fp);
			die();
		}
		
		$filename = "templates/builder/html/help/".if_set($_GET["path"], "index.html");
		
		require($filename);
		die();		
	}
	
	function logout() {
		$_SESSION["builder"] = Array();
		session_destroy();
		header('WWW-Authenticate: Basic realm="Quixotic Worx Framework Realm"');
		header('HTTP/1.0 401 Unauthorized');
		require("templates/builder/html/not_allowed.html");
		die();		
	}
	
    function deleteTable()
    {
        $arr_table = $this->obj_data->selectTable($_GET["table_id"]);
		if (strlen($arr_table["name"])>0) {
			$this->obj_data->SqlQuery("DROP TABLE IF EXISTS ".$arr_table["name"]);
		}
		$this->obj_data->deleteTable($_GET["table_id"]);
        die ("success");
    }

    function editTable()
    {
        $_SESSION["table_id"] = if_set($_GET["table_id"], $_SESSION["table_id"]);
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_table = $_POST["table"];
            if ($_SESSION["table_id"] > 0)
            {
                $arr_param["table"] = $this->obj_data->selectTable($_SESSION["table_id"]);
                $this->obj_data->updateTable($_SESSION["table_id"], $arr_table);
            } else
            {
                $arr_table["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_table["fk_module_id"] = $_SESSION["module_id"];
                $_SESSION["table_id"] = $this->obj_data->insertTable($arr_table);
				
				$arr_module = $this->obj_data->selectModule($_POST["scaffold"]["fk_module_id"]);
				$arr_module["name"] = strtolower($arr_module["name"]);
				if ($_POST["d_scaffold"]) {
					foreach ($_POST["d_scaffold"] as $data => $one) {
						ob_start();
						@require("templates/builder/php/data_scaffold_".$data.".php");
						$query = ob_get_clean();
						$did = $this->obj_data->insertData($arr_data = Array(
							"name" => $data.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1),
							"code" => $query,
							"fk_module_id" => $_POST["scaffold"]["fk_module_id"] 
						));
						$this->obj_data->insertDataHistory($did, null, $arr_data);
					}
				}
				if ($_POST["h_scaffold"]) {
					foreach ($_POST["h_scaffold"] as $handler => $one) {
						ob_start();
						@require("templates/builder/php/handler_scaffold_".$handler.".php");
						$code = ob_get_clean();
						ob_start();
						@require("templates/builder/php/handler_scaffold_".$handler."_html.php");
						$htmlcode = ob_get_clean();
						$hid = $this->obj_data->insertHandler($arr_data = Array(
							"event" => $handler.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1),
							"code" => $code,
							"html_code" => $htmlcode,
							"fk_module_id" => $_POST["scaffold"]["fk_module_id"]
						));
						$this->obj_data->insertHandlerHistory($hid, null, $arr_data);
					}
				}
            }
            $this->obj_data->insertTableHistory($_SESSION["table_id"], $arr_param["table"], $arr_table);
			
			// re-deploy this table
			$arr_fields = Array();
			$items = explode(":", $arr_table["field_names"]);
			$types = explode(":", $arr_table["field_types"]);
			foreach ($items as $i=>$field) {
				$arr_fields[$field] = $types[$i];
			}
			$arr_db = Array();
			$arr_db[$arr_table["name"]] = $arr_fields;
			DBDeployer::deploy($arr_db);
			echo $_SESSION["table_id"]."\n";
            die("success");
        }

		$arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["table"] = $this->obj_data->selectTable($_SESSION["table_id"]);

        die ($this->_callPrinter("editTable", $arr_param));
    }
	
	function deleteConfiguration() 
	{
        $_SESSION["configuration_id"] = if_set($_GET["configuration_id"], $_SESSION["configuration_id"]);
		$this->obj_data->deleteConfiguration($_GET["configuration_id"]);
        die ("success");
	}
	
    function editConfiguration()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_configuration = $_POST["configuration"];
			
			if ($_SESSION["module_id"] < 0) {
				// store the changed data in our configuration.php	
			} else {
				$this->obj_data->clearConfiguration($_SESSION["module_id"]);
				foreach ($arr_configuration as $arr_conf) {
					$arr_conf["fk_module_id"] = $_SESSION["module_id"];
					$this->obj_data->insertConfiguration($arr_conf);
				}
			}
						
            die("success");
        }
		
		if ($_SESSION["module_id"] < 0) {
			$arr_param["configuration"] = Array();
			$conf = get_defined_constants(true);
			foreach ($conf["user"] as $name => $value) {
				array_push($arr_param["configuration"], Array(
					"fk_module_id" => -1,
					"flag_public" => 1,
					"name" => $name,
					"value" => $value
				));
			}
			$arr_param["module"] = Array(
				"module_id" => "-1",
				"name" => "Global"
			);
		} else {
	        $arr_param["configuration"] = $this->obj_data->listConfigurationFromModule($_SESSION["module_id"]);
			$arr_param["module"] = $this->obj_data->selectModule($_SESSION["module_id"]);
		}

        die ($this->_callPrinter("editConfiguration", $arr_param));
    }
	
	function shout() 
	{
		$me = $_SESSION["builder"]["name"];
		$msg = stripslashes($_POST["msg"]);
		
		$entry = "<p><span class='sbuser'>".$me."</span> <span class='sbdate'>(".date("H:i:s").")</span><br/><span class='sbtext'>".str_replace("\n", "<br/>", str_replace("\r\n", "<br/>", $msg))."</span></p>";
		$chat = @file_get_contents("shout.box");
		if (!$chat) $chat = Array(); else $chat = explode("\n", $chat);
		array_unshift($chat, $entry);
		if (count($chat) > 100) array_pop($chat);
		file_put_contents("shout.box", $content=implode("\n", $chat));
		@chmod("shout.box", 0755);
		@touch("shout.box");
		die($content); 
	}
	
	function queryTest()
	{
		$arr_param = Array();
		
		if ($_GET["check"] == "1") {
			$query = stripslashes($_POST["query"]);
			$arr_param["result"] = $this->obj_data->SqlQuery($query);
		}
		
		die($this->_callPrinter("queryTest", $arr_param));
	}	
	
	function searchItem()
	{
		$arr_param = Array();
		
		$query = $_POST["query"];
		$arr_param["handlerResult"] = $this->obj_data->findHandlerByName($query, $_SESSION["builder"]["user_id"]);
		$arr_param["dataResult"] = $this->obj_data->findDataByName($query, $_SESSION["builder"]["user_id"]);
		$arr_param["libraryResult"] = $this->obj_data->findLibByName($query, $_SESSION["builder"]["user_id"]);
		$arr_param["moduleResult"] = $this->obj_data->findModuleByName($query, $_SESSION["builder"]["user_id"]);
		$arr_param["tagResult"] = $this->obj_data->findTagByName($query, $_SESSION["builder"]["user_id"]);
		$arr_param["tableResult"] = $this->obj_data->findTableByName($query, $_SESSION["builder"]["user_id"]);
		
		$arr_param["result"] = Array();
		if (is_array($arr_param["handlerResult"])) $arr_param["result"] = $arr_param["handlerResult"];
		if (is_array($arr_param["dataResult"])) $arr_param["result"] = array_merge($arr_param["result"], $arr_param["dataResult"]);
		if (is_array($arr_param["libraryResult"])) $arr_param["result"] = array_merge($arr_param["result"], $arr_param["libraryResult"]);
		if (is_array($arr_param["moduleResult"])) $arr_param["result"] = array_merge($arr_param["result"], $arr_param["moduleResult"]);
		if (is_array($arr_param["tagResult"])) $arr_param["result"] = array_merge($arr_param["result"], $arr_param["tagResult"]);
		if (is_array($arr_param["tableResult"])) $arr_param["result"] = array_merge($arr_param["result"], $arr_param["tableResult"]);
		header("Content-Type: application/json");
		die(json_encode($arr_param["result"]));
	}
	
	// merges the new changes with the unsaved changes of the user, so that the unsaved ones survive too
	function _mergeContent($oldDB, $newDB, $newUser) {
		$userChanges = diff($oldDB, $newUser);
		$dbChanges = diff($oldDB, $newDB);
		
		$lines = Array();
		foreach ($userChanges as $line=>$change) {
			if (is_array($change)) {
				array_push($lines, $line);
			}
		}
		foreach ($lines as $line) {
			$dbChanges[$line] = $userChanges[$line];
		}
		
		$result = Array();
		foreach ($dbChanges as $line=>$content) {
			if (is_array($content)) {
				if (isset($content["i"])) {
					if (is_array($content["i"])) foreach ($content["i"] as $c) {
						array_push($result, $c);
					} else {
						array_push($result, $content["i"]);
					}
				}
			} else {
				array_push($result, $content);
			}
		}
		return implode("\n", $result);
	}

    /*</EVENT-HANDLERS>*/

    /**
     * @desc calls the corresponding method in printer
     * @param $str_func [STRING]   function to call
     * @param $arr_param [ARRAY]   some data that may be needed
     * @returns [BOOLEAN]    TRUE if call successful, else FALSE
     */
    function _callPrinter($str_func, $arr_param)
    {
        if (method_exists($this->obj_print, $str_func))
        {
            return $this->obj_print->$str_func($arr_param);
        } else
        {
            pushError("Could not call ".$str_func." in BuilderPrinter.");
            return false;
        }
    }
}

?>
