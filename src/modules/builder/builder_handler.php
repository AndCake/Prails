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
			if (ENV_PRODUCTION === true && (!in("builder:", $_GET["event"]) || $_GET["event"] == "builder:createResource")) {
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
					$primaryGroup = "devel";
					foreach ($groups as $group)
					{
						list ($grp, $users) = explode("=", $group);
						$users = explode(",", trim($users));
						if (in($_SERVER["PHP_AUTH_USER"], $users))
						{
							if (in("[primary]", $grp)) {
								$primaryGroup = $grp;
							}
							$u_group = $grp;
							break;
						}
					}
					if (in($_SERVER["PHP_AUTH_USER"].":".md5($_SERVER["PHP_AUTH_PW"].(USER_SALT !== "USER_SALT" ? USER_SALT : "")), $passwd))
					{
						$_SESSION["builder"]["name"] = $_SERVER["PHP_AUTH_USER"];
						$_SESSION["builder"]["user_id"] = crc32($primaryGroup);
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
			@mkdir("static/backups", 0755, true);
		}
		$dp = opendir("static/backups");
		if ($dp) { 
			while (($file = readdir($dp)) !== false) {
				if ($file[0] != ".") {
					array_push($arr_param["backupList"], $file);
				}
			}
			closedir($dp);
		}
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
		if (!function_exists("compileCode")) {
			function compileCode($code) {
				// returns the PHP code
				if (SNOW_MODE === true) {
					$sc = new SnowCompiler($code."\n");
					return $sc->compile();
				} else 
					return $code;
			}
		}
		if (!function_exists("makeDebuggable")) {
			function makeDebuggable($code, $addBreakpoint = false) {
				if (ENV_PRODUCTION === true) return $code;
				if ($addBreakpoint) {
					$debugStart = "Debugger::breakpoint();";
				} else {
					$debugStart = "Debugger::wait(get_defined_vars(), __LINE__);";
				}
	
				$lines = explode("\n", $code);
				foreach ($lines as $i=>$line) {
					if (preg_match("/(\\{\\s*\$)|(;\\s*\$)|(\\}\\s*\$)/i", $line) && 
					    (!preg_match("/(^|\\s+)switch\\s*\\(/i", $line) ||
					    (preg_match("/^\\s*\\{/i", $line) && !preg_match("/(^|\s+)switch\s*\(/i", $lines[$i-1])))
					) {
						$lines[$i] = $line . "Debugger::wait(get_defined_vars(), __LINE__);";
					}
				}
				$code = implode("\n", $lines);
				if (in("//[ACTUAL]", $code)) {
					$code = str_replace("//[ACTUAL]", "//[ACTUAL]\n".$debugStart, $code);
					$last = strrpos($code, "//[END POST-");
					$code = substr($code, 0, $last) . $debugStart . substr($code, $last);
				} else if (in("/*[ACTUAL]*/", $code)) {
					$code = str_replace("/*[ACTUAL]*/", "/*[ACTUAL]*/".$debugStart, $code);
					$last = strrpos($code, "/*[END POST-");
					$code = substr($code, 0, $last) . $debugStart . substr($code, $last);
				} else $code = $debugStart . $code;
				$code = preg_replace('/(\breturn\s+)/m', 'Debugger::wait(get_defined_vars(), __LINE__);\1', $code);
				return str_replace("\r", "", $code);
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
					if (!file_exists($libPath)) @mkdir($libPath, 0755, true);
					if ($arr_lib["fk_resource_id"] > 0) {
						$libPath .= $arr_lib["name"] . (ENV_PRODUCTION === true ? "" : $arr_lib["library_id"]) . "/";
						if (!file_exists($libPath)) {
							@mkdir($libPath, 0755, true);
						}
						$libfile = $libPath.$arr_lib["resource"]["name"];
						file_put_contents($libfile, base64_decode($arr_lib["resource"]["data"]));
						preg_match('/((\.tar\.[a-zA-Z0-9]+)|(\.tgz)|(\.zip))$/mi', $libfile, $match);
						if (strlen($match[1]) > 0) {
							// unpack it to get contents
							if (PHP_OS == "WINNT") {
								if (in($match[1], Array(".tar.gz", ".tar.bz2", ".tgz"))) 
									exec("cd ".dirname($libfile)."; ..\\7za.exe x ".basename($libfile)."; ..\\7za.exe x ".basename(str_replace($match[1], ".tar", $libfile)));
								else 
									exec("cd ".dirname($libfile)."; ..\\7za.exe x ".basename($libfile));
							} else {
								$progs = Array(".tar.bz2" => "tar -xvjf ", ".tar.gz" => "tar -xvzf ", ".tgz" => "tar -xvzf ", ".zip" => "unzip ");
								exec("cd ".dirname($libfile)."; ".$progs[$match[1]].basename($libfile));
							}
						}
					}
					$libname = $libPath . $arr_lib["name"] . (ENV_PRODUCTION === true ? "" : $arr_lib["library_id"]) . ".php";
					if (file_exists($libname)) {
						$libName = $libPath . $arr_lib["name"].(ENV_PRODUCTION === true ? "" : $arr_lib["library_id"])."_loader.php";
					}
					if (!file_exists($libname)) {
						$content = "<"."?php\n".compileCode($arr_lib["code"])."\n?".">";
						file_put_contents($libname, $content);
					}
					$libs .= "include_once('".$libname."');\n";
				}
			}
			$tagPath = "lib/tags/custom/";
			if (!file_exists($tagPath)) {
				@mkdir($tagPath, 0755, true);
			}
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
				@mkdir("templates/".$mod."/html", 0755, true);
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
					$code = $arr_handler["code"];
					$code = compileCode($code);
					$code = preg_replace("/([^a-zA-Z0-9])out\s*\((.*)\)([^a-zA-Z0-9])/", "\$1\$this->_callPrinter(\"".$arr_handler["event"]."\", \$2)\$3", $code);
					$code = preg_replace("/\\\$data->/", "\$this->obj_data->", $code);
					$code = preg_replace("/\\\$gen->/", "\$this->gen->", $code);
					$code = makeDebuggable($code, $bol_invoke["handler"] == $arr_handler["handler_id"]);
					$handler .= "\nfunction ".$arr_handler["event"]."() {\n  global \$SERVER, \$SECURE_SERVER, \$currentLang;\n	\$arguments = func_get_args();\n	\$arr_param = \$arguments[0];\n	\$param = &\$arr_param;\n".$code."\n}\n";
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
					if ($arr_handler["flag_cacheable"] == "1" && ENV_PRODUCTION === true) {
						$printer .= "  Generator::getInstance()->setIsCachable();\n";
					}
					$printer .= "  \$decoration = (strlen(\$decorator)>0 ? invoke(\$decorator, \$arr_param) : \"<!--[content]-->\");\n";
					$printer .= "  \$str_content = Generator::getInstance()->includeTemplate(\"templates/".$mod."/html/".$arr_handler["event"]."\".(strlen(\$template) > 0 && strtolower(\$template) != \"default\" ? \".\".\$template : \"\").\".html\", \$arr_param);\n";
					$printer .= "  \$str_content = str_replace(\"<!--[content]-->\", \$str_content, \$decoration);\n";
					$printer .= "  return \$str_content;\n}\n";

					$handlers[$arr_handler["event"]] = $arr_handler["html_code"];
				}
				if (is_array($arr_data)) foreach ($arr_data as $arr_d) {
					$data .= "\nfunction ".$arr_d["name"]."() {\n\$arguments = func_get_args();\n".makeDebuggable(compileCode($arr_d["code"]), $bol_invoke["data"] == $arr_d["data_id"])."\n}\n";
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
			if (PHP_OS == "WINNT") {
				exec("del 'cache\\handler_".$arr_param['module']['name'].":*'");
				exec("del 'cache\\handler_".$arr_param['module']['name'].$module_id.":*'");
				exec("del 'cache\\handler_".$arr_param['module']['name'].":*'");
			} else {
				exec("rm cache/handler_".$arr_param['module']['name'].":* cache/handler_".strtolower($arr_param['module']['name']).":* cache/handler_".strtolower($arr_param["module"]["name"]).$module_id.":* cache/handler_".$arr_param["module"]["name"].$module_id.":*");
			}
		}

		if ($die) {
			die ("success");
		} else {
			return "success";
		}
	}

	function updateStream($obj) {
		$update = json_decode(@file_get_contents("cache/update-stream"), true);
		$now = time();
		if (is_numeric($update)) $update = Array();

		$val = Array("time" => $now, "id" => $obj['id']);
		if (!empty($obj["module"])) {
			$mid = $obj["module"];
			if (!$update["module"]) $update["module"] = Array();
			if (!$update["module"][$mid]) 
				$update["module"][$mid] = Array("time" => 0, "handler" => Array(), "data" => Array(), "resource" => Array());
			$update["module"][$mid]["time"] = $now;
			if (count($obj) == 2) {
				$update["module"][$mid]["id"] = $obj['id'];
			}
			if (!empty($obj["handler"])) $update["module"][$mid]["handler"][$obj["handler"]] = $val;
			if (!empty($obj["data"])) $update["module"][$mid]["data"][$obj["data"]] = $val;
			if (!empty($obj["library"])) $update["module"][$mid]["library"][$obj['library']] = $val;
			if (!empty($obj["tag"])) $update["module"][$mid]["tag"][$obj["tag"]] = $val;
			if (!empty($obj["resource"])) $update["module"][$mid]["resource"][$obj["resource"]] = $val;
		} else if (!empty($obj["library"])) {
			$update["library"][$obj["library"]] = $val;
		} else if (!empty($obj["tag"])) {
			$update["tag"][$obj["tag"]] = $val;
		}
		@file_put_contents("cache/update-stream", json_encode($update));
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
		if (!isset($_GET['module_id']) && isset($_GET['module'])) {
			$arr_param['module'] = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_GET['module']);
		} else {
			$arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
		}
		if ($arr_param['module']) {
			removeDir("modules/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
			removeDir("modules/".strtolower($arr_param["module"]["name"]), true);
			removeDir("templates/".strtolower($arr_param["module"]["name"]).$arr_param["module"]["module_id"], true);
			removeDir("templates/".strtolower($arr_param["module"]["name"]), true);
		}
		
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
			if (!isset($_GET['module_id']) && !empty($_POST['module']['name'])) {
				$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_POST['module']['name']);
				if ($module) {
					$_GET['module_id'] = $module['module_id'];
				} else {
					$_GET['module_id'] = 0;
				}
			}
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
					if (isset($_POST['recursive'])) {
						$arr_data['header_info'] = @serialize(array_merge_recursive_distinct($headerInfo, $arr_data['header_info']));
					} else {
						$arr_data["header_info"] = @serialize(array_merge($headerInfo, $arr_data["header_info"]));
					}
				}

				$this->updateStream(Array("module" => $arr_param['module']['name'], "id" => $_GET['module_id']));
				$this->resetModule(false, $_GET["module_id"]);
				$this->obj_data->updateModule($_GET["module_id"], $arr_data);
			} else if ((int)$_GET["module_id"] == 0)
			{
				$_SESSION["module_id"] = $_GET["module_id"] = $this->obj_data->insertModule($arr_data);
				$arr_module = $arr_data;
				// also generate an nearly-empty home entry point
				ob_start();
				require ("templates/builder/".(SNOW_MODE === true ? 'snow' : 'php')."/handler_scaffold_home.php");
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
				$this->updateStream(Array("module" => $arr_module["name"], "handler" => "home", "id" => $hid));
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

			die ("success");
		} else if ($_GET["check"] == "2")
		{
			$arr_data = $_POST;
			$arr_data["oldDB"] = ($arr_data["oldDB"]);
			$arr_data["newUser"] = ($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectModule($_GET["module_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
		} else if ($_GET["check"] == "tag") {
			setcookie("builder-lastVersionTag", $_POST['tag']['name'], time() + 30 * 60 * 60 * 24, "/");
			$this->obj_data->setVersionTag("module", $_GET["module_id"], $_POST["tag"]["name"]);
			die("success");
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
			$arr_param['lastVersion'] = $this->obj_data->selectLatestVersion("module", $_GET["module_id"]);
		}
		
		if ($_GET["refresh"]) {
			die(json_encode(Array("code"=>$arr_param["module"][$_GET["refresh"]])));
		}

		die ($this->_callPrinter("editModule", $arr_param));
	}

	function moduleHistory()
	{
		$_SESSION["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);
		$arr_param["history"] = $this->obj_data->listModuleHistory($_SESSION["module_id"]);

		// need to merge it...
		foreach ($arr_param['history'] as $key => &$history) {
			$i = $key;
			while (strlen($history["js_code"]) <= 0 && $i > 0) {
				$history["js_code"] = $arr_param['history'][--$i]["js_code"];
			}
			$i = $key;
			while (strlen($history["style_code"]) <= 0 && $i > 0) {
				$history["style_code"] = $arr_param['history'][--$i]["style_code"];
			}
			$i = $key;
			while (strlen($history["name"]) <= 0 && $i > 0) {
				$history["name"] = $arr_param['history'][--$i]["name"];
			}
		}
		
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
		if (!isset($_GET['handler_id']) && !empty($_POST['module']['name']) && !empty($_POST['handler']['event'])) {
			$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_POST['module']['name']);
			$mid = $module['module_id'];
			$handler = $this->obj_data->selectHandlerByNameAndModule($mid, $_POST['handler']['event']);
			$_GET['handler_id'] = (int)$handler['handler_id'];
		}
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
			$mod = $this->obj_data->selectModule($_SESSION["module_id"]);
			
			$this->updateCRCFile(Array("codeh".$_GET["handler_id"], "html_codeh".$_GET["handler_id"]));
			
			if ($_GET["handler_id"] > 0)
			{
				$arr_param["handler"] = $this->obj_data->selectHandler($_GET["handler_id"]);
				$this->obj_data->updateHandler($_GET["handler_id"], $arr_data);
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

			$this->updateStream(Array("module" => $mod['name'], "handler" => $arr_data['event'], "id" => $_GET['handler_id']));
			return $this->resetModule();
		} else if ($_GET["check"] == "2")
		{
			$arr_data = $_POST;
			$arr_data["oldDB"] = ($arr_data["oldDB"]);
			$arr_data["newUser"] = ($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectHandler($_GET["handler_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
		} else if ($_GET["check"] == "tag") {
			setcookie("builder-lastVersionTag", $_POST['tag']['name'], time() + 30 * 60 * 60 * 24, "/");
			$this->obj_data->setVersionTag("handler", $_GET["handler_id"], $_POST["tag"]["name"]);
			die("success");
		} else if ($_GET['check'] == "3") {
			if (!empty($_POST['handler']['event']) && !empty($_POST['module']['name'])) {
				$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_POST['module']['name']);
				if (!$module) {
					$module = Array("name" => $_POST['module']['name'], "fk_user_id" => $_SESSION['builder']['user_id']);
					$module["module_id"] = $this->obj_data->insertModule($module);
				}
				$mid = $module["module_id"];
				$handler = $this->obj_data->selectHandlerByNameAndModule($mid, $_POST['handler']['event']);
				$_SESSION['module_id'] = $_GET['module_id'] = $mid;
				$arr_data = Array();
				if (!$handler) $hid = 0; else $hid = $handler['handler_id'];
				if ($_POST['html_code']) {
					if (is_array($_POST['html_code'])) {
						$key = array_pop(array_keys($_POST['html_code']));
					} else $key = "";
					$code = $handler['html_code'];
					preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
					$lastPos = 0;
					$codes = Array();
					array_push($codes, Array("title" => "Default", "id" => "html_".$arr_param["handler"]["handler_id"]));
					$added = false;
					if (is_array($matches) && is_array($matches[1])) {         
						foreach ($matches[1] as $match) {
							$cd = Array(
								"title" => $match[0],
								"id" => "html_".$match[0].$arr_param["handler"]["handler_id"] 
							);
							if ($key == $cd["title"]) {
								$cd["content"] = $_POST['html_code'][$key];
								$added = true;
							} else {
								$start = $match[1] + strlen("".$match[0].">\n");
								$end = strpos($code, "</part-".$match[0].">\n", $match[1]);
								$cd["content"] = substr($code, $start, $end - $start);
							}
							$lastPos = strpos($code, "</part-".$match[0].">\n", $match[1]) + strlen("</part-".$match[0].">\n");
							array_push($codes, $cd);
						}
					}
					if ($key == "") {
						$codes[0]["content"] = $_POST['html_code'];
					} else {
						if (!$added) {
							array_push($codes, Array("title" => $key, "content" => $_POST['html_code'][$key]));
						}
						$codes[0]["content"] = substr($code, $lastPos);
					}
					// re-build it and store it into the handler field
					$html = "";
					for ($i = 1; $i < count($codes); $i++) {
						$html .= "<part-".$codes[$i]["title"].">\n" . $codes[$i]["content"] . "</part-".$codes[$i]["title"].">\n";
					}
					$html .= $codes[0]["content"];
					$arr_data["html_code"] = $html;
				} else if ($_POST['code']) {
					if (is_array($_POST['code'])) {
						$key = array_pop(array_keys($_POST['code']));
					} else $key = "";
					$code = $handler["code"];
					if (SNOW_MODE === true) {
						preg_match_all('/##\[BEGIN POST-([^\]]+)\]/mi', $code, $matches, PREG_OFFSET_CAPTURE);
						$lastPos = 0;
						$codes = Array();
						array_push($codes, Array("title" => "Default", "id" => "hcode_".$arr_param["handler"]["handler_id"]));
						$added = false;
						if (is_array($matches) && is_array($matches[1])) {         
							foreach ($matches[1] as $match) {
								$cd = Array(
									"title" => $match[0],
									"id" => "code_".$match[0].$arr_param["handler"]["handler_id"] 
								);
								if ($cd["title"] == $key) {
									$cd["content"] = $_POST['code'][$key];
									$added = true;
								} else {
									$start = strpos($code, "##[ACTUAL]", $match[1]) + strlen("##[ACTUAL]\n\t");
									$end = strpos($code, "##[END ACTUAL]", $start);
									$cd["content"] = implode("\n", explode("\n\t", ltrim(substr($code, $start, $end - $start))));
								}
								$lastPos = strpos($code, "##[END POST-".$match[0]."]", $match[1]) + strlen("##[END POST-".$match[0]."]\n");
								array_push($codes, $cd);
							}
						}
					} else {
						preg_match_all('/\/\*\[BEGIN POST-([^\]]+)\]\*\//mi', $code, $matches, PREG_OFFSET_CAPTURE);
						$lastPos = 0;
						$codes = Array();
						array_push($codes, Array("title" => "Default", "id" => "hcode_".$arr_param["handler"]["handler_id"]));
						$added = false;
						if (is_array($matches) && is_array($matches[1])) {         
							foreach ($matches[1] as $match) {
								$cd = Array(
									"title" => $match[0],
									"id" => "code_".$match[0].$arr_param["handler"]["handler_id"] 
								);
								if ($cd["title"] == $key) {
									$cd["content"] = $_POST['code'][$key];
									$added = true;
								} else {
									$start = strpos($code, "/*[ACTUAL]*/", $match[1]) + strlen("/*[ACTUAL]*/") + 1;
									$end = strpos($code, "/*[END ACTUAL]*/", $start);
									$cd["content"] = substr($code, $start, $end - $start);
								}
								$lastPos = strpos($code, "/*[END POST-".$match[0]."]*/\n", $match[1]) + strlen("/*[END POST-".$match[0]."]*/\n");
								array_push($codes, $cd);
							}
						}
					}
					if ($key == "") {
						$codes[0]["content"] = $_POST['code'];
					} else {
						if (!$added) {
							array_push($codes, Array("title" => $key, "content" => $_POST['code'][$key]));
						}
						$codes[0]["content"] = ltrim(substr($code, $lastPos));
					}
					// re-build it and store it into the handler field
					$code = "";
					for ($i = 1; $i < count($codes); $i++) {
						if (SNOW_MODE === true) {
							$codes[$i]["content"] = implode("\n\t", explode("\n", $codes[$i]["content"]));
							$code .= "##[BEGIN POST-".$codes[$i]["title"]."]\nif _POST[\"".$codes[$i]["title"]."\"]?\n\t##[ACTUAL]\n\t".$codes[$i]["content"]."\n\t##[END ACTUAL]\n\tdo session_write_close\n\tdo die\n##[END POST-".$codes[$i]["title"]."]\n";
						} else {
							$code .= "/*[BEGIN POST-".$codes[$i]["title"]."]*/\nif (isset(\$_POST[\"".$codes[$i]["title"]."\"])) { /*[ACTUAL]*/\n".$codes[$i]["content"]."/*[END ACTUAL]*/\nsession_write_close();\ndie();}\n/*[END POST-".$codes[$i]["title"]."]*/\n";
						}
					}
					$code .= $codes[0]["content"];
					$arr_data["code"] = $code;
				}
				$arr_data["event"] = $_POST['handler']['event'];
				if ($hid > 0) {
					$this->obj_data->updateHandler($hid, $arr_data);
				} else {
					$arr_data["fk_module_id"] = $mid;
					$hid = $this->obj_data->insertHandler($arr_data);
				}
				$this->updateStream(Array("module" => $module['name'], "handler" => $_POST['handler']['event'], "id" => $hid));
				return $this->resetModule();
			} else {
				die("Missing parameters");
			}
		}

		$arr_param["configurations"] = Array();
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
			$arr_configs = $this->obj_data->listConfigurationFromModule($_GET["module_id"], "1");            
			$arr_param["decorators"] = $this->obj_data->selectDecoratorEventsFromUser($_SESSION["builder"]["user_id"]);
			$arr_param["configurations"] = Array();
			foreach ($arr_configs as $config) {
				if (!in($config["name"], $arr_param['configurations'])) {
					array_push($arr_param['configurations'], $config["name"]);
				}
			}
			$arr_param['lastVersion'] = $this->obj_data->selectLatestVersion("handler", $_GET["handler_id"]);                    
		}
		
		if ($_GET["handler_id"] <= 0 && $_GET["module_id"] > 0) {
			$arr_param["handler"]["html_code"] = file_get_contents("templates/builder/php/output_empty.php");
			$arr_param["handler"]["code"] = file_get_contents("templates/builder/".(SNOW_MODE === true ? 'snow' : 'php')."/handler_empty.php");
		}
		
		$code = $arr_param['handler']['html_code'];
		preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
		$lastPos = 0;
		$codes = Array();
		array_push($codes, Array("title" => "Default", "id" => "html_".$arr_param["handler"]["handler_id"]));
		if (is_array($matches) && is_array($matches[1])) {         
			foreach ($matches[1] as $match) {
				$cd = Array(
					"title" => $match[0],
					"id" => "html_".$match[0].$arr_param["handler"]["handler_id"] 
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
		$lastPos = 0;
		$codes = Array();
		array_push($codes, Array("title" => "Default", "id" => "hcode_".$arr_param["handler"]["handler_id"]));
		if (SNOW_MODE === true) {
			preg_match_all('/##\[BEGIN POST-([^\]]+)\]/mi', $code, $matches, PREG_OFFSET_CAPTURE);
			if (is_array($matches) && is_array($matches[1])) {         
				foreach ($matches[1] as $match) {
					$cd = Array(
						"title" => $match[0],
						"id" => "code_".$match[0].$arr_param["handler"]["handler_id"] 
					);
					$start = strpos($code, "##[ACTUAL]", $match[1]) + strlen("##[ACTUAL]\n\t");
					$end = strpos($code, "##[END ACTUAL]", $start);
					$cd["content"] = join("\n", explode("\n\t", ltrim(substr($code, $start, $end - $start))));
					$lastPos = strpos($code, "##[END POST-".$match[0]."]", $match[1]) + strlen("##[END POST-".$match[0]."]\n");
					array_push($codes, $cd);
				}
			}
		} else {
			preg_match_all('/\/\*\[BEGIN POST-([^\]]+)\]\*\//mi', $code, $matches, PREG_OFFSET_CAPTURE);
			if (is_array($matches) && is_array($matches[1])) {         
				foreach ($matches[1] as $match) {
					$cd = Array(
						"title" => $match[0],
						"id" => "code_".$match[0].$arr_param["handler"]["handler_id"] 
					);
					$start = strpos($code, "/*[ACTUAL]*/", $match[1]) + strlen("/*[ACTUAL]*/") + 1;
					$end = strpos($code, "/*[END ACTUAL]*/", $start);
					$cd["content"] = substr($code, $start, $end - $start);
					$lastPos = strpos($code, "/*[END POST-".$match[0]."]*/\n", $match[1]) + strlen("/*[END POST-".$match[0]."]*/\n");
					array_push($codes, $cd);
				}
			}
		}
		$codes[0]["content"] = ltrim(substr($code, $lastPos));
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
		
		// need to merge it...
		foreach ($arr_param['history'] as $key => &$history) {
			$i = $key;
			while (strlen($history["code"]) <= 0 && $i > 0) {
				$history["code"] = $arr_param['history'][--$i]["code"];
			}
			$i = $key;
			while (strlen($history["html_code"]) <= 0 && $i > 0) {
				$history["html_code"] = $arr_param['history'][--$i]["html_code"];
			}
			$i = $key;
			while (strlen($history["event"]) <= 0 && $i > 0) {
				$history["event"] = $arr_param['history'][--$i]["event"];
			}
		}
		
		die ($this->_callPrinter("handlerHistory", $arr_param));
	}

	function listDatas()
	{
		$arr_param["datas"] = $this->obj_data->listDatas($_SESSION["builder"]["user_id"]);

		return $this->_callPrinter("listDatas", $arr_param);
	}

	function deleteData()
	{
		if (!isset($_GET['data_id']) && !empty($_POST['data']['name']) && !empty($_POST['module']['name'])) {
			$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_POST['module']['name']);
			if ($module) {
				$mid = $module['module_id'];
				$_GET['module_id'] = $mid;
				$data = $this->obj_data->getDataFromName($_POST['data']['name'], $mid);
				if ($data) {
					$_GET['data_id'] = $data['data_id'];
				} else {
					die("Query not found!");
				}
			} else {
				die("Module not found!");
			}
		}			
		$this->obj_data->deleteData($_GET["data_id"]);
		die ("success");
	}

	function editData()
	{
		$_SESSION["data_id"] = $_GET["data_id"] = if_set($_GET["data_id"], $_SESSION["data_id"]);
		$_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

		if ($_GET["check"] == "1")
		{
			if (!isset($_GET['data_id']) && !empty($_POST['data']['name']) && !empty($_POST['module']['name'])) {
				$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_POST['module']['name']);
				if ($module) {
					$mid = $module['module_id'];
					$_GET['module_id'] = $_SESSION['module_id'] = $mid;
					$data = $this->obj_data->getDataFromName($_POST['data']['name'], $mid);
					if ($data) {
						$_GET['data_id'] = $data['data_id'];
					} else {
						$_GET['data_id'] = 0;
					}
				} else {
					// module not found!
					$_SESSION["module_id"] = $_GET["module_id"] = $this->obj_data->insertModule(Array("name" => $_POST['module']['name'], "fk_user_id" => $_SESSION['builder']['user_id']));
					$_GET['data_id'] = 0;
				}
			}			
			$arr_data = $_POST["data"];
			$arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
			$arr_data["fk_module_id"] = $_GET["module_id"];
			$arr_data["code"] = ($arr_data["code"]);
			$mod = $this->obj_data->selectModule($_GET["module_id"]);
			
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
			
			$this->updateStream(Array("module" => $mod['name'], "data" => $arr_data['name'], "id" => $_GET['data_id']));
			return $this->resetModule();
		} else if ($_GET["check"] == "2")
		{
			$arr_data = $_POST;
			$arr_data["oldDB"] = ($arr_data["oldDB"]);
			$arr_data["newUser"] = ($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectData($_GET["data_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
		} else if ($_GET["check"] == "tag") {
			setcookie("builder-lastVersionTag", $_POST['tag']['name'], time() + 30 * 60 * 60 * 24, "/");
			$this->obj_data->setVersionTag("data", $_GET["data_id"], $_POST["tag"]["name"]);
			die("success");
		}

		$arr_param["data"] = $this->obj_data->selectData($_GET["data_id"]);
		$arr_param['lastVersion'] = $this->obj_data->selectLatestVersion("data", $_GET["data_id"]);
		
		if ($_GET["refresh"]) {
			die(json_encode(Array("code"=>$arr_param["data"][$_GET["refresh"]])));
		}

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
		if (!isset($_GET['library_id']) && !empty($_POST['library']['name'])) {
			$library = $this->obj_data->selectLibraryByUserAndName($_SESSION['builder']['user_id'], $_POST['library']['name']);
			$_GET['library_id'] = (int)$library['library_id'];
		}
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
		$this->updateStream(Array("library" => $lib['name'], "id" => $_GET['library_id']));
		die ("success");
	}

	function editLibrary()
	{
		$_SESSION["library_id"] = $_GET["library_id"] = if_set($_GET["library_id"], $_SESSION["library_id"]);
		$_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

		if ($_GET["check"] == "1")
		{
			if (!isset($_GET['library_id']) && !empty($_POST['library']['name'])) {
				$library = $this->obj_data->selectLibraryByUserAndName($_SESSION['builder']['user_id'], $_POST['library']['name']);
				if ($library) {
					$_GET['library_id'] = $library['library_id'];
				} else {
					$_GET['library_id'] = 0;
				}
			}
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

			$this->updateStream(Array("library" => $arr_library['name'], "id" => $_GET['library_id']));
			return $this->resetModule();
		} else if ($_GET["check"] == "2")
		{
			$arr_data = $_POST;
			$arr_data["oldDB"] = ($arr_data["oldDB"]);
			$arr_data["newUser"] = ($arr_data["newUser"]);
			$arr_data["newDB"] = $this->obj_data->selectLibrary($_GET["library_id"]);
			echo $arr_data["newDB"][$_GET["type"]]."\n6c7f3ed76b9e883ec951f60dedb25491\n";
			die ($this->_mergeContent($arr_data["oldDB"], $arr_data["newDB"][$_GET["type"]], $arr_data["newUser"]));
		} else if ($_GET["check"] == "tag") {
			setcookie("builder-lastVersionTag", $_POST['tag']['name'], time() + 30 * 60 * 60 * 24, "/");
			$this->obj_data->setVersionTag("library", $_GET["library_id"], $_POST["tag"]["name"]);
			die("success");
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
				if (PHP_OS == "WINNT") {
					if (in($match[1], Array(".tar.gz", ".tar.bz2", ".tgz"))) 
						exec("cd ".dirname($file)."; ..\\7za.exe x ".basename($file)."; ..\\7za.exe x ".basename(str_replace($match[1], ".tar", $file)));
					else 
						exec("cd ".dirname($file)."; ..\\7za.exe x ".basename($file));
				} else {
					$progs = Array(".tar.bz2" => "tar -xvjf ", ".tar.gz" => "tar -xvzf ", ".tgz" => "tar -xvzf ", ".zip" => "unzip ");
					exec("cd ".dirname($file)."; ".$progs[$match[1]].basename($file));
				}
				function getTree($root, $exclude = Array()) {
					$tree = Array();
					$dp = opendir($root);
					while (($na = readdir($dp)) !== false) {
						if ($na[0] != "." && !in($root.'/'.$na, $exclude)) {
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
			require("templates/builder/".(SNOW_MODE === true ? 'snow' : 'php')."/library_upload_scaffold.php");
			$arr_param["library"]["code"] = ob_get_clean();
			$_SESSION["library_id"] = $_GET["library_id"] = $this->obj_data->insertLibrary($arr_param["library"]);
			$this->updateStream(Array("library" => $libName, "id" => $_GET['library_id']));
		}

		$arr_param["library"] = $this->obj_data->selectLibrary($_GET["library_id"]);
		$arr_param['lastVersion'] = $this->obj_data->selectLatestVersion("library", $_GET["module_id"]);        

		if ($_GET["refresh"]) {
			die(json_encode(Array("code"=>$arr_param["library"][$_GET["refresh"]])));
		}

		die ($this->_callPrinter("editLibrary", $arr_param));
	}

	function libraryHistory()
	{
		$_SESSION["library_id"] = if_set($_GET["library_id"], $_SESSION["library_id"]);
		$arr_param["history"] = $this->obj_data->listLibraryHistory($_SESSION["library_id"]);

		die ($this->_callPrinter("libraryHistory", $arr_param));
	}

	function deleteTag()
	{
		if (!isset($_GET['tag_id']) && !empty($_POST['tag']['name'])) {
			$tag = $this->obj_data->selectTagByUserAndName($_SESSION['builder']['user_id'], $_POST['tag']['name']);
			$_GET['tag_id'] = (int)$tag['tag_id'];
		}
		$this->obj_data->deleteTag($_GET["tag_id"]);
		$this->updateStream(Array("tag" => if_set($_POST['tag']['name'], $tag['name']), "id" => $_GET['tag_id']));		
		die ("success");
	}

	function editTag()
	{
		$_SESSION["tag_id"] = $_GET["tag_id"] = if_set($_GET["tag_id"], $_SESSION["tag_id"]);
		$_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

		if ($_GET["check"] == "1") {
			if (!isset($_GET['tag_id']) && strlen($_POST['tag']['name']) > 0) {
				$tag = $this->obj_data->selectTagByUserAndName($_SESSION['builder']['user_id'], $_POST['tag']['name']);
				if ($tag) {
					$_GET['tag_id'] = $tag['tag_id'];
				} else {
					$_GET['tag_id'] = 0;
				}
			}
			$this->updateCRCFile(Array("codet".$_GET["tag_id"]));
			
			$arr_tag = $_POST["tag"];

			if ($_GET["tag_id"] > 0) {
				$arr_param["tag"] = $this->obj_data->selectTag($_GET["tag_id"]);
				$arr_param["tag"]["html_code"] = ($arr_param["tag"]["html_code"]);
				$this->obj_data->updateTag($_GET["tag_id"], $arr_tag);
			} else {
				$arr_tag["fk_user_id"] = $_SESSION["builder"]["user_id"];
				$arr_tag["fk_module_id"] = $_GET["module_id"];
				$_SESSION["tag_id"] = $_GET["tag_id"] = $this->obj_data->insertTag($arr_tag);
			}
			$this->obj_data->insertTagHistory($_GET["tag_id"], $arr_param["tag"], $arr_tag);
			echo $_GET["tag_id"]."\n";

			$this->updateStream(Array("tag" => $arr_tag['name'], "id" => $_GET['tag_id']));
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
				if (!isset($_GET['resource_id']) && !empty($_GET['file'])) {
					$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_GET['module']);
					if (!$module) {
						$module = Array("name" => $_POST['module']['name'], "fk_user_id" => $_SESSION['builder']['user_id']);
						$module["module_id"] = $this->obj_data->insertModule($module);
					}
					$_GET['module_id'] = $module['module_id'];
					$res = $this->obj_data->selectResourceByName($module['module_id'], $_GET['file']);
					$_GET['resource_id'] = $_SESSION['resource_id'] = (int)$res['resource_id'];
					$type = getMIMEType($_GET['file']);
				}
				$content = file_get_contents($file);
				$mod = $this->obj_data->selectModule($_GET['module_id']);
				$arr_data["fk_module_id"] = $_GET["module_id"];
				$arr_data["type"] = if_set($type, $_FILES["resource"]["type"]["file"]);
				$arr_data["data"] = base64_encode($content);
				if ($_GET["resource_id"] > 0)
				{
					$this->obj_data->updateResource($_GET["resource_id"], $arr_data);
				} else
				{
					$arr_data["name"] = if_set($_FILES["resource"]["name"]["file"], $_GET['file']);
					$_SESSION["resource_id"] = $_GET['resource_id'] = $this->obj_data->insertResource($arr_data);
				}
				$this->updateStream(Array("module" => $mod['name'], "resource" => $arr_data['name'], "id" => $_GET['resource_id']));
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
			$mod = $this->obj_data->selectModule($_GET['module_id']);
			$this->updateStream(Array("module" => $mod['name'], "resource" => $arr_data['name'], "id" => $_GET['resource_id']));
			return $this->resetModule();
		} else if ($_GET["check"] == "upload") {
			$file = receiveFile($_GET["name"], ($_GET["module_id"] < 0 ? "templates/main/resources/" : "static/"));
			if ($_GET["module_id"] > 0) {
				$mod = $this->obj_data->selectModule($_GET['module_id']);
				$content = file_get_contents($file);
				$mime = mime_content_type($file);
				@unlink($file);
				$arr_data["fk_module_id"] = $_GET["module_id"];
				$arr_data["type"] = $mime;
				$arr_data["data"] = base64_encode($content);
				if ($_GET["resource_id"] > 0) {
					$this->obj_data->updateResource($_GET["resource_id"], $arr_data);
					$arr_data = $this->obj_data->selectResource($_GET['resource_id']);
				} else {
					$arr_data["name"] = basename($file);
					$_SESSION["resource_id"] = $_GET['resource_id'] = $this->obj_data->insertResource($arr_data);
				}
				$this->updateStream(Array("module" => $mod['name'], "resource" => $arr_data['name'], "id" => $_GET['resource_id']));
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
				$this->updateStream(Array("module" => $mod, "resource" => $arr_param["resource"]["name"], "id" => $_GET['resource_id']));
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
			$this->obj_data->query("DROP TABLE IF EXISTS ".$arr_table["name"]);
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
					@ require ("templates/builder/".(SNOW_MODE === true ? 'snow' : 'php')."/data_scaffold_".$data.".php");
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
					@require ("templates/builder/".(SNOW_MODE === true ? 'snow' : 'php')."/handler_scaffold_".$handler.".php");
					$code = ob_get_clean();
					ob_start();
					@require ("templates/builder/php/handler_scaffold_".$handler."_html.php");
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
				$mod = $this->obj_data->selectModule($_POST['scaffold']['fk_module_id']);
				$this->updateStream(Array("module" => $mod['name'], "id" => $_POST['scaffold']['fk_module_id']));
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
			$this->obj_data->sql->flush();            
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
		$mod = $this->obj_data->selectModule($_GET['module_id']);
		$this->updateStream(Array("module" => $mod['name'], "id" => $_GET['module_id']));
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
				$arr_settings = getConfiguration();
				updateConfiguration($arr_configuration);
				if ($arr_settings["ENV_PRODUCTION"] === true) {
					foreach ($arr_configuration as $conf) {
						if ($conf["name"] === "ENV_PRODUCTION") {
							if ($conf["value"] === "false") {
								$this->flushCustomModules(true);
							}
							break;
						}
					}
				}
			} else
			{
				$this->obj_data->clearConfiguration($_GET["module_id"], (int)$_GET["type"]);
				foreach ($arr_configuration as $arr_conf)
				{
					$arr_conf["fk_module_id"] = $_GET["module_id"];
					$this->obj_data->insertConfiguration($arr_conf);
				}
				$mod = $this->obj_data->selectModule($_GET['module_id']);
				$this->updateStream(Array("module" => $mod['name'], "id" => $_GET['module_id']));
			}
			
			return $this->resetModule($_GET["die"] == "no", $_GET["module_id"]);
		} else if ($_GET['check'] == "2" && $_GET['module']) {
			$module = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $_GET['module']);
			if ($module) {
				$_GET['module_id'] = $module['module_id'];
				$this->obj_data->clearConfiguration($_GET["module_id"], 0);
				$this->obj_data->clearConfiguration($_GET["module_id"], 1);
				foreach ($_POST['config']['name'] as $key => $conf) {
					$arr_conf["name"] = $conf;
					$arr_conf["value"] = $_POST['config']['value'][$key];
					$arr_conf['flag_public'] = $_POST['config']['flag_public'][$key];
					$arr_conf["fk_module_id"] = $_GET["module_id"];
					$this->obj_data->insertConfiguration($arr_conf);
				}
				$this->updateStream(Array("module" => $_GET['module'], "id" => $_GET["module_id"]));
				die("success");
			}
			die("Module not found");
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
			$this->obj_data->prefix = "tbl_";
			if (strtoupper(substr($query, 0, 7)) == "SELECT ") {
				$query .= " LIMIT [offset], [limit]";
				$arr_param["totals"] = $this->obj_data->query("SELECT COUNT(*) AS total FROM (".str_replace(" LIMIT [offset], [limit]", "", $query).") AS a WHERE 1=1");
			}
			$arr_param["result"] = $this->obj_data->query(str_replace(Array('[offset]', '[limit]'), Array(0, 1), $query));
			$this->obj_data->prefix = "tbl_prailsbase_";
			$result = Array();
			$arr_param["error"] = $this->obj_data->sql->lastError;
			
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
			$loop = 0;
			do {
				$query = if_set($_SESSION["builder"]["currentQuery"], base64_decode($_GET["q"]));
				if (!empty($_POST['query'])) {
					 $searches = " ".$_POST['query']." ";
					 $query = str_replace(" 1=1 ", " (".if_set(trim($searches), "1=1").") ", $query);
				}
				if ($loop == 0 && isset($_POST["sort"])) {
					$query = str_replace(" LIMIT [offset], [limit]", " ORDER BY ".$_POST["sort"]." ".$_POST["dir"]." LIMIT [offset], [limit]", $query);
				}
				$this->obj_data->prefix = "tbl_";
				if (strtoupper(substr($query, 0, 7)) == "SELECT ") {
					$arr_param["result"] = $this->obj_data->query(str_replace(Array('[offset]', '[limit]'), Array(if_set($_POST["start"], 0), if_set($_POST["limit"], 25)), $query));
				}
				$this->obj_data->prefix = "tbl_prailsbase_";
				$arr_param["error"] = $this->obj_data->sql->lastError;
				$loop++;
			} while (!empty($arr_param['error']) && isset($_POST['sort']) && $loop < 2);					

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
			
			$arr_param["result"] = $this->obj_data->query($query);
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
			$arr_rules = Array();	
			array_push($arr_rules, $_POST["rule"]);
			$this->obj_data->updateUrlRules($arr_rules);
			
			die("success");
		}
		
		$arr_param["rules"] = $this->obj_data->listUrlRules();
		$arr_rule = null;	
		foreach ($arr_param["rules"] as $keys=>$rule) {
			if (in("event=".$arr_param["handler"]["module"]["name"].":".$arr_param["handler"]["event"], $rule)) {
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
			$arr_rules = $this->obj_data->listUrlRules();
			foreach ($_POST["modules"] as $mod) {
				$arr_module = $this->obj_data->selectModule($mod)->getArrayCopy();
				$arr_module["handlers"] = $this->obj_data->listHandlers($mod);
				$arr_module["datas"] = $this->obj_data->listDatas($mod);
				$arr_module["configsDevel"] = $this->obj_data->listConfigurationFromModule($mod, 1);
				$arr_module["configsProd"] = $this->obj_data->listConfigurationFromModule($mod, 2);
				$arr_module["resources"] = $this->obj_data->listResources($mod);
				$arr_module["testcases"] = $this->obj_data->listTestcase($mod);

				$arr_rule = null;
				$arr_module["urls"] = Array();
				foreach ($arr_rules as $keys=>$rule) {
					if (in("event=".$arr_module["name"].":", $rule)) {
						$arr_rule = Array("nice" => $keys, "original" => $rule);
						array_push($arr_module["urls"], $arr_rule);
					}
				}
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
			$oldPrefix = $this->obj_data->prefix;
			$this->obj_data->prefix = "tbl_";
			foreach ($_POST["db"] as $table) {
				$dbs[$table] = $this->obj_data->get($table);
			}
			$this->obj_data->prefix = $oldPrefix;
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
			while (($imgFile = readdir($dp)) !== false) {
				if ($imgFile[0] != '.' && is_file($path.$imgFile) && filesize($path.$imgFile) > 0) {
					fwrite($fp, $fileMagic . $imgFile."\n");
					$sp = fopen($path.$imgFile, "r");
					while (!feof($sp)) {
						fwrite($fp, fread($sp, 4096));
					}
					fclose($sp);
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
		} else {
			if ($content == null || is_array($content)) {
				$fp = fopen("php://input", "r");
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
					if (in(($mgc = preg_replace('/^.*(---[a-fA-F0-9]+)$/mi', '\1', $line)), $edge)) {
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
				
				if ($section[0] == "A") { // import database contents
					// ignore DB contents explicitly on production so that nothing will be imported accidentally
					if (ENV_PRODUCTION === true && !$dataRestore) continue;
					$oldPrefix = $this->obj_data->prefix;
					$this->obj_data->prefix = "tbl_";
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
					$this->obj_data->prefix = $oldPrefix;
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
						$this->updateStream(Array("module" => $m['name'], "id" => $id));
						$this->resetModule(false, $m["module_id"]);
						$this->obj_data->deleteModule($m["module_id"]);
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
							$confs = Array();
							foreach ($mod["configs"] as $config) {
								$config = $config->getArrayCopy();
								if ($confs[$config["name"]]) continue;
								$confs[$config["name"]] = true;
								$config["fk_module_id"] = $modId;
								unset($config["configuration_id"]);
								$this->obj_data->insertConfiguration($config);
							}
						}	
						if (is_array($mod["configsDevel"])) {					
							$this->obj_data->clearConfiguration($id, 1);
							$confs = Array();
							foreach ($mod["configsDevel"] as $config) {
								$config = $config->getArrayCopy();
								if ($confs[$config["name"]]) continue;
								$confs[$config["name"]] = true;
								$config["fk_module_id"] = $modId;
								unset($config["configuration_id"]);
								$this->obj_data->insertConfiguration($config);
							}
						}
						if (is_array($mod["configsProd"])) {
							$this->obj_data->clearConfiguration($id, 2);
							$confs = Array();
							foreach ($mod["configsProd"] as $config) {
								$config = $config->getArrayCopy();
								if ($confs[$config["name"]]) continue;
								$confs[$config["name"]] = true;
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
						// cleanup and then add all related test cases
						$this->obj_data->clearTestcase($id);
						foreach ($mod["testcases"] as $tc) {
							$tc = $tc->getArrayCopy();
							unset($tc["testcase_id"]);
							$tc["fk_module_id"] = $modId;
							$this->obj_data->insertTestcase($tc);
						}
						// add custom URL definitions for the current module
						if (is_array($mod["urls"])) {
							$this->obj_data->updateUrlRules($mod['urls']);
						}
					}
				}
			} 
		}
		$this->flushWebCache(true);
		$this->flushCustomLibraries(true);
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
			$this->obj_data->sql->flush();        	
			$this->flushWebCache(true);
			$this->flushCustomModules(true);
			die("success\n--\n".$_GET['warnings']);        	
		} else {
			// first download the installer...
			// clean cache first
			if (PHP_OS == "WINNT")
				exec("cd cache; del *; cd ..");
			else
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
		$this->obj_data->sql->flush();
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
		
		$this->flushCustomLibraries($return);
		if (!$return || is_array($return)) die("success");
		return "success";
	}

	function flushCustomLibraries($return = false) {
		// flush libs
		$arr_libs = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
		foreach ($arr_libs as $lib) {
					if (ENV_PRODUCTION) {
				@unlink("lib/custom/".$lib["name"].".php");
					} else {
				@unlink("lib/custom/".$lib["name"].$lib["library_id"].".php");
					}
		}
		if (!$return || is_array($return)) die("success");
		return "success";
	}
		
	function flushLogs() {
		$dp = opendir("log/");
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != "." && in(".log", $file)) {
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
				$updated = false;
				foreach ($users as $user) {
					list($name, $password) = explode(":", $user);
					if (($name == $_SESSION['builder']['name'] && !isset($_POST['user']['name'])) || ($name == $_POST['user']['name'] && ($_SESSION['builder']['group'] == 'admin' || $_SESSION['builder']['name'] == "admin"))) {
						array_push($result, $name.":".md5($data["password"].(USER_SALT !== "USER_SALT" ? USER_SALT : "")));
						$updated = true;
					} else if (strlen(trim($user)) > 0) array_push($result, $user);
				}
				if (!$updated && $_POST['user']['name'] && ($_SESSION['builder']['group'] == 'admin' || $_SESSION['builder']['name'] == "admin")) 
					array_push($result, $_POST['user']['name'].":".md5($data['password'].(USER_SALT !== "USER_SALT" ? USER_SALT : "")));
				file_put_contents(".users", preg_replace('/[\\n]{2,}/', "\n", join("\n", $result)));

				if ($_POST['groups'] && ($_SESSION['builder']['group'] == 'admin' || $_SESSION['builder']['name'] == "admin")) {
					$groups = $_POST["groups"];
					$list = file(".groups");
					$result = Array();
					foreach ($list as $line) {
						list($grp, $users) = explode("=", trim($line));
						if (in($data['name'], explode(",", $users)) && !in($grp, $groups)) {
							$users = preg_replace('/'.$data['name'].'(,?)/i', "", $users);
						} else if (in($grp, $groups) && !in($data['name'], explode(",", $users))) {
							$users .= ','.$data['name'];
							$groups = array_diff($groups, Array($grp));
						} else {
							$groups = array_diff($groups, Array($grp));
						}
						array_push($result, $grp . "=" . $users);
					}
					foreach ($groups as $grp) {
						array_push($result, $grp . "=" . $data['name']);
					}
					file_put_contents(".groups", preg_replace('/[\\n]{2,}/', "\n", join("\n", $result)));
				}
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
						$newUsers[] = $user.":".md5($data["pass"][$key].(USER_SALT !== "USER_SALT" ? USER_SALT : ""));
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
			if ($file[0] != "." && in(".log", $file)) {
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
					if (in("image/", $mime)) {
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

		global $BUILDER_CLASS;
		$BUILDER_CLASS = "x-viewport";

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
			array_push($toPost["db"], $mod["name"]);
		}
		foreach ($arr_param["translations"] as $root => $mod) {
			array_push($toPost["translations"], $root);
		}
		$_POST = $toPost;
		if (!is_dir("static/backups")) {
			@mkdir("static/backups", 0755, true);
		}
		return $this->export("static/backups/".PROJECT_NAME."-".date("Ymd-Hi").".prails");
	}
	
	function restore() {
		if (!is_dir("static/backups")) {
			@mkdir("static/backups", 0755, true);
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
			$file = "cache/replication-data.prails";
			file_put_contents($file, file_get_contents(trim($credentials["source"],'/')."/?event=builder:export", false, $ctx));			
			$this->import($file);
			@unlink($file);
			die("success");
		}
	}
	
	function getStyleDefs() {
		readfile("templates/builder/css/stylepanel.css");
		echo "\n\n";
		readfile("templates/main/css/global.css");
		die();
	}

	function checkPHPSyntax() {
		$code = $_POST["code"];
		$result = $this->_checkPHPSyntax($code);
		if ($result === false) {
			die("false\n");
		} else {
			die(json_encode(Array("message" => $result[0], "line" => $result[1]))."\n");
		}
	}	
	
	function downloadProject() {
		global $SERVER;
		$project = PROJECT_NAME.".tar.bz2";

		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT\n");
                header("Content-Transfer-Encoding: binary");
                header("Content-type: application/octet-stream");
                header("Content-Disposition: attachment; filename=\"".$project."\"");
		
		$path = "cache/download/".preg_replace('/[^a-zA-Z0-9_]/', '_', PROJECT_NAME)."/";
		removeDir($path, true);
		mkdir($path, 0777, true);
		$metadata = "identifier=".$_SESSION['builder']['user_id']."\n";
		$metadata.= "instance=".$SERVER."\n";
		$metadata.= "credentials=".base64_encode(caesar($_SERVER['PHP_AUTH_USER'].":".$_SERVER['PHP_AUTH_PW'], $SERVER))."\n";
		file_put_contents($path.".metadata", $metadata);
		
		$modules = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
		mkdir($path."modules/", 0777, true);
		foreach ($modules as $mod) {
			mkdir($path."modules/".$mod['name'], 0777, true);
			mkdir($path."modules/".$mod['name']."/client", 0777, true);
			mkdir($path."modules/".$mod['name']."/client/resources/", 0777, true);
			mkdir($path."modules/".$mod['name']."/server", 0777, true);
			mkdir($path."modules/".$mod['name']."/server/templates/", 0777, true);
			mkdir($path."modules/".$mod['name']."/server/handlers/", 0777, true);
			mkdir($path."modules/".$mod['name']."/server/queries/", 0777, true);
			file_put_contents($path."modules/".$mod['name']."/client/".$mod['name'].".less", $mod['style_code']);
			file_put_contents($path."modules/".$mod['name']."/client/".$mod['name'].".js", $mod['js_code']);
			$handlers = $this->obj_data->listHandlers($mod['module_id']);
			foreach ($handlers as $handler) {
				// handle PHP code
		                $code = $handler["code"];
		                preg_match_all('/\/\*\[BEGIN POST-([^\]]+)\]\*\//mi', $code, $matches, PREG_OFFSET_CAPTURE);
		                $lastPos = 0;
		                if (is_array($matches) && is_array($matches[1])) {
		                        foreach ($matches[1] as $match) {
		                                $cd = Array(
		                                        "title" => $match[0],
		                                );
		                                $start = strpos($code, "/*[ACTUAL]*/", $match[1]) + strlen("/*[ACTUAL]*/") + 1;
		                                $end = strpos($code, "/*[END ACTUAL]*/", $start);
                		                $cd["content"] = substr($code, $start, $end - $start);
		                                $lastPos = strpos($code, "/*[END POST-".$match[0]."]*/\n", $match[1]) + strlen("/*[END POST-".$match[0]."]*/\n");
						file_put_contents($path."modules/".$mod['name']."/server/handlers/".$handler['event'].".".$cd["title"].".php", "<"."?\n".$cd["content"]."\n?".">");
                		        }
				}
	                	$code = substr($code, $lastPos);
				file_put_contents($path."modules/".$mod['name'].'/server/handlers/'.$handler['event'].".php", "<"."?\n".$code."\n?".">");
				
				// handle HTML code
				$code = $handler['html_code'];
		                preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
			        $lastPos = 0;
		                if (is_array($matches) && is_array($matches[1])) {
		                        foreach ($matches[1] as $match) {
		                                $cd = Array(
		                                        "title" => $match[0],
		                                );
		                                $start = $match[1] + strlen("".$match[0].">\n");
		                                $end = strpos($code, "</part-".$match[0].">\n", $match[1]);
		                                $cd["content"] = substr($code, $start, $end - $start);
		                                $lastPos = strpos($code, "</part-".$match[0].">\n", $match[1]) + strlen("</part-".$match[0].">\n");
						file_put_contents($path."modules/".$mod['name']."/server/templates/".$handler['event'].".".$cd['title'].".html", $cd['content']);
                        		}
		                }
                		$code = substr($code, $lastPos);
				file_put_contents($path."modules/".$mod['name']."/server/templates/".$handler['event'].".html", $code);
			}
			$datas = $this->obj_data->listDatas($mod['module_id']);
			foreach ($datas as $data) {
				file_put_contents($path."modules/".$mod['name']."/server/queries/".$data['name'].".php", "<"."?\n".$data["code"]."\n?".">");
			}

			// handle configuration options
		    $privateConfig = $this->obj_data->listConfigurationFromModule($mod["module_id"], 0);
			$publicConfig = $this->obj_data->listConfigurationFromModule($mod['module_id'], 1);
			$config = "# This file contains the module's configuration options. It's split into a \n".
				  "# development part and a production part. The first one means these configuration\n".
				  "# options should be used in development environments (so all instance types that\n".
				  "# are no production instances). Whereas all configuration options in the production\n".
				  "# section are only used on production instances.\n[development]\n";
			foreach ($publicConfig as $conf) {
				$config .= $conf['name']."=".$conf['value']."\n";
			}
			$config .= "\n[production]\n";
			foreach ($privateConfig as $conf) {
				$config .= $conf['name']."=".$conf['value']."\n";
			}
			file_put_contents($path."modules/".$mod['name']."/config.ini", $config);
		
			// handle resources
			$resources = $this->obj_data->listResources($mod["module_id"]);
			foreach ($resources as $res) {
				file_put_contents($path."modules/".$mod['name']."/client/resources/".$res['name'], base64_decode($res["data"]));
			}
		}

		// write out library codes
		mkdir($path."libs/", 0777, true);
		$libraries = $this->obj_data->listLibrariesFromUser($_SESSION["builder"]["user_id"]);
		foreach ($libraries as $lib) {
			file_put_contents($path."libs/".$lib['name'].".php", "<"."?\n".$lib['code']."\n?".">");
			if ($lib["fk_resource_id"] > 0) {
				$libfile = $path."lib/".$lib['name']."/".$lib['resource']['name'];
				file_put_contents($libfile, base64_decode($lib["resource"]["data"]));
                                preg_match('/((\.tar\.[a-zA-Z0-9]+)|(\.tgz)|(\.zip))$/mi', $libfile, $match);
                                if (strlen($match[1]) > 0) {
	                                // unpack it to get contents
 					if (PHP_OS == "WINNT") {
						if (in($match[1], Array(".tar.gz", ".tar.bz2", ".tgz"))) 
							exec("cd ".dirname($libfile)."; ..\\7za.exe x ".basename($libfile)."; ..\\7za.exe x ".basename(str_replace($match[1], ".tar", $libfile)));
						else 
							exec("cd ".dirname($libfile)."; ..\\7za.exe x ".basename($libfile));
					} else {
	                                       $progs = Array(".tar.bz2" => "tar -xvjf ", ".tar.gz" => "tar -xvzf ", ".tgz" => "tar -xvzf ", ".zip" => "unzip ");
					       exec("pushd;cd ".dirname($libfile)."; ".$progs[$match[1]].basename($libfile)."; rm ".basename($libfile)."; popd");
					}
                                }
			}
		}

		mkdir($path."tags/", 0777, true);
		$tags = $this->obj_data->listTagsFromUser($_SESSION["builder"]["user_id"]);
		foreach ($tags as $tag) {
			file_put_contents($path."tags/".$tag['name'].".tag", $tag['html_code']);
		}

		// pack it into an archive
		copy("sync.tar.bz2", $path."sync.tar.bz2");
		$dlName = preg_replace('/[^a-zA-Z0-9_]/', '_', PROJECT_NAME);
		if (PHP_OS == "WINNT") {
			exec("cd $path; ..\\7za.exe x sync.tar.bz2; ..\\7za.exe x sync.tar");
			unlink($path."sync.tar");
			unlink($path."sync.tar.bz2");

			exec("cd cache\\download; ..\\7za.exe a -ttar $dlName.tar ".basename($path)."; ..\\7za.exe -tbzip2 $dlName.tar.bz2 $dlName.tar");
		} else {
			exec("pushd;cd ".$path."; tar xvjf sync.tar.bz2; rm sync.tar.bz2; popd");
			exec("pushd;cd cache/download;tar cvjf $dlName.tar.bz2 ".basename($path).";popd");
		} 
		readfile(substr($path, 0, -1) . ".tar.bz2");
		@unlink(substr($path, 0, -1) . ".tar.bz2");
		die();
	}
	
	function syncStatus() {
		if (!empty($_POST['status'])) {
			$obj = json_decode($_POST['status'], true);
			$result = Array();
			$tagList = Array();
			$libList = Array();
			$moduleList = Array();
			$dataList = Array();
			$handlerList = Array();
			$resourceList = Array();
			if ($obj["tags"] && is_array($obj["tags"])) {
				foreach ($obj["tags"] as $tag) {
					$t = $this->obj_data->selectTagByUserAndName($_SESSION['builder']['user_id'], $tag["name"]);
					$time = $this->obj_data->selectLastChanged("tag", (int)$t["tag_id"]);
					$tagList[$tag["name"]] = (int)$t["tag_id"];
					array_push($result, Array(
						"diff" => crc32($t['html_code'])+4294967296 == $tag['crc'] ? 0 : ($time <= 0 ? $tag["time"] - 1 : $time) - (int)$tag["time"],
						"paths" => Array("../tags/".$tag["name"].".tag"),
						"id" => $t["tag_id"],
						"type" => "tag"
					));
				}
			}
			if ($obj["libs"] && is_array($obj["libs"])) {
				foreach ($obj["libs"] as $lib) {
					$l = $this->obj_data->selectLibraryByUserAndName($_SESSION['builder']['user_id'], $lib["name"]);
					$time = $this->obj_data->selectLastChanged("library", (int)$l["library_id"]);
					$libList[$lib["name"]] = (int)$l["library_id"];
					array_push($result, Array(
						"diff" => crc32($l['code'])+4294967296 == $lib['crc'] ? 0 : ($time <= 0 ? (int)$lib["time"] - 1 : $time) - (int)$lib["time"],
						"paths" => Array("../libs/".$lib["name"].".php"),
						"id" => $l["library_id"],
						"type" => "library"
					));
				}
			}
			if ($obj["modules"] && is_array($obj["modules"])) {
				foreach ($obj["modules"] as $module) {
					$m = $this->obj_data->selectModuleByUserAndName($_SESSION['builder']['user_id'], $module["name"]);
					if (!empty($module["resource"])) {
						$resource = $this->obj_data->selectResourceByName($module['resource']);
						if (!$resource) {
							array_push($result, Array(
								"diff" => -1,
								"paths" => Array($module['path']),
								"id" => 0,
								"type" => "resource"
							));
						} else {
							$resourceList[$module['resource']] = $resource['resource_id'];
						}
					} else if (!empty($module['data'])) {
						// queries are checked
						$d = $this->obj_data->getDataFromName($module['data'], $m['module_id']);
						$time = $this->obj_data->selectLastChanged("data", (int)$d["data_id"]);
						$dataList[$module["data"]] = (int)$d["data_id"];
						array_push($result, Array(
							"diff" => crc32($d['code'])+4294967296 == $module['crc'] ? 0 : ($time <= 0 ? (int)$module["time"] - 1 : $time) - (int)$module["time"],
							"paths" => Array("../modules/".$module["name"]."/server/queries/".$module['data'].".php"),
							"id" => $d["data_id"],
							"type" => "data"
						));
					} else if (!empty($module['handler'])) {
						// queries are checked
						$h = $this->obj_data->selectHandlerByNameAndModule($m['module_id'], $module['handler']);
						$time = $this->obj_data->selectLastChanged("handler", (int)$h["handler_id"]);
						$handlerList[$module["handler"]] = (int)$h["handler_id"];
						array_push($result, Array(
							"diff" => ($time <= 0 ? (int)$module["time"] - 1 : $time) - (int)$module["time"],
							"paths" => Array($module['path']),
							"id" => $h["handler_id"],
							"type" => "handler"
						));
					} else if ($module["config"]) {
						// ... skip for now
						// @TODO
					} else {
						// module itself is checked
						$time = $this->obj_data->selectLastChanged("module", (int)$m["module_id"]);
						$moduleList[$module["name"]] = (int)$m['module_id'];
						array_push($result, Array(
							"diff" => ($time <= 0 ? (int)$module["time"] - 1 : $time) - (int)$module["time"],
							"paths" => Array($module['path']),
							"id" => $m["module_id"],
							"type" => "module"
						));
					} 
				} // end foreach
			} // end is modules
			
			// now check what is missing
			$missingTags = $this->obj_data->query("SELECT * FROM tbl_prailsbase_tag WHERE tag_id NOT IN ('".implode("', '", $tagList)."') AND name!=''");
			foreach ($missingTags as $tag) {
				array_push($result, Array(
					"diff" => 1,
					"paths" => Array("./tags/".$tag["name"].".tag"),
					"id" => $tag["tag_id"],
					"type" => "tag"
				));
			}
			$missingLibs = $this->obj_data->query("SELECT * FROM tbl_prailsbase_library WHERE library_id NOT IN ('".implode("', '", $libList)."') AND name!=''");
			foreach ($missingLibs as $lib) {
				array_push($result, Array(
					"diff" => 1,
					"paths" => Array("./libs/".$lib["name"].".php"),
					"id" => $lib["library_id"],
					"type" => "library"
				));
			}
			$missingMods = $this->obj_data->query("SELECT * FROM tbl_prailsbase_module WHERE module_id NOT IN ('".implode("', '", $moduleList)."') AND name!=''");
			foreach ($missingMods as $mod) {
				array_push($result, Array(
					"diff" => 1,
					"paths" => Array("./modules/client/".$mod["name"].".js", "./modules/client/".$mod["name"].".less"),
					"id" => $mod["module_id"],
					"type" => "module"
				));
			}
			$missingDatas = $this->obj_data->query("SELECT a.* FROM tbl_prailsbase_data AS a, tbl_prailsbase_module WHERE a.fk_module_id=module_id AND data_id NOT IN ('".implode("', '", $dataList)."') AND a.name!=''");
			foreach ($missingDatas as $data) {
				array_push($result, Array(
					"diff" => 1,
					"paths" => Array("./modules/server/queries/".$data["name"].".php"),
					"id" => $data["data_id"],
					"type" => "data"
				));
			}
			$missingHandlers = $this->obj_data->query("SELECT a.* FROM tbl_prailsbase_handler AS a, tbl_prailsbase_module WHERE fk_user_id='".$_SESSION['builder']['user_id']."' AND a.fk_module_id=module_id AND handler_id NOT IN ('".implode("', '", $handlerList)."') AND event!=''");
			foreach ($missingHandlers as $handler) {
				array_push($result, Array(
					"diff" => 1,
					"paths" => Array(),
					"id" => $handler["handler_id"],
					"type" => "handler"
				));
			}
			die(json_encode($result));
		} // end status
		die();
	}
	
	function singleDownload() {
		if (!empty($_POST['type']) && !empty($_POST['id'])) {
			$result = Array();
			switch ($_POST['type']) {
				case "tag": 
					$tag = $this->obj_data->selectTag($_POST['id']);
					if ($tag) {
						$result["tags/".$tag['name'].".tag"] = $tag['html_code'];
					}
					break;
				case "library": 
					$lib = $this->obj_data->selectLibrary($_POST['id']);
					if ($lib) {
						$result['libs/'.$lib['name'].'.php'] = "<"."?\n".$lib['code']."\n?".">";
					}
					break;
				case "data":
					$data = $this->obj_data->selectData($_POST['id']);
					if ($data) {
						$result['modules/'.$data['module']['name'].'/server/queries/'.$data['name'].'.php'] = "<"."?\n".$data['code']."\n?".">";
					}
					break;
				case "handler":
					$handler = $this->obj_data->selectHandler($_POST['id']);
					if ($handler) {
						// handle event code
		                $code = $handler["code"];
		                preg_match_all('/\/\*\[BEGIN POST-([^\]]+)\]\*\//mi', $code, $matches, PREG_OFFSET_CAPTURE);
		                $lastPos = 0;
		                if (is_array($matches) && is_array($matches[1])) {
							foreach ($matches[1] as $match) {
							    $cd = Array(
							    	"title" => $match[0],
							    );
							    $start = strpos($code, "/*[ACTUAL]*/", $match[1]) + strlen("/*[ACTUAL]*/") + 1;
							    $end = strpos($code, "/*[END ACTUAL]*/", $start);
							    $cd["content"] = substr($code, $start, $end - $start);
							    $lastPos = strpos($code, "/*[END POST-".$match[0]."]*/\n", $match[1]) + strlen("/*[END POST-".$match[0]."]*/\n");
								$result["modules/".$handler['module']['name']."/server/handlers/".$handler['event'].".".$cd["title"].".php"] = "<"."?\n".$cd["content"]."\n?".">";
							}
						}
						$code = substr($code, $lastPos);
						$result["modules/".$handler['module']['name'].'/server/handlers/'.$handler['event'].".php"] = "<"."?\n".$code."\n?".">";
				
						// handle HTML code
						$code = $handler['html_code'];
		                preg_match_all('/<part-([^>]+)>/mi', $code, $matches, PREG_OFFSET_CAPTURE);
				        $lastPos = 0;
						if (is_array($matches) && is_array($matches[1])) {
							foreach ($matches[1] as $match) {
								$cd = Array(
									"title" => $match[0],
								);
								$start = $match[1] + strlen("".$match[0].">\n");
								$end = strpos($code, "</part-".$match[0].">\n", $match[1]);
								$cd["content"] = substr($code, $start, $end - $start);
								$lastPos = strpos($code, "</part-".$match[0].">\n", $match[1]) + strlen("</part-".$match[0].">\n");
								$result["modules/".$handler['module']['name']."/server/templates/".$handler['event'].".".$cd['title'].".html"] = $cd['content'];
                       		}
		                }
                		$code = substr($code, $lastPos);
						$result["modules/".$handler['module']['name']."/server/templates/".$handler['event'].".html"] = $code;
					}
					break;
				case "module":
					$module = $this->obj_data->selectModule($_POST['id']);
					if ($module) {
						$result["modules/".$module['name']."/client/".$module['name'].".less"] = $module['style_code'];
						$result["modules/".$module['name']."/client/".$module['name'].".js"] = $module['js_code'];
					}
					break;
			}
			die(json_encode($result));
		}
	}
	
/*</EVENT-HANDLERS>*/
	function _checkPHPSyntax($code) {
		$braces = 0;
		$inString = 0;
	
		// First of all, we need to know if braces are correctly balanced.
		// This is not trivial due to variable interpolation which
		// occurs in heredoc, backticked and double quoted strings
		foreach (token_get_all('<?php ' . $code) as $token) {
			if (is_array($token)) {
				switch ($token[0]) {
					case T_CURLY_OPEN:
					case T_DOLLAR_OPEN_CURLY_BRACES:
					case T_START_HEREDOC: ++$inString; break;
					case T_END_HEREDOC:   --$inString; break;
				}
			} else if ($inString & 1) {
				switch ($token) {
					case '`':
					case '"': --$inString; break;
				}
			} else {
				switch ($token) {
					case '`':
					case '"': ++$inString; break;
					case '{': ++$braces; break;
					case '}':
						if ($inString) --$inString;
						else {
							--$braces;
							if ($braces < 0) return Array("Missing '}'.", count(explode("\n", $code)));
						}
						break;
					}
			}
		}

		if ($braces != 0) return Array("Missing '}'.", count(explode("\n", $code)));

		// Display parse error messages and use output buffering to catch them
		$inString = @ini_set('log_errors', false);
		$token = @ini_set('display_errors', true);
		ob_start();
	
		// If $braces is not zero, then we are sure that $code is broken.
		// We run it anyway in order to catch the error message and line number.
	
		// Else, if $braces are correctly balanced, then we can safely put
		// $code in a dead code sandbox to prevent its execution.
		// Note that without this sandbox, a function or class declaration inside
		// $code could throw a "Cannot redeclare" fatal error.
	
		$code = "if(0){{$code}\n}";

		if (false === eval($code)) {
			// Get the maximum number of lines in $code to fix a border case
			in("\r", $code) && $code = strtr(str_replace("\r\n", "\n", $code), "\r", "\n");
			$braces = substr_count($code, "\n");

			$code = ob_get_clean();
			$code = strip_tags($code);

			// Get the error message and line number
			if (preg_match("'parse error([,:]?)(.*) in .+ on line (\\d+)\$'is", $code, $code)) {
				$code[3] = (int) $code[3];
				$code = $code[3] <= $braces
					? array(trim($code[2]), $code[3])
					: array('unexpected $end' . substr($code[2], 14), $braces);
			} else $code = array('syntax error', 0);
		} else {
			ob_end_clean();
			$code = false;
		}

		@ini_set('display_errors', $token);
		@ini_set('log_errors', $inString);
	
		return $code;
	}

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
