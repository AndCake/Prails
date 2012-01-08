<?php
/**
 Prails Web Framework
 Copyright (C) 2011  Robert Kunze
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

    function BuilderHandler($str_lang = "")
    {
        $this->obj_data = new BuilderData();
        $this->str_lang = $str_lang;
        $this->obj_print = new BuilderPrinter($str_lang);
        
        $this->cleanUpCrc();

        //
       	$_SESSION["builder"] = Array();
        if (!$_SESSION["builder"]["user_id"])
        {
        	if (ENV_PRODUCTION === true && (strpos($_GET["event"], "builder:") === false || $_GET["event"] == "builder:createResource")) {
        		$u_group = "cms";
                $_SESSION["builder"]["name"] = "builder";
        		$_SESSION["builder"]["user_id"] = crc32("devel");
	            $_SESSION["builder"]["group"] = $u_group;
        	} else {
	            if (! isset ($_SERVER["PHP_AUTH_USER"]))
	            {
	                $this->logout();
	            } else
	            {
	                $passwd = file(".users");
	                $groups = file(".groups");
	                foreach ($passwd as & $val)
	                {
	                    $val = trim($val);
	                }
	                $u_group = -1;
	                foreach ($groups as $group)
	                {
	                    list ($grp, $users) = explode("=", $group);
	                    $users = explode(",", trim($users));
	                    if (in_array($_SERVER["PHP_AUTH_USER"], $users))
	                    {
	                        $u_group = $grp;
	                        break;
	                    }
	                }
	                if (in_array($_SERVER["PHP_AUTH_USER"].":".md5($_SERVER["PHP_AUTH_PW"]), $passwd))
	                {
	                    $_SESSION["builder"]["name"] = $_SERVER["PHP_AUTH_USER"];
	                    $_SESSION["builder"]["user_id"] = crc32("devel");
	                    $_SESSION["builder"]["group"] = $u_group;
	                } else
	                {
	                    $this->logout();
	                }
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
        $arr_param["texts"] = Generator::getInstance()->getLanguage()->listTexts();
        $arr_param["backup"] = Quartz::getJob("backupjob");
        $arr_param["backupList"] = Array();
       	if (!is_dir("static/backups")) {
       		@mkdir("static/backups", 0755);
       	}
        $dp = opendir("static/backups");
        while (($file = readdir($dp)) !== false) {
        	if ($file[0] != ".") {
        		array_push($arr_param["backupList"], $file);
        	}
        }
        closedir($dp);
        $groups = file(".groups");
        $users = file(".users");
        $userGroups = Array();
        foreach ($groups as $group) {
        	list($grp, $userList) = explode("=", $group);
        	$arr_param['groups'][$grp] = $grp;
        	$userList = explode(",", trim($userList));
        	foreach ($userList as $usr) {
        		$userGroups[$usr] = $grp;
        	}
        } 
        foreach ($users as $user) {
        	list($usr, $pwd) = explode(":", $user);
        	$arr_param['users'][] = Array("name" => $usr, "group" => $userGroups[$usr]);
        }
        
        $prailsServerBasePath = "http://prails.googlecode.com/svn/trunk/";
        // check for new {Prails} version
        $url = parse_url(PRAILS_HOME_PATH."version");
        // check if we are online...
        $fp = fsockopen($url["host"], if_set($url["port"], 80), $en, $es, 2);
        // (if connection speed is so slow that we need more than 2 seconds to check
        // then we should not even show the user to update, as this might then take
        // _very_ long)
        if ($fp) {
            // if we are online, fetch the current version file
            fclose($fp);
            $arr_param["local"]["version"] = file_get_contents(PRAILS_HOME_PATH."version");
            if (trim($arr_param["local"]["version"]) != FRAMEWORK_VERSION) { 
                $arr_param["local"]["changeset"] = file_get_contents(PRAILS_HOME_PATH."changeset");
            }
        }
        
        foreach ($arr_param["modules"] as $key=>$arr_module)
        {
            $arr_param["modules"][$key]["handlers"] = $this->obj_data->listHandlers($arr_module["module_id"]);
            $arr_param["modules"][$key]["datas"] = $this->obj_data->listDatas($arr_module["module_id"]);
        }

        if (!$_SESSION["builder"]["user_code"]) {
            $_SESSION["builder"]["user_code"] = md5(rand());
        }

        return $this->_callPrinter("home", $arr_param);
    }

    /*<EVENT-HANDLERS>*/
    function run($arr_param=null, $bol_invoke = true)
    {
		if (!function_exists("makeDebuggable")) {
			function makeDebuggable($code, $addBreakpoint = false) {
				if (ENV_PRODUCTION === true) return $code;
	      		if ($addBreakpoint) {
					$debugStart = "Debugger::breakpoint();";
	      		} else {
	             	$debugStart = "Debugger::wait();";
	            }
	
	            $lines = explode("\n", $code);
	            foreach ($lines as $i=>$line) {
	            	if (preg_match("/(\{$)|(;$)/i", $line) && (preg_match("/(^|\s+)switch\s*\(/i", $line) ||
	            			(preg_match("/^\s*\{/i", $line) && preg_match("/(^|\s+)switch\s*\(/i", $lines[$i-1])))) {
							$lines[$i] = $line . "Debugger::wait(get_defined_vars());";
	            	}
	            }
	            return $debugStart.implode("\n", $lines);			
			}
		}
		
    	if ($bol_invoke !== true) {
    		$arr_module = $this->obj_data->selectModule($bol_invoke["module_id"]);
    		$module = $arr_module["name"];
            $arr_handlers = $this->obj_data->listHandlers($arr_module["module_id"]);
            $handler = $arr_handlers[0]["event"];
    	} else {
	        list ($module, $handler) = explode(":", $_GET["builder"]["event"]);
	        $arr_module = $this->obj_data->selectModuleByUserAndName($_SESSION["builder"]["user_id"], $module);
    	}
        $arr_libraries = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
        $arr_tags = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
        $arr_configuration = $this->obj_data->listConfigurationFromModule($arr_module["module_id"], (ENV_PRODUCTION === true ? "2" : "1"));
        if ($arr_module != null && count($arr_module) > 0)	
        {
            $mod = strtolower($arr_module["name"]).(ENV_PRODUCTION === true?"":$arr_module["module_id"]);
            @mkdir("modules/".$mod, 0755);
            @mkdir("modules/".$mod."/lib", 0755);
            $config = "\n\$arr_".$mod."_settings = Array(\n/*<CUSTOM-SETTINGS>*/\n";
            $config .= "\t\"".strtoupper($arr_module['name'])."\" => \"".$mod."\",\n";
            foreach ($arr_configuration as $arr_conf)
            {
            	if ($arr_conf["value"] == "true" || $arr_conf["value"] == "false" || is_numeric($arr_conf["value"])) {
                	$config .= "\t".'"'.$arr_conf['name'].'" => '.str_replace('"', '\"', $arr_conf['value']).",\n";
            	} else {
                	$config .= "\t".'"'.$arr_conf['name'].'" => "'.str_replace('"', '\"', $arr_conf['value'])."\",\n";
            	}
            }
            $config .= "/*</CUSTOM-SETTINGS>*/\n);\nforeach (\$arr_".$mod."_settings as \$key=>\$value) {\n\tif(!defined(\$key)) define(\$key, \$value);\n}\n";

            $libs = "\n";
            foreach ($arr_libraries as $arr_lib) {
            	if ((int)$arr_lib["fk_module_id"] == 0 || $arr_lib["fk_module_id"] == $arr_module["module_id"]) {
	                if ($arr_lib["fk_module_id"] == $arr_module["module_id"]) {
	                    $libPath = "modules/".$mod."/lib/";
	                } else {
	                    $libPath = "lib/custom/";
	                }
	                if ($arr_lib["fk_resource_id"] > 0) {
	                	$libPath .= $arr_lib["name"] . (ENV_PRODUCTION === true ? "" : $arr_lib["library_id"]) . "/";
	                	if (!file_exists($libPath)) {
	                		mkdir($libPath, 0755);
	                	}
	                	$libfile = $libPath.$arr_lib["resource"]["name"];
	                	file_put_contents($libfile, base64_decode($arr_lib["resource"]["data"]));
			        	preg_match('/((\.tar\.[a-zA-Z0-9]+)|(\.tgz)|(\.zip))$/mi', $libfile, $match);
			        	if (strlen($match[1]) > 0) {
			        		// unpack it to get contents
			        		$progs = Array(".tar.bz2" => "tar -xvjf ", ".tar.gz" => "tar -xvzf ", ".tgz" => "tar -xvzf ", ".zip" => "unzip ");
			        		exec("cd ".dirname($libfile)."; ".$progs[$match[1]].basename($libfile));
			        	}
	                }
	                $libname = $libPath . $arr_lib["name"] . (ENV_PRODUCTION === true ? "" : $arr_lib["library_id"]) . ".php";
	                if (file_exists($libname)) {
	                	$libName = $libPath . $arr_lib["name"].(ENV_PRODUCTION === true ? "" : $arr_lib["library_id"])."_loader.php";
	                }
	                if (!file_exists($libname)) {
	                    $content = "<"."?php\n".$arr_lib["code"]."\n?".">";
	                    file_put_contents($libname, $content);
	                }
                	$libs .= "include_once('".$libname."');\n";
            	}
            }
            $tagPath = "lib/tags/custom/";
            foreach ($arr_tags as $arr_tag) {
                file_put_contents($tagPath.$arr_tag["name"].(ENV_PRODUCTION===true ? "" : $arr_tag["fk_user_id"]).".tag", $arr_tag["html_code"]);
            }

            $arr_event = $this->obj_data->selectHandlerByNameAndModule($arr_module["module_id"], $handler);
            if ($arr_event != null)
            {
                $arr_handlers = $this->obj_data->listHandlers($arr_module["module_id"]);
                $arr_data = $this->obj_data->listDatas($arr_module["module_id"]);
                if (file_exists("modules/".$mod) && file_exists("modules/".$mod."/".$mod.".php"))
                {
                    return invoke($mod.":".$arr_event["event"], $arr_param);
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
                $c = Array(
                	file_get_contents($path.$mod.".php"), file_get_contents($path.$mod."_data.php"),
                	file_get_contents($path.$mod."_handler.php"), file_get_contents($path.$mod."_printer.php")
                );
                $jsinc = "";
                $cssinc = "";

                $header = @unserialize($arr_module["header_info"]);
                if (is_array($header))
                {
                    if (is_array($header["js_includes"])) foreach ($header["js_includes"] as $entry)
                    {
                        $jsinc .= "	  \$obj_gen->addJavaScript(\"".$entry."\", ".(ENV_PRODUCTION !== true ? "false" : "true").");\n";
                    }
                    if (is_array($header["css_includes"])) foreach ($header["css_includes"] as $entry)
                    {
                        $cssinc .= "	  \$obj_gen->addStyleSheet(\"".$entry."\", ".(ENV_PRODUCTION !== true ? "false" : "true").");\n";
                    }
                }

                $handler = "";
                $printer = "";
                $data = "";
                $handlers = Array();
                if (is_array($arr_handlers)) foreach ($arr_handlers as $arr_handler)
                {
                    $code = preg_replace("/([^a-zA-Z0-9])out\s*\((.*)\)([^a-zA-Z0-9])/", "\$1\$this->_callPrinter(\"".$arr_handler["event"]."\", \$2)\$3", $arr_handler["code"]);
                    $code = preg_replace("/\\\$data->/", "\$this->obj_data->", $code);
                    $code = makeDebuggable($code, $bol_invoke["handler"] == $arr_handler["handler_id"]);
                    $handler .= "\nfunction ".$arr_handler["event"]."() {\n  global \$SERVER, \$SECURE_SERVER, \$currentLang;\n	\$arr_param = func_get_arg(0);\n".$code."\n}\n";
                    $printer .= "\nfunction ".$arr_handler["event"]."(\$arr_param, \$decorator, \$template) {\n";
		    		$printer .= "  global \$SERVER, \$SECURE_SERVER;\n";
                    $printer .= "  \$arr_param[\"session\"] = &\$_SESSION;\n";
                    $printer .= "  \$arr_param[\"odict\"] = &\$_SESSION[\"odict\"];\n";
		    		$printer .= "  \$arr_param[\"server\"] = Array(\"url\" => substr(\$SERVER, 0, -1), \"secureUrl\" => substr(\$SECURE_SERVER, 0, -1), \"host\" => \$_SERVER[\"HTTP_HOST\"], \"port\" => \$_SERVER[\"SERVER_PORT\"], \"referer\" => \$_SERVER[\"HTTP_REFERER\"]);\n";
		    		$printer .= "  \$arr_param[\"request\"] = Array(\"get\" => \$_GET, \"post\" => \$_POST);\n";
		    		$printer .= "  \$arr_param[\"cookie\"] = &\$_COOKIE;\n";
		    		$printer .= "  \$arr_param[\"local\"] = array_merge(is_array(\$arr_param[\"local\"]) ? \$arr_param[\"local\"] : Array(), \$arr_param);\n";
                    if ($arr_handler["flag_ajax"] == "1")
                    {
                        $printer .= "  Generator::getInstance()->setIsAjax();\n";
                    }
		    		if ($arr_handler["flag_cacheable"] == "1") {
						$printer .= "  Generator::getInstance()->setIsCachable();\n";
		    		}
                    $printer .= "  \$decoration = (strlen(\$decorator)>0 ? invoke(\$decorator, \$arr_param) : \"<!--[content]-->\");\n";
                    $printer .= "  \$str_content = Generator::getInstance()->includeTemplate(\"templates/".$mod."/html/".$arr_handler["event"]."\".(strlen(\$template) > 0 && strtolower(\$template) != \"default\" ? \".\".\$template : \"\").\".html\", \$arr_param);\n";
                    $printer .= "  \$str_content = str_replace(\"<!--[content]-->\", \$str_content, \$decoration);\n";
                    $printer .= "  return \$str_content;\n}\n";

                    $handlers[$arr_handler["event"]] = $arr_handler["html_code"];
                }
                if (is_array($arr_data)) foreach ($arr_data as $arr_d) {
                	$data .= "\nfunction ".$arr_d["name"]."() {\n".makeDebuggable($arr_d["code"], $bol_invoke["data"] == $arr_d["data_id"])."\n}\n";
                }
                $c = str_replace(
                	Array("empty", "Empty", "/*<CONFIGURATION>*/", "/*<LIBRARIES>*/", "/*<EVENT-HANDLERS>*/", "/*<PRINTER-METHODS>*/", "/*<DB-METHODS>*/", "/*<JAVASCRIPT-INCLUDES>*/", "/*<CSS-INCLUDES>*/"),
                	Array(strtolower($mod), ucfirst(strtolower($mod)), "/*<CONFIGURATION>*/".$config, "/*<LIBRARIES>*/".$libs, "/*<EVENT-HANDLERS>*/".$handler, "/*<PRINTER-METHODS>*/".$printer, "/*<DB-METHODS>*/".$data, "/*<JAVASCRIPT-INCLUDES>*/".$jsinc, "/*<CSS-INCLUDES>*/".$cssinc),
                	$c
                );
                file_put_contents("templates/".$mod."/css/".$mod.".css", $arr_module["style_code"]);
                file_put_contents("templates/".$mod."/js/".$mod.".js", $arr_module["js_code"]);
                file_put_contents($path.$mod.".php", $c[0]);
                file_put_contents($path.$mod."_data.php", $c[1]);
                file_put_contents($path.$mod."_handler.php", $c[2]);
                file_put_contents($path.$mod."_printer.php", $c[3]);
                foreach ($handlers as $key=>$code) { 
			        preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
			        $lastPos = 0;
			        $codes = Array();
			        array_push($codes, Array("title" => "Default", "id" => md5("h".$arr_param["handler"]["handler_id"])));
			        if (is_array($matches) && is_array($matches[1])) {         
				        foreach ($matches[1] as $match) {
				        	$cd = Array(
				        		"title" => $match[0],
				        		"id" => md5("html_".$match[0].$arr_param["handler"]["handler_id"]) 
				        	);
				        	$start = $match[1] + strlen("".$match[0].">\n");
				        	$end = strpos($code, "</part-".$match[0].">\n", $match[1]);
				        	$val = substr($code, $start, $end - $start);
				        	$lastPos = $end + strlen("</part-".$match[0].">\n");
				        	file_put_contents("templates/".$mod."/html/".$key.".".$match[0].".html", $val);
				        }
			        }
			        $value = substr($code, $lastPos);
                	
                	file_put_contents("templates/".$mod."/html/".$key.".html", $value);
                }
                @chmod("templates/".$mod, 0755);
                @chmod("templates/".$mod."/css/", 0755);
                @chmod("templates/".$mod."/css/".$mod.".css", 0755);
                if ($bol_invoke === true) {
                	return invoke($mod.":".$arr_event["event"], $arr_param);
                } else {
                	return true;
                }
            }
        }

        return invoke("main:pageNotFound", $arr_param);
    }

    function resetModule($die = true, $module_id = "") {
    	$module_id = if_set($module_id, $_SESSION["module_id"]);
    	if (strlen($module_id) <= 0 || $module_id <= 0) {
    		if ($die) die("success"); else return "success";
    	} 
        $arr_param["module"] = $this->obj_data->selectModule($module_id);

        if (strlen($arr_param["module"]["name"]) > 0 && $arr_param["module"]["module_id"] > 0) {
        	if (ENV_PRODUCTION) {
                removeDir("modules/".strtolower($arr_param["module"]["name"]), true);
            } else {
                removeDir("modules/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
                removeDir("templates/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
            }
            removeDir("templates/".strtolower($arr_param["module"]["name"]), true);
			exec("rm cache/handler_".$arr_param['module']['name'].":* cache/handler_".strtolower($arr_param['module']['name']).":* cache/handler_".strtolower($arr_param["module"]["name"]).$module_id.":* cache/handler_".$arr_param["module"]["name"].$module_id.":*");
        }

        if ($die) {
            die ("success");
        } else {
            return "success";
        }
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

        removeDir("modules/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
        removeDir("modules/".strtolower($arr_param["module"]["name"]), true);
        removeDir("templates/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
        removeDir("templates/".strtolower($arr_param["module"]["name"]), true);
        
        $this->obj_data->deleteHandlerFromModule($_GET["module_id"]);
		$this->obj_data->deleteDataFromModule($_GET["module_id"]);
        $this->obj_data->clearConfiguration($_GET["module_id"]);
		$this->obj_data->clearResource($_GET["module_id"]);
		$this->obj_data->clearTestcase($_GET["module_id"]);
		$this->obj_data->deleteModule($_GET["module_id"]);
        $this->resetModule(true, $_GET["module_id"]);
    }

    function editModule()
    {
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
			$this->updateCRCFile(Array("code".$_GET["module_id"], "js_code".$_GET["module_id"]));
			
        	$arr_data = $_POST["module"];
            if (!$arr_data["header_info"])
            {
                $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_data["style_code"] = $arr_data["style_code"];
                $arr_data["js_code"] = $arr_data["js_code"];
            }
            if ($_GET["module_id"] > 0)
            {
                $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
                if ($arr_data["header_info"])
                {
                	$headerInfo = @unserialize($arr_param["module"]["header_info"]);
                	if (!is_array($headerInfo)) $headerInfo = Array();
                    $arr_data["header_info"] = @serialize(array_merge($headerInfo, $arr_data["header_info"]));
                }

            	$this->resetModule(false, $_GET["module_id"]);
                $this->obj_data->updateModule($_GET["module_id"], $arr_data);
            } else if ((int)$_GET["module_id"] == 0)
            {
                $_SESSION["module_id"] = $_GET["module_id"] = $this->obj_data->insertModule($arr_data);
                $arr_module = $arr_data;
                // also generate an nearly-empty home entry point
                ob_start();
                require ("templates/builder/php/handler_scaffold_home.php");
                $code = ob_get_clean();
                ob_start();
                require ("templates/builder/php/handler_scaffold_home_html.php");
                $htmlcode = ob_get_clean();
                $hid = $this->obj_data->insertHandler($arr_handler = Array(
	                "fk_module_id"=>$_GET["module_id"],
	                "event"=>"home",
	                "code"=>$code,
	                "html_code"=>$htmlcode
                ));
                $this->obj_data->insertHandlerHistory($hid, $arr_param["handler"], $arr_handler);
            } else if ($_GET["module_id"] < 0)
            {
                if (!$arr_data["header_info"])
                {
                    file_put_contents("templates/main/css/global.css", $arr_data["style_code"]);
                    file_put_contents("templates/main/js/global.js", $arr_data["js_code"]);
                } else
                {
                    $content = file_get_contents("modules/main/main_printer.php");
                    if ($arr_data["header_info"]["js_includes"])
                    {
                        $pre = substr($content, 0, strpos($content, "/*<JAVASCRIPT-INCLUDES>*/")+strlen("/*<JAVASCRIPT-INCLUDES>*/"));
                        $post = substr($content, strpos($content, "/*</JAVASCRIPT-INCLUDES>*/"));
                        $content = $pre."\n";
                        foreach ($arr_data["header_info"]["js_includes"] as $inc)
                        {
                            $content .= "        \$obj_gen->addJavaScript(\"".$inc."\");\n";
                        }
                        $content .= $post;
                    }

                    if ($arr_data["header_info"]["css_includes"])
                    {
                        $pre = substr($content, 0, strpos($content, "/*<CSS-INCLUDES>*/")+strlen("/*<CSS-INCLUDES>*/"));
                        $post = substr($content, strpos($content, "/*</CSS-INCLUDES>*/"));
                        $content = $pre;
                        foreach ($arr_data["header_info"]["css_includes"] as $inc)
                        {
                            $content .= "        \$obj_gen->addStyleSheet(\"".$inc."\");\n";
                        }
                        $content .= $post;
                    }

                    file_put_contents("modules/main/main_printer.php", $content);
                }
            }
            $this->obj_data->insertModuleHistory($_GET["module_id"], $arr_param["module"], $arr_data);
            setcookie("klatcher[kmdtk][module][".$_GET["module_id"]."]", 1);
            echo $_GET["module_id"]."\n";
            if ($hid > 0)
            {
                echo $hid."\n";
            }
/*
            if ($_GET["module_id"] > 0)
            {
                $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
                $arr_obj["m_style_code_".$_GET["module_id"]] = crc32($arr_data["style_code"]);
                $arr_obj["m_js_code_".$_GET["module_id"]] = crc32($arr_data["js_code"]);
                file_put_contents("builder.crc32", json_encode($arr_obj));
            } */

            die ("success");
        } else if ($_GET["check"] == "2")
        {
            $arr_data = $_POST;
            $arr_data["oldDB"] = ($arr_data["oldDB"]);
            $arr_data["newUser"] = ($arr_data["newUser"]);
            $arr_data["newDB"] = $this->obj_data->selectModule($_GET["module_id"]);
            echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
            die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        if ($_GET["module_id"] < 0)
        {
            $arr_param["module"] = Array(
            "module_id"=>"-1",
            "name"=>"Global",
            "js_code"=>file_get_contents("templates/main/js/global.js"),
            "style_code"=>file_get_contents("templates/main/css/global.css")
            );
            $c = file_get_contents("modules/main/main_printer.php");
            $jsinc = substr($c, $start = strpos($c, "/*<JAVASCRIPT-INCLUDES>*/")+strlen("/*<JAVASCRIPT-INCLUDES>*/"), strpos($c, "/*</JAVASCRIPT-INCLUDES>*/")-$start);
            $jsinc = str_replace(Array("\$obj_gen->addJavaScript(\"", "\");"), "", $jsinc);
            $cssinc = substr($c, $start = strpos($c, "/*<CSS-INCLUDES>*/")+strlen("/*<CSS-INCLUDES>*/"), strpos($c, "/*</CSS-INCLUDES>*/")-$start);
            $cssinc = str_replace(Array("\$obj_gen->addStyleSheet(\"", "\");"), "", $cssinc);

            $arr_param["module"]["header_info"] = Array();
            $arr_param["module"]["header_info"]["js_includes"] = preg_split("/\\s*\n\\s+/", trim($jsinc));
            $arr_param["module"]["header_info"]["css_includes"] = preg_split("/\\s*\n\\s+/", trim($cssinc));
        } else
        {
            $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
            $arr_param["module"]["header_info"] = @unserialize($arr_param["module"]["header_info"]);
        }
        
        if ($_GET["refresh"]) {
        	die(json_encode(Array("code"=>$arr_param["module"][$_GET["refresh"]])));
        }

        // create crc32 files
/*
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["module_id"] > 0 && $arr_obj["m_style_code".$_GET["module_id"]] != crc32($arr_param["module"]["style_code"]) || $arr_obj["m_js_code".$_GET["module_id"]] != crc32($arr_param["module"]["js_code"]))
        {
            $arr_obj["m_style_code_".$_GET["module_id"]] = crc32($arr_param["module"]["style_code"]);
            $arr_obj["m_js_code_".$_GET["module_id"]] = crc32($arr_param["module"]["js_code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
        } //*/

        die ($this->_callPrinter("editModule", $arr_param));
    }

    function moduleHistory()
    {
        $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
        $arr_param["history"] = $this->obj_data->listModuleHistory($_SESSION["module_id"]);

        die ($this->_callPrinter("moduleHistory", $arr_param));
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
        $_SESSION["handler_id"] = $_GET["handler_id"] = if_set($_GET["handler_id"], $_SESSION["handler_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["handler"];
            $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
            $arr_data["fk_module_id"] = $_GET["module_id"];
            $arr_data["flag_ajax"] = (int)$arr_data["flag_ajax"];
		    $arr_data["flag_cacheable"] = (int)$arr_data["flag_cacheable"];
            $arr_data["code"] = ($arr_data["code"]);
            $arr_data["html_code"] = ($arr_data["html_code"]);
            
			$this->updateCRCFile(Array("codeh".$_GET["handler_id"], "html_codeh".$_GET["handler_id"]));
            
            if ($_GET["handler_id"] > 0)
            {
                $arr_param["handler"] = $this->obj_data->selectHandler($_GET["handler_id"]);
                $this->obj_data->updateHandler($_GET["handler_id"], $arr_data);
               	$mod = $this->obj_data->selectModule($_SESSION["module_id"]);
                if (strlen($arr_param["handler"]["schedule"]) > 0) {
               		Quartz::removeJob(JSON_decode($arr_param["handler"]["schedule"], true), $mod["name"].":".$arr_param["handler"]["event"]);
               	}
                if (strlen($arr_data["schedule"]) > 0) {
                	Quartz::addJob(JSON_decode($arr_data["schedule"], true), $mod["name"].":".$arr_param["handler"]["event"]);
                } 
            } else if ((int)$_GET["handler_id"] == 0)
            {
                $_SESSION["handler_id"] = $_GET["handler_id"] = $this->obj_data->insertHandler($arr_data);
            } else if ($_GET["handler_id"] < 0)
            {
                $arr_param["handler"]["handler_id"] = -1;
                $arr_param['handler']['event'] = "home";
                // parse main handler for handler code
                $content = file_get_contents("modules/main/main_handler.php");
                $match = substr($content, ($start = strpos($content, "/** BEGIN_CODE **/"))+strlen("/** BEGIN_CODE **/"), strpos($content, "/** END_CODE **/", $start)-$start-strlen("/** BEGIN_CODE **/"));
                $arr_param["handler"]["code"] = $match;
                $content = file_get_contents("templates/main/html/home.html");
                $arr_param["handler"]["html_code"] = $content;

                $content = file_get_contents("modules/main/main_handler.php");

                $arr_data["code"] = preg_replace("/([^a-zA-Z0-9])out\s*\((.*)\)([^a-zA-Z0-9])/", "\$1\$this->_callPrinter(\"home\", \$2)\$3", $arr_data["code"]);
                $arr_data["code"] = preg_replace("/\\\$data->/", "\$this->obj_data->", $arr_data["code"]);

                file_put_contents("modules/main/main_handler.php",
	                substr($content, 0, strpos($content, "/** BEGIN_CODE **/")+strlen("/** BEGIN_CODE **/")).
	                "\n".$arr_data["code"]."\n".
                	substr($content, strpos($content, "/** END_CODE **/"))
                );
                file_put_contents("templates/main/html/home.html", $arr_data["html_code"]);
            }
            $this->obj_data->insertHandlerHistory($_GET["handler_id"], $arr_param["handler"], $arr_data);
            echo $_GET["handler_id"]."\n";

/*/            
            if ($_GET["handler_id"] > 0)
            {
                $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
                $arr_obj["h_html_code_".$_GET["handler_id"]] = crc32($arr_data["html_code"]);
                $arr_obj["h_code_".$_GET["handler_id"]] = crc32($arr_data["code"]);
                file_put_contents("builder.crc32", json_encode($arr_obj));
            } //*/

            return $this->resetModule();
            //           die("success");
        } else if ($_GET["check"] == "2")
        {
            $arr_data = $_POST;
            $arr_data["oldDB"] = ($arr_data["oldDB"]);
            $arr_data["newUser"] = ($arr_data["newUser"]);
            $arr_data["newDB"] = $this->obj_data->selectHandler($_GET["handler_id"]);
            echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
            die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        if ($_GET["handler_id"] < 0 && $_GET["module_id"] < 0)
        {
            $arr_param["module"]["module_id"] = -1;
            $arr_param["module"]["name"] = "Global";
            $arr_param["handler"]["handler_id"] = -1;
            $arr_param['handler']['event'] = "home";
            // parse main handler for handler code
            $content = file_get_contents("modules/main/main_handler.php");
            $match = substr($content, ($start = strpos($content, "/** BEGIN_CODE **/"))+strlen("/** BEGIN_CODE **/"), strpos($content, "/** END_CODE **/", $start)-$start-strlen("/** BEGIN_CODE **/"));
            $arr_param["handler"]["code"] = $match;
            $content = file_get_contents("templates/main/html/home.html");
            $arr_param["handler"]["html_code"] = $content;
        } else
        {
            $arr_param["handler"] = $this->obj_data->selectHandler($_GET["handler_id"]);
            $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
            $arr_param["decorators"] = $this->obj_data->selectDecoratorEventsFromUser($_SESSION["builder"]["user_id"]);
        }
        
        if ($_GET["handler_id"] <= 0 && $_GET["module_id"] > 0) {
        	$arr_param["handler"]["html_code"] = file_get_contents("templates/builder/php/output_empty.php");
        	$arr_param["handler"]["code"] = file_get_contents("templates/builder/php/handler_empty.php");
        }
        
        $code = $arr_param['handler']['html_code'];
        preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
        $lastPos = 0;
        $codes = Array();
        array_push($codes, Array("title" => "Default", "id" => md5("h".$arr_param["handler"]["handler_id"])));
        if (is_array($matches) && is_array($matches[1])) {         
	        foreach ($matches[1] as $match) {
	        	$cd = Array(
	        		"title" => $match[0],
	        		"id" => md5($match[0].$arr_param["handler"]["handler_id"]) 
	        	);
	        	$start = $match[1] + strlen("".$match[0].">\n");
	        	$end = strpos($code, "</part-".$match[0].">\n", $match[1]);
	        	$cd["content"] = substr($code, $start, $end - $start);
	        	$lastPos = strpos($code, "</part-".$match[0].">\n", $match[1]) + strlen("</part-".$match[0].">\n");
	        	array_push($codes, $cd);
	        }
        }
        $codes[0]["content"] = substr($code, $lastPos);
        $arr_param["html_codes"] = $codes;
        
        $code = $arr_param["handler"]["code"];
        preg_match_all('/\/\*\[BEGIN POST-([^\]]+)\]\*\//mi', $code, $matches, PREG_OFFSET_CAPTURE);
        $lastPos = 0;
        $codes = Array();
        array_push($codes, Array("title" => "Default", "id" => md5("h".$arr_param["handler"]["handler_id"])));
        if (is_array($matches) && is_array($matches[1])) {         
	        foreach ($matches[1] as $match) {
	        	$cd = Array(
	        		"title" => $match[0],
	        		"id" => md5($match[0].$arr_param["handler"]["handler_id"]) 
	        	);
	        	$start = strpos($code, "/*[ACTUAL]*/", $match[1]) + strlen("/*[ACTUAL]*/") + 1;
	        	$end = strpos($code, "/*[END ACTUAL]*/", $start);
	        	$cd["content"] = substr($code, $start, $end - $start);
	        	$lastPos = strpos($code, "/*[END POST-".$match[0]."]*/\n", $match[1]) + strlen("/*[END POST-".$match[0]."]*/\n");
	        	array_push($codes, $cd);
	        }
        }
        $codes[0]["content"] = substr($code, $lastPos);
        $arr_param["codes"] = $codes;
                        
        if ($_GET["refresh"]) {
        	die(json_encode(Array("code"=>$arr_param["handler"][$_GET["refresh"]])));
        }

        die ($this->_callPrinter("editHandler", $arr_param));
    }

    function handlerHistory()
    {
        $_SESSION["handler_id"] = if_set($_GET["handler_id"], $_SESSION["handler_id"]);
        $arr_param["history"] = $this->obj_data->listHandlerHistory($_SESSION["handler_id"]);

        die ($this->_callPrinter("handlerHistory", $arr_param));
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
        $_SESSION["data_id"] = $_GET["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["data"];
            $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
            $arr_data["fk_module_id"] = $_GET["module_id"];
            $arr_data["code"] = ($arr_data["code"]);
            
            $this->updateCRCFile(Array("coded".$_GET["data_id"]));
            
            if ($_GET["data_id"] > 0)
            {
                $arr_param["data"] = $this->obj_data->selectData($_GET["data_id"]);
                $this->obj_data->updateData($_GET["data_id"], $arr_data);
            } else
            {
                $_SESSION["data_id"] = $_GET["data_id"] = $this->obj_data->insertData($arr_data);
            }
            $this->obj_data->insertDataHistory($_GET["data_id"], $arr_param["data"], $arr_data);
            echo $_GET["data_id"]."\n";
            
/*
            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["d_code_".$_GET["data_id"]] = crc32($arr_data["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
//*/            

            return $this->resetModule();
            //           die("success");
        } else if ($_GET["check"] == "2")
        {
            $arr_data = $_POST;
            $arr_data["oldDB"] = ($arr_data["oldDB"]);
            $arr_data["newUser"] = ($arr_data["newUser"]);
            $arr_data["newDB"] = $this->obj_data->selectData($_GET["data_id"]);
            echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
            die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        $arr_param["data"] = $this->obj_data->selectData($_GET["data_id"]);
        
        if ($_GET["refresh"]) {
        	die(json_encode(Array("code"=>$arr_param["data"][$_GET["refresh"]])));
        }

        // create crc32 files
/*/
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["data_id"] > 0 && $arr_obj["d_code".$_GET["data_id"]] != crc32($arr_param["data"]["code"]))
        {
            $arr_obj["d_code_".$_GET["data_id"]] = crc32($arr_param["data"]["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
        }//*/

        die ($this->_callPrinter("editData", $arr_param));
    }

    function dataModel()
    {
        global $arr_database;
        $_SESSION["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);

        $arr_param["db"] = $arr_database;

        die ($this->_callPrinter("dataModel", $arr_param));
    }

    function dataHistory()
    {
        $_SESSION["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
        $arr_param["history"] = $this->obj_data->listDataHistory($_SESSION["data_id"]);

        die ($this->_callPrinter("dataHistory", $arr_param));
    }

    function deleteLibrary()
    {
        $lib = $this->obj_data->selectLibrary($_GET["library_id"]);
        if ($lib["fk_resource_id"] > 0) {
        	$this->obj_data->deleteResource($lib["fk_resource_id"]);
          	if ($lib["fk_resource_id"] > 0) {
				if (ENV_PRODUCTION) {
					removeDir("lib/custom/".$lib["name"], true);
				} else {
					removeDir("lib/custom/".$lib['name'].$_GET["library_id"], true);
				}
           	} else {
           		if (ENV_PRODUCTION) {
           		    @unlink("lib/custom/".$lib["name"].".php");
              	} else {
                   	@unlink("lib/custom/".$lib["name"].$_GET["library_id"].".php");
                }
           	}
        }
    	$this->obj_data->deleteLibrary($_GET["library_id"]);
        die ("success");
    }

    function editLibrary()
    {
        $_SESSION["library_id"] = $_GET["library_id"] = if_set($_GET["library_id"], $_SESSION["library_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
        	$this->updateCRCFile(Array("codel".$_GET["library_id"]));
            $arr_library = $_POST["library"];
            $arr_library["code"] = ($arr_library["code"]);
            if ($_GET["library_id"] > 0)
            {
                $arr_param["library"] = $this->obj_data->selectLibrary($_GET["library_id"]);
                $this->obj_data->updateLibrary($_GET["library_id"], $arr_library);
            } else
            {
                $arr_library["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_library["fk_module_id"] = $_GET["module_id"];
                $_SESSION["library_id"] = $_GET["library_id"] = $this->obj_data->insertLibrary($arr_library);
            }
            $this->obj_data->insertLibraryHistory($_GET["library_id"], $arr_param["library"], $arr_library);
            if (strlen($arr_param["library"]["name"]) > 0) {
            	if ($arr_param["library"]["fk_resource_id"] > 0) {
					if (ENV_PRODUCTION) {
						removeDir("lib/custom/".$arr_param["library"]["name"], true);
					} else {
						removeDir("lib/custom/".$arr_param["library"]['name'].$_GET["library_id"], true);
					}
            	} else {
            		if (ENV_PRODUCTION) {
            		    @unlink("lib/custom/".$arr_param["library"]["name"].".php");
                	} else {
                    	@unlink("lib/custom/".$arr_param["library"]["name"].$_GET["library_id"].".php");
	                }
            	}
            }
            echo $_GET["library_id"]."\n";
/*/
            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["l_code_".$_GET["library_id"]] = crc32($arr_library["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));//*/

            return $this->resetModule();
            //           die("success");
        } else if ($_GET["check"] == "2")
        {
            $arr_data = $_POST;
            $arr_data["oldDB"] = ($arr_data["oldDB"]);
            $arr_data["newUser"] = ($arr_data["newUser"]);
            $arr_data["newDB"] = $this->obj_data->selectLibrary($_GET["library_id"]);
            echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
            die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        } else if ($_GET["select"]) {
        	$arr_param["library"] = $this->obj_data->selectLibrary($_GET["library_id"]);
        	if (file_exists("lib/custom/".$arr_param['library']['name']."/".$_GET['select'])) {
        		highlight_file("lib/custom/".$arr_param['library']['name']."/".$_GET['select']);
        	} else if (file_exists("lib/custom/".$arr_param['library']['name'].$arr_param['library']['library_id']."/".$_GET['select'])) {
        		highlight_file("lib/custom/".$arr_param['library']['name'].$arr_param['library']['library_id']."/".$_GET['select']);
        	}
        	die();
        } else if ($_GET["import"] == "1") {
        	$libName = preg_replace('/[^a-zA-Z0-9_]/mi', '', substr($_GET["name"], 0, strpos($_GET["name"], ".")));
        	if (strlen($libName) <= 0 || file_exists("lib/custom/".$libName."/")) {
        		$libName = preg_replace('/[^a-zA-Z0-9_]/mi', '', $_GET["name"]);
        		if (strlen($libName) <= 0 || file_exists("lib/custom/".$libName."/")) {
        			$libName = "NewLibrary";
        			$i = "";
        			while (file_exists("lib/custom/".$libName.$i."/")) {
        				$i++;
        			}
        			$libName = $libName.$i;
        		}
        	}
        	$file = receiveFile($_GET["name"], "lib/custom/".$libName.'/');
        	preg_match('/((\.tar\.[a-zA-Z0-9]+)|(\.tgz)|(\.zip))$/mi', $file, $match);
        	if (strlen($match[1]) > 0) {
        		// unpack it to get contents
        		$progs = Array(".tar.bz2" => "tar -xvjf ", ".tar.gz" => "tar -xvzf ", ".tgz" => "tar -xvzf ", ".zip" => "unzip ");
        		exec("cd ".dirname($file)."; ".$progs[$match[1]].basename($file));
        		function getTree($root, $exclude = Array()) {
        			$tree = Array();
        			$dp = opendir($root);
        			while (($na = readdir($dp)) !== false) {
        				if ($na[0] != "." && !in_array($root.'/'.$na, $exclude)) {
        					$tree[$na] = filesize($root.'/'.$na);
        					if (is_dir($root.'/'.$na)) {
        						$tree[$na] = getTree($root.'/'.$na);
        					}
        				}
        			}
        			closedir($dp);
        			return $tree;
        		}
        		$tree = getTree(dirname($file), Array($file));
        	} else {
        		$tree = Array();
        		$tree[basename($file)] = filesize($file);
        	}
            $content = file_get_contents($file);
            $arr_data["fk_module_id"] = 0;
            $arr_data["type"] = mime_content_type($file);
            $arr_data["data"] = base64_encode($content);
            $arr_data["tree"] = json_encode($tree);
            $arr_data["name"] = $_GET["name"];
            $resource_id = $this->obj_data->insertResource($arr_data);
        	
        	$arr_param["library"]["name"] = $libName;
        	$arr_param["library"]["fk_resource_id"] = $resource_id;
        	$arr_param["library"]["fk_user_id"] = $_SESSION["builder"]["user_id"];
        	$arr_param["library"]["fk_module_id"] = 0;
        	ob_start();
        	require("templates/builder/php/library_upload_scaffold.php");
        	$arr_param["library"]["code"] = ob_get_clean();
            $_SESSION["library_id"] = $_GET["library_id"] = $this->obj_data->insertLibrary($arr_param["library"]);
//        	removeDir("lib/custom/".$libName."/", true);
        }

        $arr_param["library"] = $this->obj_data->selectLibrary($_GET["library_id"]);

        if ($_GET["refresh"]) {
        	die(json_encode(Array("code"=>$arr_param["library"][$_GET["refresh"]])));
        }
        // create crc32 files
/*/
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["library_id"] > 0 && $arr_obj["l_code".$_GET["library_id"]] != crc32($arr_param["library"]["code"]))
        {
            $arr_obj["l_code_".$_GET["library_id"]] = crc32($arr_param["library"]["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
        }//*/

        die ($this->_callPrinter("editLibrary", $arr_param));
    }

    function deleteTag()
    {
        $this->obj_data->deleteTag($_GET["tag_id"]);
        die ("success");
    }

    function editTag()
    {
        $_SESSION["tag_id"] = $_GET["tag_id"] = if_set($_GET["tag_id"], $_SESSION["tag_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
        	$this->updateCRCFile(Array("codet".$_GET["tag_id"]));
        	
        	$arr_tag = $_POST["tag"];
            //	   	   $arr_tag["html_code"] = addslashes($arr_tag["html_code"]);
            if ($_GET["tag_id"] > 0)
            {
                $arr_param["tag"] = $this->obj_data->selectTag($_GET["tag_id"]);
                $arr_param["tag"]["html_code"] = ($arr_param["tag"]["html_code"]);
                $this->obj_data->updateTag($_GET["tag_id"], $arr_tag);
            } else
            {
                $arr_tag["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_tag["fk_module_id"] = $_GET["module_id"];
                $_SESSION["tag_id"] = $_GET["tag_id"] = $this->obj_data->insertTag($arr_tag);
            }
            $this->obj_data->insertTagHistory($_GET["tag_id"], $arr_param["tag"], $arr_tag);
            echo $_GET["tag_id"]."\n";

/*/
            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["t_html_code_".$_GET["tag_id"]] = crc32($arr_tag["html_code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));//*/
			if ($arr_tag["fk_module_id"] > 0 || $arr_param["tag"]["fk_module_id"] > 0) {
            	return $this->resetModule();
			}
            die("success");
        } else if ($_GET["check"] == "2")
        {
            $arr_data = $_POST;
            $arr_data["oldDB"] = ($arr_data["oldDB"]);
            $arr_data["newUser"] = ($arr_data["newUser"]);
            $arr_data["newDB"] = $this->obj_data->selectTag($_GET["tag_id"]);
            echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
            die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
        }

        $arr_param["tag"] = $this->obj_data->selectTag($_GET["tag_id"]);
        
        if ($_GET["refresh"]) {
        	die(json_encode(Array("code"=>$arr_param["tag"][$_GET["refresh"]])));
        }        
        // create crc32 files
/*/
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["tag_id"] > 0 && $arr_obj["t_html_code".$_GET["tag_id"]] != crc32($arr_param["tag"]["html_code"]))
        {
            $arr_obj["t_html_code_".$_GET["tag_id"]] = crc32($arr_param["tag"]["html_code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
        }//*/

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
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
        $_SESSION["resource_id"] = $_GET["resource_id"] = if_set($_GET["resource_id"], $_SESSION["resource_id"]);

        if ($_GET["check"] == "1")
        {
            $id = $_GET["resource_id"];
            if ($id > 0)
            {
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
                $arr_data["fk_module_id"] = $_GET["module_id"];
                $arr_data["type"] = $_FILES["resource"]["type"]["file"];
                $arr_data["data"] = base64_encode($content);
                if ($_GET["resource_id"] > 0)
                {
                    $this->obj_data->updateResource($_GET["resource_id"], $arr_data);
                } else
                {
                    $arr_data["name"] = $_FILES["resource"]["name"]["file"];
                    $_SESSION["resource_id"] = $this->obj_data->insertResource($arr_data);
                }
                echo "<img src='?event=builder:previewResource&resource_id=".$_SESSION["resource_id"]."' width='64' />\n";
                echo "OK\n";
                echo $arr_data["name"];
                die ();
            }

            $arr_data = $_POST["resource"];
            if ($_GET["resource_id"] > 0)
            {
                $this->obj_data->updateResource($_GET["resource_id"], $arr_data);
            } else
            {
                $arr_data["fk_module_id"] = $_GET["module_id"];
                $_SESSION["resource_id"] = $_GET["resource_id"] = $this->obj_data->insertResource($arr_data);
            }
            return $this->resetModule();
        } else if ($_GET["check"] == "upload") {
        	$file = receiveFile($_GET["name"], ($_GET["module_id"] < 0 ? "templates/main/resources/" : "static/"));
        	if ($_GET["module_id"] > 0) {
	            $content = file_get_contents($file);
	        	$mime = mime_content_type($file);
	        	@unlink($file);
	            $arr_data["fk_module_id"] = $_GET["module_id"];
	            $arr_data["type"] = $mime;
	            $arr_data["data"] = base64_encode($content);
	            if ($_GET["resource_id"] > 0) {
	            	$this->obj_data->updateResource($_GET["resource_id"], $arr_data);
	            } else {
	                $arr_data["name"] = basename($file);
	                $_SESSION["resource_id"] = $this->obj_data->insertResource($arr_data);
	            }
        	} else {
        		$res = $this->obj_data->selectResource($_GET["resource_id"], $_GET["module_id"]);
        		global $log;
        		if ($res) {
        			@copy($file, "templates/main/resources/".$res["name"]) or $log->debug("error copying to existing file");
        			@unlink($file) or $log->debug("error removing file");
        		}
        	}
        	die();
        }

        $arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"], $_GET["module_id"]);
        if ($_GET["module_id"] < 0) {
        	$arr_param["module"]["name"] = "main";	
        	$arr_param["local"]["path"] = "resources";
        } else {
        	$arr_param["local"]["path"] = "images";
        	$arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
        }

        die ($this->_callPrinter("editResource", $arr_param));
    }

    function previewResource()
    {
        if ($_GET["resource_id"] > 0 || $_GET["module_id"] < 0) {
            $arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"], $_GET["module_id"]);
            if ($arr_param["resource"] != null) {
	            header("Content-Type: ".$arr_param["resource"]["type"]);
            	if ($_GET["module_id"] < 0) {
	            	readfile("templates/main/resources/".$arr_param["resource"]["name"]);
	            	die();	
	            } else {
	            	die (base64_decode($arr_param["resource"]["data"]));
	            }
            }
        } else {
            readfile("templates/builder/images/empty_resource.gif");
            die ();
        }
    }

    function createResource()
    {
		$omod = $_GET["mod"];
    	$_GET["mod"] = preg_replace('/([a-zA-Z_]+)[0-9]*$/', '\1', $_GET["mod"]);
        $arr_param["module"] = $this->obj_data->selectModuleByUserAndName($_SESSION["builder"]["user_id"], strtolower($_GET["mod"]), true);
        $arr_param["resource"] = $this->obj_data->selectResourceByName($arr_param["module"]["module_id"], $_GET["resource"]);

        if ($arr_param["resource"] != null)
        {
            $mod = $arr_param["module"]["name"];
            
            if (!ENV_PRODUCTION) {
            	$mod = $omod;
            }

            $basePath = "templates/".strtolower($mod)."/images/";
            $path = $basePath.$arr_param["resource"]["name"];
            if (!file_exists($basePath))
            {
                mkdir($basePath, 0755, true);
                @chmod($basePath, 0755);
                @chmod("templates/".strtolower($mod), 0755);
            }
            file_put_contents($path, base64_decode($arr_param["resource"]["data"]));
            @chmod($path, 0755);
            header("Content-Type: ".$arr_param["resource"]["type"]);
            readfile($path);
        }
        die ();
    }

    function deleteResource()
    {
        if ($_GET["resource_id"] > 0)
        {
        	$arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"], $_GET["module_id"]);
        	if ($_GET["module_id"] < 0) {
        		$path = "templates/main/resources/".$arr_param["resource"]["name"];
        	} else {
	            $arr_param["module"] = $this->obj_data->selectModule($arr_param["resource"]["fk_module_id"]);
	
	            $mod = strtolower($arr_param["module"]["name"]);
	
	            $basePath = "templates/".$mod."/images/";
	            $path = $basePath.$arr_param["resource"]["name"];
	
	            $this->obj_data->deleteResource($_GET["resource_id"]);
        	}
            @unlink($path);
        }
    }

    function help()
    {
        if ($_GET["time"])
        {
            $fp = fopen("cache/test/".$_SESSION["builder"]["name"].".log", "a+");
            $line = "[".date("Y-m-d H:i:s", time()-$_GET["time"])."] came from ".$_GET["referer"]." to ".$_GET["page"]." stayed ".$_GET["time"]." seconds\n";
            fwrite($fp, $line);
            fclose($fp);
            die ();
        }

        $filename = "templates/builder/html/help/".if_set($_GET["path"], "index.html");
		$cnt = file_get_contents($filename);
		$endl = "ENDL";
		$cnt = str_replace('$', "\$", $cnt);
		if (floatval(phpversion()) >= 5.3) $endl = "'ENDL'";
		$cnt = str_replace(Array("<pre>", "</pre>", "<code>", "</code>"), Array("<pre><? \$cnt=<<<".$endl."\n", "\nENDL;\nhl(\$cnt); ?></pre>", "<? \$cnt=<<<".$endl."\n", "\nENDL;\nhl(\$cnt); ?".">"), $cnt);
		$cnt = "<? function hl(\$str) {\$res = highlight_string('<? '.\$str.' ?>', true); echo str_replace('?&gt;</span>', '</span>', str_replace('&lt;?&nbsp;', '', \$res));}?>\n".$cnt;
        file_put_contents("cache/".md5($filename).".api", $cnt);
        require("cache/".md5($filename).".api");
        @unlink("cache/".md5($filename).".api");
        die ();
    }

    function logout()
    {
        $_SESSION["builder"] = Array();
        if (session_id() != "") session_destroy();
        session_write_close();
	    header('WWW-Authenticate: Basic realm="Prails Web Framework Realm"');
	    header('HTTP/1.0 401 Unauthorized');
        if ($_GET["norelogin"] == "1") {
        	require("templates/builder/html/loggedout.html");
        	die();	
        } else {
	        require ("templates/builder/html/not_allowed.html");
	        die ();
        }
    }

    function deleteTable()
    {
        $arr_table = $this->obj_data->selectTable($_GET["table_id"]);
        if (strlen($arr_table["name"]) > 0)
        {
            $this->obj_data->SqlQuery("DROP TABLE IF EXISTS ".$arr_table["name"]);
        }
        $this->obj_data->deleteTable($_GET["table_id"]);
        die ("success");
    }

    function editTable()
    {
        $_SESSION["table_id"] = $_GET["table_id"] = if_set($_GET["table_id"], $_SESSION["table_id"]);
        $_SESSION["module_id"] = $_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_table = $_POST["table"];
			$needFlush = false;
            if ($_GET["table_id"] > 0) {
                $arr_param["table"] = $this->obj_data->selectTable($_GET["table_id"]);
                $this->obj_data->updateTable($_GET["table_id"], $arr_table);
            } else {
                $arr_table["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_table["fk_module_id"] = $_GET["module_id"];
                $_SESSION["table_id"] = $_GET["table_id"] = $this->obj_data->insertTable($arr_table);
            }
            $arr_module = $this->obj_data->selectModule($_POST["scaffold"]["fk_module_id"]);
            $arr_module["name"] = strtolower($arr_module["name"]);
            if ($_POST["d_scaffold"])
            {
            	$needFlush = true;
                foreach ($_POST["d_scaffold"] as $data=>$one)
                {
                	$exists = ($this->obj_data->getDataFromName($data.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1), $_POST["scaffold"]["fk_module_id"]) != null);
					if ($exists) continue;
                    ob_start();
                    @ require ("templates/builder/php/data_scaffold_".$data.".php");
                    $query = ob_get_clean();
                    $did = $this->obj_data->insertData($arr_data = Array(
	                    "name"=>$data.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1),
	                    "code"=>$query,
	                    "fk_module_id"=>$_POST["scaffold"]["fk_module_id"]
                    ));
                    $this->obj_data->insertDataHistory($did, null, $arr_data);
                }
            }
            if ($_POST["h_scaffold"])
            {
            	$needFlush = true;                	
                foreach ($_POST["h_scaffold"] as $handler=>$one)
                {
                	$exists = ($this->obj_data->selectHandlerByNameAndModule($_POST["scaffold"]["fk_module_id"], $handler.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1)) != null);
					if ($exists) continue;
                    ob_start();
                    @ require ("templates/builder/php/handler_scaffold_".$handler.".php");
                    $code = ob_get_clean();
                    ob_start();
                    @ require ("templates/builder/php/handler_scaffold_".$handler."_html.php");
                    $htmlcode = ob_get_clean();
                    $hid = $this->obj_data->insertHandler($arr_data = Array(
	                    "event"=>$handler.strtoupper($arr_table["name"][0]).substr($arr_table["name"], 1),
	                    "code"=>$code,
	                    "html_code"=>$htmlcode,
	                    "fk_module_id"=>$_POST["scaffold"]["fk_module_id"]
                    ));
                    $this->obj_data->insertHandlerHistory($hid, null, $arr_data);
                }
            }
			if ($needFlush) {
				$this->resetModule(false, $_POST["scaffold"]["fk_module_id"]);
			}
			
            $this->obj_data->insertTableHistory($_GET["table_id"], $arr_param["table"], $arr_table);

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
            echo $_GET["table_id"]."\n";
			$this->obj_data->obj_mysql->flush();            
            die ("success");
        }

        $arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["table"] = $this->obj_data->selectTable($_GET["table_id"]);
        $arr_param["tables"] = $this->obj_data->listTablesFromUser($_SESSION["builder"]["user_id"]);
        
        die ($this->_callPrinter("editTable", $arr_param));
    }

    function deleteConfiguration() {
        $_SESSION["configuration_id"] = if_set($_GET["configuration_id"], $_SESSION["configuration_id"]);
        $this->obj_data->deleteConfiguration($_GET["configuration_id"]);
        return $this->resetModule(true, $_GET["module_id"]);
    }

    function editConfiguration() {
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_configuration = $_POST["configuration"];

            if ($_GET["module_id"] < 0)
            {
                // store the changed data in our configuration.php
                updateConfiguration($arr_configuration);
            } else
            {
                $this->obj_data->clearConfiguration($_GET["module_id"], (int)$_GET["type"]);
                foreach ($arr_configuration as $arr_conf)
                {
                    $arr_conf["fk_module_id"] = $_GET["module_id"];
                    $this->obj_data->insertConfiguration($arr_conf);
                }
            }
            
            return $this->resetModule($_GET["die"] == "no", $_GET["module_id"]);
        }

        if ($_GET["module_id"] < 0)
        {
            $arr_settings = getConfiguration();
            $arr_param["configuration"] = Array();
            foreach ($arr_settings as $name=>$value)
            {
            	if (gettype($value) == "boolean") $value = ($value ? "true" : "false");
                array_push($arr_param["configuration"], Array(
                	"fk_module_id"=>-1,
                	"flag_public"=>0,
                	"name"=>$name,
                	"value"=>$value
                ));
            }
            $arr_param["module"] = Array(
            	"module_id"=>"-1",
            	"name"=>"Global"
            );
        } else
        {
            $arr_param["configuration"] = $this->obj_data->listConfigurationFromModule($_GET["module_id"], (int)$_GET['type']);
            $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
        }

        die ($this->_callPrinter("editConfiguration", $arr_param));
    }

    function shout()
    {
        $me = $_SESSION["builder"]["name"];
        $msg = ($_POST["msg"]);

        $entry = "<p><span class='sbuser'>".$me."</span> <span class='sbdate'>(".date("H:i:s").")</span><br/><span class='sbtext'>".str_replace("\n", "<br/>", str_replace("\r\n", "<br/>", $msg))."</span></p>";
        $chat = @file_get_contents("shout.box");
        if (!$chat)
            $chat = Array();
        else $chat = explode("\n", $chat);
    	array_unshift($chat, $entry);
		if (count($chat) > 100)array_pop($chat);
		file_put_contents("shout.box", $content = implode("\n", $chat));
		@chmod("shout.box", 0755);
		@touch("shout.box");
		die ($content);
	}

	function queryTest()
	{
    	$arr_param = Array();

		if ($_GET["check"] == "1")
		{
    		$query = ($_POST["query"]);
    		if (preg_match('/\s+LIMIT\s+([0-9]+)\s*,?\s*([0-9]+)\s*$/', $query, $match)) {
    			if (strlen($match[2]) > 0) {
    				$offset = $match[1];
    				$limit = $match[2];
    			} else {
    				$limit = $match[1];
    			}
    			$query = preg_replace('/\s+LIMIT\s+([0-9]+)\s*,?\s*([0-9]+)\s*$/', '', $query);
    		}
    		$this->obj_data->str_prefix = "tbl_";
    		if (strtoupper(substr($query, 0, 7)) == "SELECT ") {
    			$query .= " LIMIT [offset], [limit]";
				$arr_param["totals"] = $this->obj_data->SqlQuery("SELECT COUNT(*) AS total FROM (".str_replace(" LIMIT [offset], [limit]", "", $query).") AS a WHERE 1=1");
    		}
    		$arr_param["result"] = $this->obj_data->SqlQuery(str_replace(Array('[offset]', '[limit]'), Array(0, 1), $query));
    		$this->obj_data->str_prefix = "tbl_prailsbase_";
			$result = Array();
			$arr_param["error"] = $this->obj_data->obj_mysql->lastError;
			
			if (is_array($arr_param["result"])) foreach ($arr_param["result"] as $i => $res) {
				$arr_res["id"] = $i + 1;
				$arr_res = array_merge($arr_res, $res->getArrayCopy());
				foreach ($arr_res as $k => $val) {
					$arr_res[$k] = "<pre>".htmlspecialchars($val)."</pre>";
				}
				array_push($result, $arr_res);
			}
			if (count($result) <= 0 && strlen($arr_param["error"]) > 0) {
				array_push($result, Array("error" => $arr_param["error"])); 
			}
			ob_flush();
    		$_SESSION["builder"]["currentQuery"] = $query;
			$_SESSION["builder"]["currentQueryTotal"] = (int)$arr_param["totals"][0]["total"];
			session_write_close();
			die(json_encode(Array("result" => $result, "total" => (int)$arr_param["totals"][0]["total"], "query" => $query)));
		} else if (isset($_POST["start"])) {
			$query = if_set($_SESSION["builder"]["currentQuery"], $_GET["query"]);
			if (isset($_POST["sort"])) {
				$query = str_replace(" LIMIT [offset], [limit]", " ORDER BY ".$_POST["sort"]." ".$_POST["dir"]." LIMIT [offset], [limit]", $query);
			}
    		$this->obj_data->str_prefix = "tbl_";
			if (strtoupper(substr($query, 0, 7)) == "SELECT ") {
				$arr_param["result"] = $this->obj_data->SqlQuery(str_replace(Array('[offset]', '[limit]'), Array(if_set($_POST["start"], 0), if_set($_POST["limit"], 25)), $query));
    		}
			$this->obj_data->str_prefix = "tbl_prailsbase_";
    		$arr_param["error"] = $this->obj_data->obj_mysql->lastError;
			
			$result = Array();
			if (is_array($arr_param["result"])) foreach ($arr_param["result"] as $i => $res) {
				$arr_res["id"] = $i + 1;
				$arr_res = array_merge($arr_res, $res->getArrayCopy());
				foreach ($arr_res as $k => $val) {
					$arr_res[$k] = "".htmlspecialchars($val)."";
				}
				array_push($result, $arr_res);
			}
			if (count($result) <= 0 && strlen($arr_param["error"]) > 0) {
				array_push($result, Array("error" => $arr_param["error"])); 
			}
			ob_flush();			
			die(json_encode(Array("result" => $result, "total" => (int)$_GET["total"])));
						
		} else {
			$query = "SELECT name AS table_name, REPLACE(field_names, ':', ', ') AS fields FROM tbl_prailsbase_table WHERE fk_user_id=\"".$_SESSION["builder"]["user_id"]."\"";
			$_SESSION["builder"]["currentQuery"] = $query;
			
			$arr_param["result"] = $this->obj_data->SqlQuery($query);
		}

		die ($this->_callPrinter("queryTest", $arr_param));
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
		$arr_param["textResult"] = Generator::getInstance()->getLanguage()->findTextByContent($query);

		$arr_param["result"] = Array();
		if (is_array($arr_param["handlerResult"]))$arr_param["result"] = $arr_param["handlerResult"];
		if (is_array($arr_param["dataResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["dataResult"]);
		if (is_array($arr_param["libraryResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["libraryResult"]);
		if (is_array($arr_param["moduleResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["moduleResult"]);
		if (is_array($arr_param["tagResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["tagResult"]);
		if (is_array($arr_param["tableResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["tableResult"]);
		if (is_array($arr_param["textResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["textResult"]);
		header("Content-Type: application/json");
		die (json_encode($arr_param["result"]));
	}
	
	function niceUrl() {
		$arr_param = Array();
		$arr_param["handler"] = $this->obj_data->selectHandler($_GET["handler_id"]);
		
		if ($_GET["check"] == "1") {
			// save rules
			$arr_param["rules"] = $this->obj_data->listUrlRules();
			$arr_rules = Array();	
			
			foreach ($arr_param["rules"] as $keys=>$rule) {
				if (strpos($rule, "event=".$arr_param["handler"]["module"]["name"].":".$arr_param["handler"]["event"]) === false) {
					array_push($arr_rules, Array("nice" => $keys, "original" => $rule));
				}
			}
			array_push($arr_rules, $_POST["rule"]);
			$this->obj_data->updateUrlRules($arr_rules);
			
			die("success");
		}
		
		$arr_param["rules"] = $this->obj_data->listUrlRules();
		$arr_rule = null;	
		foreach ($arr_param["rules"] as $keys=>$rule) {
			if (strpos($rule, "event=".$arr_param["handler"]["module"]["name"].":".$arr_param["handler"]["event"]) !== false) {
				$arr_rule = Array($keys, $rule);
				break;
			}
		}
		$arr_param["parameters"] = $this->obj_data->listParametersFromRule($arr_rule);	
		
		die($this->_callPrinter("niceUrl", $arr_param));
	}

	// merges the new changes with the unsaved changes of the user, so that the unsaved ones survive too
	function _mergeContent($oldDB, $newDB, $newUser)
	{
	    $userChanges = diff($oldDB, $newUser);
		$dbChanges = diff($oldDB, $newDB);

		$lines = Array();
		foreach ($userChanges as $line=>$change)
		{
    		if (is_array($change))
    		{
        		array_push($lines, $line);
			}
		}
		foreach ($lines as $line)
		{
    		$dbChanges[$line] = $userChanges[$line];
		}

		$result = Array();
		foreach ($dbChanges as $line=>$content)
		{
    		if (is_array($content))
    		{
        		if ( isset ($content["i"]))
        		{
            		if (is_array($content["i"])) foreach ($content["i"] as $c)
            		{
                		array_push($result, $c);
        			} else
        			{
            			array_push($result, $content["i"]);
    				}
				}
			} else
			{
    			array_push($result, $content);
			}
		}
		return implode("\n", $result);
	}

	function proxyRequest()
	{
	    $data = file_get_contents($_GET["url"]);
		die ($data);
	}
	
	function export($file = null) {
		if (!is_array($file) && strlen($file) > 0) {
			$fp = fopen($file, "w");
		} else {
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT\n");
			header("Content-Transfer-Encoding: binary");
			header("Content-type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"".$_POST["file"]."\"");
			$fp = tmpfile();
		}
		$magic_border = md5(serialize($_POST));
		if ($_POST["modules"]) {
			fwrite($fp, "---".$magic_border."\n");
			$modules = Array();
			foreach ($_POST["modules"] as $mod) {
				$arr_module = $this->obj_data->selectModule($mod)->getArrayCopy();
				$arr_module["handlers"] = $this->obj_data->listHandlers($mod);
				$arr_module["datas"] = $this->obj_data->listDatas($mod);
				$arr_module["configsDevel"] = $this->obj_data->listConfigurationFromModule($mod, 1);
				$arr_module["configsProd"] = $this->obj_data->listConfigurationFromModule($mod, 2);
				$arr_module["resources"] = $this->obj_data->listResources($mod);
				$arr_module["testcases"] = $this->obj_data->listTestcase($mod);
				array_push($modules, $arr_module);				
			}
			fwrite($fp, "M");
			fwrite($fp, gzcompress(serialize($modules), 9));
			unset($modules);
		}
		if ($_POST["libraries"]) {
			fwrite($fp, "---".$magic_border."\n");
			$libraries = Array();
			foreach ($_POST["libraries"] as $lib) {
				$arr_library = $this->obj_data->selectLibrary($lib)->getArrayCopy();
				if ($arr_library["fk_resource_id"] > 0) {
					$arr_library["resource"] = $this->obj_data->selectResource($arr_library["fk_resource_id"])->getArrayCopy();
				}
				array_push($libraries, $arr_library);
			}
			fwrite($fp, "L");
			fwrite($fp, gzcompress(serialize($libraries), 9));
			unset($libraries);
		}
		if ($_POST["tags"]) {
			fwrite($fp, "---".$magic_border."\n");
			$tags = Array();
			foreach ($_POST["tags"] as $tag) {
				array_push($tags, $this->obj_data->selectTag($tag)->getArrayCopy());
			}
			fwrite($fp, "T");
			fwrite($fp, gzcompress(serialize($tags), 9));
			unset($tags);
		}
		if ($_POST["tables"]) {
			fwrite($fp, "---".$magic_border."\n");
			$tables = Array();
			foreach ($_POST["tables"] as $table) {
				array_push($tables, $this->obj_data->selectTable($table)->getArrayCopy());
			}
			fwrite($fp, "D");
			fwrite($fp, gzcompress(serialize($tables), 9));
			unset($tables);
		}
		if ($_POST["translations"]) {
			fwrite($fp, "---".$magic_border."\n");
			$arr_content = Array();
			$arr_content["texts"] = Array();
			$arr_content["languages"] = Generator::getInstance()->obj_lang->listLanguages();
			foreach ($_POST["translations"] as $root) {
				array_push($arr_content["texts"], Generator::getInstance()->obj_lang->listAllTextsFromRoot($root));
			}
			fwrite($fp, "C");
			fwrite($fp, gzcompress(serialize($arr_content), 9));
			unset($arr_content);
		}
		if ($_POST["db"]) {
			fwrite($fp, "---".$magic_border."\n");
			$dbs = Array();
			$oldPrefix = $this->obj_data->str_prefix;
			$this->obj_data->str_prefix = "tbl_";
			foreach ($_POST["db"] as $table) {
				$dbs[$table] = $this->obj_data->get($table);
			}
			$this->obj_data->str_prefix = $oldPrefix;
			fwrite($fp, "A");
			fwrite($fp, gzcompress(serialize($dbs), 9));
			unset($dbs);
		}
		if ($_POST["images"] == "1") {
			fwrite($fp, "---".$magic_border."\n");
			fwrite($fp, "I");
			$path = "static/images/";
			$dp = opendir($path);
			$fileMagic = "---".md5(time())."\n";
			while (($file = readdir($dp)) !== false) {
				if ($file[0] != '.' && is_file($path.$file) && filesize($path.$file) > 0) {
					fwrite($fp, $fileMagic . $file."\n");
					if (!is_array($file) && strlen($file) > 0) {
						$sp = fopen($path.$file, "r");
						while (!feof($sp)) {
							fwrite($fp, fread($sp, 4096));
						}
						fclose($sp);
					} else {
						readfile($path.$file);
					}
				}
			}
			closedir($path);
		}
		if (is_array($file) || strlen($file) <= 0) {
			fseek($fp, 0);
  			while (!feof($fp)) { 
    			$buffer = fread($fp, 2048); 
    			echo $buffer; 
    			ob_flush(); 
    			flush(); 
  			}
 		}
		fclose($fp);
		die();
	}
	
	function import($content = null, $dataRestore = null) {
		if ($_FILES["file"]) {
			$fp = fopen($_FILES["file"]["tmp_name"], "r");
//			$content = file_get_contents($_FILES["file"]["tmp_name"]);
		} else {
			if ($content == null || is_array($content)) {
				$fp = fopen("php://input", "r");
//				$content = file_get_contents("php://input");
			} else {
				$fp = fopen($content, "r");
			}
		}
		if ($fp) {
			// import everything we find
			if (fread($fp, 3) !== "---") {
				die("error parsing input file...");
			}
			function readUntil($fp, $edge) {
				$data = "";
				while (!feof($fp)) {
					$line = fgets($fp);
					if (in_array(($mgc = preg_replace('/^.*(---[a-fA-F0-9]+)$/mi', '\1', $line)), $edge)) {
						return $data . str_replace($mgc, '', $line);
					} else {
						$data .= $line;
					}
				}
				return $data;
			}
			$magic = "---" . fgets($fp, 1024);
			$moduleMapping = Array();
			$languageMapping = Array();
			$sections = explode($magic, substr($content, strlen($magic)));
			while (!feof($fp)) {
				$section = fread($fp, 1);
				if ($section !== "I") {
					$data = unserialize(gzuncompress(readUntil($fp, Array($magic))));
				} else {
					$fileMagic = fgets($fp);
					while (($file = readUntil($fp, Array($magic, $fileMagic)))) {
						$fpos = strpos($file, "\n");
						$name = substr($file, 0, $fpos);
						file_put_contents("static/images/".$name, substr($file, $fpos + 1));
					}
					continue;
				}
				
/*			}
//			foreach ($sections as $section) {
				if ($section[0] !== "I") {
					$data = unserialize(gzuncompress(substr($section, 1)));
				} else {
					$fileMagic = substr($section, 1, strpos($section, "\n"));					
					$files = explode($fileMagic, $section);
					for ($i = 1; $i < count($files); $i++) {
						$fpos = strpos($files[$i], "\n");
						$name = substr($files[$i], 0, $fpos);
						file_put_contents("static/images/".$name, substr($files[$i], $fpos + 1));
					}
				}*/
				if ($section[0] == "A") { // import database contents
					// ignore DB contents explicitly on production so that nothing will be imported accidentally
					if (ENV_PRODUCTION === true && !$dataRestore) continue;
					$oldPrefix = $this->obj_data->str_prefix;
					$this->obj_data->str_prefix = "tbl_";
					foreach ($data as $table => $arr_db) {
						if (DB_TYPE == SQLITE) {
							$this->obj_data->remove($table, "1");
						} else {
							$this->obj_data->query("TRUNCATE TABLE tbl_".$table);
						}
						foreach ($arr_db as $entry) {
							$this->obj_data->add($table, $entry->getArrayCopy());
						}
					}
					$this->obj_data->str_prefix = $oldPrefix;
				} else if ($section[0] == "D") {
					// import database table
					foreach ($data as $arr_table) {
						if (!is_array($arr_table)) $arr_table = $arr_table->getArrayCopy();
					    $arr_table["fk_user_id"] = $_SESSION["builder"]["user_id"];
					    unset($arr_table["table_id"]);
					    $d = $this->obj_data->selectTableFromUserAndName($arr_table["fk_user_id"], $arr_table["name"]);
					    $this->obj_data->deleteTable($d["table_id"]);
						$this->obj_data->insertTable($arr_table);
						// deploy table
			            $arr_fields = Array();
			            $items = explode(":", $arr_table["field_names"]);
			            $types = explode(":", $arr_table["field_types"]);
			            foreach ($items as $i=>$field) {
			                $arr_fields[$field] = $types[$i];
			            }
			            $arr_db = Array();
			            $arr_db[$arr_table["name"]] = $arr_fields;
			            DBDeployer::deploy($arr_db);
					}
				} else if ($section[0] == "C") {
					foreach ($data["languages"] as $lang) {
						$id = $lang["language_id"];
						unset($lang["language_id"]);
						$id2 = 0;
						$langs = Generator::getInstance()->obj_lang->listLanguages();
						foreach ($langs as $lg) {
							if ($lg["name"] == $lang["name"]) {
								$id2 = $lg["language_id"];
								Generator::getInstance()->obj_lang->deleteLanguage($lg["language_id"]);
								break;
							}
						}
						$languageMapping["new"][$id] = Generator::getInstance()->obj_lang->insertLanguage($lang);
						$languageMapping["old"][$id2] = $languageMapping["new"][$id]; 
					}
					foreach ($data["texts"] as $texts) {
						foreach ($texts as $text) {
							$text = $text->getArrayCopy();							
							$id = 0;
							unset($text["texts_id"]);
							$text["fk_language_id"] = $languageMapping["new"][$text["fk_language_id"]];
							Generator::getInstance()->obj_lang->insertText($text);
						}
					}
				} else if ($section[0] == "T") {
					foreach ($data as $tag) {
						if (!is_array($tag)) $tag = $tag->getArrayCopy();
						unset($tag["tag_id"]);
                        $tag["fk_user_id"] = $_SESSION["builder"]["user_id"];
 					    if ($tag["fk_module_id"] > 0) {
 					    	$tag["fk_module_id"] = $moduleMapping[$tag["fk_module_id"]];
 					    }
                        $t = $this->obj_data->selectTagByUserAndName($tag["fk_user_id"], $tag["name"]);
						$this->obj_data->deleteTag($t["tag_id"]);
						$this->obj_data->insertTag($tag);
					}
				} else if ($section[0] == "L") {
					foreach ($data as $library) {
						if (!is_array($library)) $library = $library->getArrayCopy();
						unset($library["library_id"]);
 					    $library["fk_user_id"] = $_SESSION["builder"]["user_id"];
 					    if ($library["fk_module_id"] > 0) {
 					    	$library["fk_module_id"] = $moduleMapping[$library["fk_module_id"]];
 					    }
 					    if ($library["fk_resource_id"] > 0 && $library["resource"]["resource_id"] > 0) {
 					    	unset($library["resource"]["resource_id"]);
 					    	$rid = $this->obj_data->insertResource($library["resource"]);
 					    	$library["fk_resource_id"] = $rid;
 					    }
 					    $l = $this->obj_data->selectLibraryByUserAndName($library["fk_user_id"], $library["name"]);
						$this->obj_data->deleteLibrary($l["library_id"]);
						$this->obj_data->insertLibrary($library);
					}
				} else if ($section[0] == "M") {
					foreach ($data as $mod) {
						if (!is_array($mod)) $mod = $mod->getArrayCopy();
						$mod["fk_user_id"] = $_SESSION["builder"]["user_id"];
					    $id = $mod["module_id"];
					    unset($mod["module_id"]);
                        $m = $this->obj_data->selectModuleByUserAndName($mod["fk_user_id"], $mod["name"]);
					    $this->obj_data->deleteModule($m["module_id"]);
						$this->resetModule(false, $m["module_id"]);
					    $modId = $this->obj_data->insertModule($mod);
						$moduleMapping[$id] = $modId;
						foreach ($mod["handlers"] as $handler) {
							$handler = $handler->getArrayCopy();
							$handler["fk_module_id"] = $modId;
							unset($handler["handler_id"]);
							$h = $this->obj_data->selectHandlerByNameAndModule($id, $handler["event"]);
							if (strlen($h["schedule"]) > 0) {
               					Quartz::removeJob(JSON_decode($h["schedule"], true), $mod["name"].":".$h["event"]);								
							}
							$this->obj_data->deleteHandler($h["handler_id"]);
							$this->obj_data->insertHandler($handler);
							if (strlen($handler["schedule"]) > 0) {
               					Quartz::addJob(JSON_decode($handler["schedule"], true), $mod["name"].":".$handler["event"]);
							}
						}
						foreach ($mod["datas"] as $dat) {
							$dat = $dat->getArrayCopy();
							unset($dat["data_id"]);
							$dat["fk_module_id"] = $modId;
							$d = $this->obj_data->getDataFromName($dat["name"], $id); 
							$this->obj_data->deleteData($d["data_id"]);
							$this->obj_data->insertData($dat);
						}
						if (is_array($mod["configs"])) {
							$this->obj_data->clearConfiguration($id, 0);
							foreach ($mod["configs"] as $config) {
								$config = $config->getArrayCopy();
								$config["fk_module_id"] = $modId;
								unset($config["configuration_id"]);
								$this->obj_data->insertConfiguration($config);
							}
						}	
						if (is_array($mod["configsDevel"])) {					
							$this->obj_data->clearConfiguration($id, 1);
							foreach ($mod["configsDevel"] as $config) {
								$config = $config->getArrayCopy();
								$config["fk_module_id"] = $modId;
								unset($config["configuration_id"]);
								$this->obj_data->insertConfiguration($config);
							}
						}
						if (is_array($mod["configsProd"])) {
							$this->obj_data->clearConfiguration($id, 2);
							foreach ($mod["configsProd"] as $config) {
								$config = $config->getArrayCopy();
								$config["fk_module_id"] = $modId;
								unset($config["configuration_id"]);
								$this->obj_data->insertConfiguration($config);
							}
						}
						foreach ($mod["resources"] as $res) {
							$res = $res->getArrayCopy();
							unset($res["resource_id"]);
							$res["fk_module_id"] = $modId;
							$r = $this->obj_data->selectResourceByName($id, $res["name"]);
							$this->obj_data->deleteResource($r["resource_id"]);
							$this->obj_data->insertResource($res);
						}
						$this->obj_data->clearTestcase($id);
						foreach ($mod["testcases"] as $tc) {
							$tc = $tc->getArrayCopy();
							unset($tc["testcase_id"]);
							$tc["fk_module_id"] = $modId;
							$this->obj_data->insertTestcase($tc);
						}
					}
				}
			} 
		}
		return $this->flushDBCache();
	}

	function editText() {
		$_SESSION["texts_id"] = $_GET["texts_id"] = if_set($_GET["texts_id"], $_SESSION["texts_id"]);
		
		if ($_GET["check"] == "1") {
			// update / insert texts
			$arr_data = $_POST["texts"];
			Generator::getInstance()->obj_lang->updateTexts($arr_data);
			if ($arr_data["type"] == '2') {
				$this->flushWebCache(true);
			}
		} else if ($_GET["check"] == "2") {
		    Generator::getInstance()->obj_lang->updateTextType($_POST["id"], $_POST["type"]);
		    die("success");
	    } else if ($_GET["check"] == "cadef") {
	    	$this->obj_data->updateCustom("texts", $_POST["custom"]);
	    	die("successdef");
	    }
		
		if ($_GET["ident"]) {
		    $arr_param["texts"] = Generator::getInstance()->obj_lang->getAllTextsByIdentifier($_GET["ident"]);
		    $_SESSION["texts_id"] = $_GET["texts_id"] = $arr_param["texts"][0]["texts_id"];
		} else {
    		$arr_param["texts"] = Generator::getInstance()->obj_lang->getAllTextsById($_GET["texts_id"]);
		}

		if ($_GET["path"] && $_GET["texts_id"] == 0) {
			$arr_param["text"]["path"] = $_GET["path"];
			if ($_GET["ident"]) {
		    	$arr_param["text"]["path"] = substr($_GET["path"], 0, strrpos($_GET["path"], ".")+1);
		    	$arr_param["text"]["name"] = substr($_GET["path"], strrpos($_GET["path"], ".")+1);
		    	$arr_param["texts"] = Generator::getInstance()->obj_lang->getAllTextsByIdentifier($arr_param["text"]["path"]);
		    	$arr_param["texts"][0]["identifier"] = $_GET["path"];
			}
		} else {
		    $arr_param["text"]["path"] = substr($arr_param["texts"][0]["identifier"], 0, strrpos($arr_param["texts"][0]["identifier"], ".")+1);
		    $arr_param["text"]["name"] = substr($arr_param["texts"][0]["identifier"], strrpos($arr_param["texts"][0]["identifier"], ".")+1);
		    $arr_param["text"]["type"] = $arr_param["texts"][0]["type"];
		    $arr_param["text"]["decorator"] = $arr_param["texts"][0]["decorator"];
		    $arr_param["text"]["title"] = $arr_param['texts'][0]["title"];
		    $arr_param["text"]["description"] = $arr_param['texts'][0]["description"];
		    $arr_param["text"]["custom"] = $arr_param['texts'][0]['custom'];
		}
		
        if ($arr_param["text"]["type"] == 2 || substr($arr_param["text"]["path"], 0, 4) == "cms.") {
            $arr_param["decorators"] = $this->obj_data->selectDecoratorEventsFromUser($_SESSION["builder"]["user_id"]);
			$arr_param["text"]["type"] = 2;
        }
        
        $arr_param["custom_struct"] = $this->obj_data->selectCustom("texts");
        
		return $this->_callPrinter("editText", $arr_param);
	}
	
	function deleteText() {
	   if ($_GET["ident"]) {
	       Generator::getInstance()->getLanguage()->deleteTextByIdentifier($_GET["ident"]);
	       die("success");
	   } else if ($_GET["section"]) {
	       Generator::getInstance()->getLanguage()->deleteSection($_GET["section"]);
	       die("success");
	   }
	}
	
	function editLanguage() {
	    $_SESSION["language_id"] = $_GET["language_id"] = if_set($_GET["language_id"], $_SESSION["language_id"]);
	    
	    if ($_POST["lang"]) {
	       $arr_data = $_POST["lang"];
	       $arr_data["default"] = $_POST["lang_default"];
	       
	       if ($_GET["language_id"] > 0) {
	           Generator::getInstance()->getLanguage()->updateLanguage($_GET["language_id"], $arr_data);
	       } else {
	           $_SESSION["language_id"] = $_GET["language_id"] = Generator::getInstance()->getLanguage()->insertLanguage($arr_data);
	       }
	       
	       header("Content-Type: application/json");
	       
	       die("{success: true}");
	    } else if ($_GET["delete"] > 0) {
	       Generator::getInstance()->getLanguage()->deleteLanguage($_GET["language_id"]);
	       die("success");
	    }
    }
    
    function updateSystem() {
        // run the system update
        if ($_GET["empty"] == "cache") {
			$this->obj_data->obj_mysql->flush();        	
			$this->flushWebCache(true);
			$this->flushCustomModules(true);
			die("success\n--\n".$_GET['warnings']);        	
        } else {
	        // first download the installer...
	        // clean cache first
	        exec("cd cache && rm -f * && cd ..");
	        $version = trim(file_get_contents(PRAILS_HOME_PATH."version"));
	        
	        file_put_contents("cache/installer.php", file_get_contents(PRAILS_HOME_PATH."installer.php"));
	        if (filesize("cache/installer.php") > 0 && md5(file_get_contents("cache/installer.php")) == md5(file_get_contents(PRAILS_HOME_PATH."installer.php"))) {
	            die("success\ncache/installer.php?version=".$version."\nDownloading package...");
	        } else {
	            die("Error saving installer. Please check permissions and internet connection.");
	        }
        }
    }
	
	function listTestcase() {
		$_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
		$arr_param = Array();
		if ($_GET["fetch"] == "all") {
			$arr_param["testsuite"] = $this->obj_data->listTestcase();
		} else if ($_GET["type"] == "single") {
			$arr_param["testsuite"] = Array();
			array_push($arr_param["testsuite"], $this->obj_data->selectTestcase($_GET["fetch"]));
		} else {
			$arr_param["testsuite"] = $this->obj_data->listTestcase($_GET["fetch"]);
		}
		
		$testsuite = "new Testsuite({name: '".$_GET["fetch"]."', testcases: [\n";
		foreach ($arr_param["testsuite"] as $i=>$testcase) {
			if ($i > 0) $testsuite .= ", \n";
			$testsuite .= "new Testcase({name: '".$testcase['name']."', setup: [\"".str_replace("\n", "\", \"", str_replace('"', '\"', $testcase['setup']))."\"], run: [\"".str_replace("\n", "\", \"", str_replace('"', '\"', $testcase["run"]))."\"], teardown: [\"".str_replace("\n", "\", \"", str_replace('"', '\"', $testcase['teardown']))."\"]})";
		}
		$testsuite .= "\n]})";
		
		if ($_GET["json"] == "1") {
			die($testsuite);
		} else {
			return $this->_callPrinter("listTestcase", $arr_param);
		}
	}
	
	function editTestcase() {
		$_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
		$_SESSION["testcase_id"] = $_GET["testcase_id"] = if_set($_GET["testcase_id"], $_SESSION["testcase_id"]);
		
		if ($_GET["check"] == "1") {
			$arr_data = $_POST["testcase"];
			if ($_GET["testcase_id"] > 0) {
				$this->obj_data->updateTestcase($_GET["testcase_id"], $arr_data);
			} else {
				$_GET["testcase_id"] = $_SESSION['testcase_id'] = $this->obj_data->insertTestcase($arr_data);
			}
			
			die("success");
		}
		
		$arr_param["testcase"] = $this->obj_data->selectTestcase($_GET["testcase_id"]);
		
		return $this->_callPrinter("editTestcase", $arr_param);
	}
	
	function deleteTestcase() {
        $_SESSION["testcase_id"] = if_set($_GET["testcase_id"], $_SESSION["testcase_id"]);
        $this->obj_data->deleteTestcase($_GET["testcase_id"]);
        die ("success");
	}
	
	function updateCRCFile($list = false) {
		$arr_obj = null;
		if (file_exists("builder.crc32")) {
			$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
		}
		
		if (!is_array($arr_obj)) $arr_obj = Array();
		if (is_array($list)) {
			foreach ($list as $entry) {
				unset($arr_obj[$entry]);
			}
		} else {
			if (strlen($_GET["dirty"]) > 0) {
				$arr_obj[$_GET["dirty"]] = Array("user" => $_SESSION["builder"]["name"], "uid" => $_SESSION["builder"]["user_id"], "time" => time());
			} else if ($_GET["clean"] && is_array($arr_obj[$_GET["clean"]])) {
				unset($arr_obj[$_GET["clean"]]);
			}
		}
		file_put_contents("builder.crc32", json_encode($arr_obj));
		
		$this->cleanUpCrc(true);
		if (!$list) {
			die("success");
		} else return "success";
	}
	
	function cleanUpCrc($force = false) {
        if (file_exists("builder.crc32") && ($force || filemtime("builder.crc32") <= time()-(60 * 60))) {
			$arr_obj = json_decode(file_get_contents("builder.crc32"), true);
			$result = Array();
			foreach ($arr_obj as $key => $value) {
				// if too old, then remove from file
				if ($value["time"] > time()-(60 * 60)) {
					$result[$key] = $value;
				}
			}
			file_put_contents("builder.crc32", json_encode($result));
        }
        
        return;
	}
	
	function debug() {
		if ($_GET["module_id"] > 0) {
			$this->resetModule(false, $_GET["module_id"]);
			$param = $_GET;
			$this->run(null, $param); //*/
			file_put_contents("cache/debugger.do", "pause");
			file_put_contents("cache/debugger.state", "");
		}
		
		return $this->_callPrinter("debug", $arr_param);
	}
	
	function flushDBCache() {
		$this->obj_data->obj_mysql->flush();
		die("success");
	}
	
	function flushWebCache($return = false) {
		$dp = opendir("cache/");
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != "." && !is_dir("cache/".$file) && strpos($file, "backup.") !== 0) {
				@unlink("cache/".$file);
			}
		}
		closedir($dp);
		if (!$return || is_array($return)) {
			die("success");
		} else {
			return "success";
		}
	}
	
	function flushCustomModules($return = false) {
		$arr_modules = $this->obj_data->listModulesFromUser($_SESSION['builder']['user_id']);
		foreach ($arr_modules as $mod) {
			$this->resetModule(false, $mod['module_id']);
		}
		if (!$return || is_array($return)) die("success");
		return "success";
	}
		
	function flushLogs() {
		$dp = opendir("log/");
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != "." && strpos($file, ".log") !== false) {
				@unlink("log/".$file);
			}
		}
		closedir($dp);
		die("success");		
	}
	
	function editUser() {
		if ($_POST["user"]) {
			$data = $_POST["user"];
			if (strlen($data['password']) > 0 && $data["password"] == $data["password2"]) {
				$users = file(".users");
				$result = Array();
				foreach ($users as $user) {
					list($name, $password) = explode(":", $user);
					if ($name == $_SESSION['builder']['name']) {
						array_push($result, $name.":".md5($data["password"]));
					} else array_push($result, $user);
				}
				file_put_contents(".users", join("\n", $result));
				die("success");
			}
		} else if ($_POST["users"]) {
			$data = $_POST["users"];
			$users = file(".users");
			$newUsers = Array();
			$groups = Array();
			foreach ($data["name"] as $key=>$user) {
				if (strlen(trim($user)) > 0) {
					if ($data["pass"][$key] == "********") {
						// use old password
						foreach ($users as $usr) {
							list($u, $p) = explode(":", $usr);
							if ($u == $user) {
								$newUsers[] = trim($usr);
								break;
							}
						}
						$groups[$data["group"][$key]][] = $user;
					} else {
						$newUsers[] = $user.":".md5($data["pass"][$key]);
						$groups[$data["group"][$key]][] = $user;
					}
				}
			}
			// finally write file to disc
			file_put_contents(".users", implode("\n", $newUsers));
			$groupLines = Array();
			foreach ($groups as $grp=>$users) {
				$groupLines[] = $grp."=".implode(",", $users);
			}
			file_put_contents(".groups", implode("\n", $groupLines));
			die("success");
		} else if ($_GET["getList"] == "1") {
	        $groups = file(".groups");
	        $users = file(".users");
	        $userGroups = Array();
	        foreach ($groups as $group) {
	        	list($grp, $userList) = explode("=", $group);
	        	$arr_param['groups'][$grp] = $grp;
	        	$userList = explode(",", trim($userList));
	        	foreach ($userList as $usr) {
	        		$userGroups[$usr] = $grp;
	        	}
	        } 
	        $arr_param["groups"]["admin"] = "admin";
	        $arr_param["groups"]["cms"] = "cms"; 
	        $arr_param["groups"]["devel"] = "devel";
	        foreach ($users as $user) {
	        	list($usr, $pwd) = explode(":", $user);
	        	$arr_param['users'][] = Array("name" => $usr, "group" => $userGroups[$usr]);
	        }
	        
	        return $this->_callPrinter("editUser", $arr_param);
		}
		die("error");
	}
	
	function showLog() {
		if ($_GET["log"]) {
			$fp = fopen("log/".str_replace("../", "", $_GET["log"]), "r");
			$file = Array();
			while ($fp && !feof($fp)) {
				array_unshift($file, fgets($fp, 100000));
				$lines++;
				if ($lines > 1000) {
					array_pop($file);
					$lines--;
				}
			}
			fclose($fp);
			$arr_param["file"] = $file;
			return $this->_callPrinter("showLog", $arr_param);
		}
		die("error");
	}
	
	function updateLogs() {
        $dp = opendir("log/");
        $arr_param["logs"] = Array();
        while (($file = readdir($dp)) !== false) {
        	if ($file[0] != "." && strpos($file, ".log") !== false) {
        		array_push($arr_param["logs"], $file);
        	}
        }
        closedir($dp);
		die(json_encode(Array("data" => $arr_param['logs'])));
	}
	
	function fileBrowser() {
		
		$base = "static/images/";
		
		if ($_GET["path"]) {
			$rb = realpath($base);
			$_GET["path"] = str_replace($rb."/", "", realpath($base.trim($_GET["path"], "/"))."/");
			$base .= $_GET["path"];
		}
		
		if ($_GET["upload"] == "1") {
			$file = receiveFile($_GET["name"], $base);
		} else if (strlen($_GET["mkdir"]) > 0) {
			$name = preg_replace('/[^a-zA-Z0-9._\-]/', '', $_GET["mkdir"]);
			@mkdir($base.$name, 0755, true);
		} else if (strlen($_GET["delete"]) > 0 && $_GET["delete"] != "..") {
			if (is_file($base.$_GET["delete"])) {
				@unlink($base.$_GET["delete"]);
			} else {
				removeDir($base.$_GET["delete"], true);
			}
		}
		
		$dp = opendir($base);
		$arr_param["base"] = $base;
		if ($_GET["path"]) {
			$arr_param["path"] = trim($_GET["path"], "/")."/";
		}
		$arr_param["files"] = Array();
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != "." || ($file == ".." && $base != "static/images/")) {
				$dim = "-";
				$size = "-";
				$mime = "Folder";
				if (is_file($base.$file)) {
					$mime = mime_content_type($base.$file);
					if (strpos($mime, "image/") !== false) {
						list($width, $height) = getimagesize($base.$file);
						$dim = $width."x".$height; 
					}
					$size = filesize($base.$file);
				}
				$arr_param["files"][] = Array("type" => (is_dir($base.$file) ? "dir" : "file"), "name" => $file, "size" => $size, "mime" => $mime, "lastModified" => filemtime($base.$file), "dimensions" => $dim);
			}
		}
		usort($arr_param["files"], create_function('$a,$b', 'if($a["type"] == "dir" && $b["type"] == "file")return -1;else if ($b["type"] == "dir" && $a["type"] == "file")return 1; return strcmp($a["name"],$b["name"]);'));
		closedir($dp);
		
		return $this->_callPrinter("fileBrowser", $arr_param);
	}
	
	function backup() {
		if ($_GET["save"]) {
			$id = "backupjob";
            if (Quartz::getJob($id)) {
           		Quartz::removeJob(null, false, $id);
           	}
            if (strlen($_POST["backupTime"]) > 0) {
              	Quartz::addJob(json_decode($_POST["backupTime"], true), "builder:backup", $id);
            } 
			
			jumpTo("?event=builder:home");
		}
       	$arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
       	$arr_param["libraries"] = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
       	$arr_param["tags"] = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
       	$arr_param["tables"] = $this->obj_data->listTablesFromUser($_SESSION["builder"]["user_id"]);
       	$arr_param["translations"] = Generator::getInstance()->getLanguage()->listTexts();
       	
       	$toPost = Array("modules" => Array(), "libraries" => Array(), "tags" => Array(), "tables" => Array(), "translations" => Array(), "db" => Array(), "images" => 1);
       	foreach ($arr_param["modules"] as $mod) {
       		array_push($toPost["modules"], $mod["module_id"]);
       	}
       	foreach ($arr_param["libraries"] as $mod) {
       		array_push($toPost["libraries"], $mod["library_id"]);
       	}
       	foreach ($arr_param["tags"] as $mod) {
       		array_push($toPost["tags"], $mod["tag_id"]);
       	}
       	foreach ($arr_param["tables"] as $mod) {
       		array_push($toPost["tables"], $mod["table_id"]);
       	}
       	foreach ($arr_param["translations"] as $root => $mod) {
       		array_push($toPost["translations"], $root);
       	}
       	$_POST = $toPost;
       	if (!is_dir("static/backups")) {
       		@mkdir("static/backups", 0755);
       	}
       	return $this->export("static/backups/".PROJECT_NAME."-".date("Ymd-Hi").".prails");
	}
	
	function restore() {
       	if (!is_dir("static/backups")) {
       		@mkdir("static/backups", 0755);
       	}
       	
       	if (strlen($_POST["file"]) > 0) {
       		if (file_exists("static/backups/".$_POST["file"]) && dirname(realpath("static/backups/".$_POST["file"])) == realpath("static/backups")) {
       			$this->import("static/backups/".$_POST["file"], $_POST["dataRestore"] == "1");
       			die("success");
       		} else {
       			die("file not found");
       		}
       	} else {
       		$list = "";
       		$dp = opendir("static/backups");
       		while (($file = readdir($dp)) !== false) {
       			if ($file[0] != ".") {
       				$list .= $file."\n";
       			}
       		}
       		closedir($dp);
       		die($list);
       	}
	}
	
	function replication() {
		if ($_POST["get"] == "details") {
			$credentials = $_POST["replicate"];
			$ctx = stream_context_create(Array(
				'http' => Array(
					"method" => "GET",
					"header" => "Authorization: Basic ".base64_encode($credentials["source_user"].":".$credentials["source_pass"])
				)
			)); 
			$return = file_get_contents(trim($credentials["source"],'/')."/?event=builder:replication&get=detailsjson", false, $ctx);
			if (strlen(trim($return)) == 0) header("HTTP/1.1 404 Not Found");
			die($return);
		} else if ($_GET["get"] == "detailsjson") {
        	$arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
        	$arr_param["libraries"] = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
        	$arr_param["tags"] = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
        	$arr_param["tables"] = $this->obj_data->listTablesFromUser($_SESSION["builder"]["user_id"]);
        	$arr_translations = Generator::getInstance()->getLanguage()->listTexts();
        	$arr_result = Array();
        	foreach ($arr_param as $key => $value) {
        		$arr_result[$key] = Array();
        		foreach ($value as $entry) {
        			if (gettype($entry) != "array") {
        				array_push($arr_result[$key], $entry->getArrayCopy());
        			} else {
        				array_push($arr_result[$key], $entry);
        			}
        		}
        	}
        	foreach ($arr_translations as $key => $value) {
        		$arr_result["translations"][$key] = $value;
        	}
        	die(json_encode($arr_result));
		} else if (isset($_POST["start"])) {
			$credentials = $_POST["replicate"];
			$ctx = stream_context_create(Array(
				'http' => Array(
					"method" => "POST",
					"header" => "Authorization: Basic ".base64_encode($credentials["source_user"].":".$credentials["source_pass"]),
					"content" => http_build_query($_POST)."&file=replication-data.prails"
				)
			)); 
			$this->import(file_get_contents(trim($credentials["source"],'/')."/?event=builder:export", false, $ctx));
			die("success");
		}
	}
	
	function getStyleDefs() {
		readfile("templates/builder/css/stylepanel.css");
		echo "\n\n";
		readfile("templates/main/css/global.css");
		die();
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