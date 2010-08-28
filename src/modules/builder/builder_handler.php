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

    function BuilderHandler($str_lang = "")
    {
        $this->obj_data = new BuilderData();
        $this->str_lang = $str_lang;
        $this->obj_print = new BuilderPrinter($str_lang);
        //
        if (!$_SESSION["builder"]["user_id"])
        {
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
                if (in_array($_SERVER["PHP_AUTH_USER"].":".$_SERVER["PHP_AUTH_PW"], $passwd))
                {
                    $_SESSION["builder"]["name"] = $_SERVER["PHP_AUTH_USER"];
                    $_SESSION["builder"]["user_id"] = crc32($u_group);
                } else
                {
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
        $arr_param["texts"] = Generator::getInstance()->getLanguage()->listTexts();

        foreach ($arr_param["modules"] as $key=>$arr_module)
        {
            $arr_param["modules"][$key]["handlers"] = $this->obj_data->listHandlers($arr_module["module_id"]);
            $arr_param["modules"][$key]["datas"] = $this->obj_data->listDatas($arr_module["module_id"]);
        }

        if (!$_SESSION["builder"]["user_code"])
        {
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
            $mod = strtolower($arr_module["name"]).(ENV_PRODUCTION === true?"":$arr_module["module_id"]);
            @mkdir("modules/".$mod, 0755);
            @mkdir("modules/".$mod."/lib", 0755);
            $config = "\n";
            foreach ($arr_configuration as $arr_conf)
            {
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
                file_put_contents($tagPath.$arr_tag["name"].(ENV_PRODUCTION===true ? "" : $arr_tag["fk_user_id"]).".tag", $arr_tag["html_code"]);
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
                $jsinc = "";
                $cssinc = "";

                $header = @unserialize($arr_module["header_info"]);
                if (is_array($header))
                {
                    if (is_array($header["js_includes"])) foreach ($header["js_includes"] as $entry)
                    {
                        $jsinc .= "	  \$obj_gen->addJavaScript(\"".$entry."\");\n";
                    }
                    if (is_array($header["css_includes"])) foreach ($header["css_includes"] as $entry)
                    {
                        $cssinc .= "	  \$obj_gen->addStyleSheet(\"".$entry."\");\n";
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
                    $handler .= "\nfunction ".$arr_handler["event"]."() {\n".$code."\n}\n";
                    $printer .= "\nfunction ".$arr_handler["event"]."(\$arr_param, \$decorator) {\n";
                    $printer .= "  \$arr_param[\"session\"] = &\$_SESSION;\n";
                    $printer .= "  \$arr_param[\"odict\"] = &\$_SESSION[\"odict\"];\n";
                    if ($arr_handler["flag_ajax"] == "1")
                    {
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

    function resetModule($die = true, $module_id = "")
    {
    	$module_id = if_set($module_id, $_SESSION["module_id"]);
        $arr_param["module"] = $this->obj_data->selectModule($module_id);

        if (strlen($arr_param["module"]["name"]) > 0 && $arr_param["module"]["module_id"] > 0)
        {
            if (ENV_PRODUCTION) {
                removeDir("modules/".$arr_param["module"]["name"], true);
            } else {
                removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
                removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
            }
            removeDir("templates/".$arr_param["module"]["name"], true);
        }

        if ($die)
        {
            die ("success");
        } else
        {
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

        removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
        removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);

        $this->obj_data->deleteModule($_GET["module_id"]);
        $this->obj_data->deleteHandlerFromModule($_GET["module_id"]);
        $this->obj_data->deleteDataFromModule($_GET["module_id"]);
        die ("success");
    }

    function editModule()
    {
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_data = $_POST["module"];
            if (!$arr_data["header_info"])
            {
                $arr_data["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_data["style_code"] = $arr_data["style_code"];
                $arr_data["js_code"] = $arr_data["js_code"];
            } else if ($_GET["module_id"] >= 0)
            {
                $this->resetModule(false);
            }
            if ($_GET["module_id"] > 0)
            {
                $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);
                if ($arr_data["header_info"])
                {
                    $arr_data["header_info"] = @serialize(array_merge(@unserialize($arr_param["module"]["header_info"]), $arr_data["header_info"]));
                }
                removeDir("modules/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);
                removeDir("templates/".$arr_param["module"]["name"].$arr_param["module"]["module_id"], true);

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

            if ($_GET["module_id"] > 0)
            {
                $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
                $arr_obj["m_style_code_".$_GET["module_id"]] = crc32($arr_data["style_code"]);
                $arr_obj["m_js_code_".$_GET["module_id"]] = crc32($arr_data["js_code"]);
                file_put_contents("builder.crc32", json_encode($arr_obj));
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

        // create crc32 files
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["module_id"] > 0 && $arr_obj["m_style_code".$_GET["module_id"]] != crc32($arr_param["module"]["style_code"]) || $arr_obj["m_js_code".$_GET["module_id"]] != crc32($arr_param["module"]["js_code"]))
        {
            $arr_obj["m_style_code_".$_GET["module_id"]] = crc32($arr_param["module"]["style_code"]);
            $arr_obj["m_js_code_".$_GET["module_id"]] = crc32($arr_param["module"]["js_code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
        }

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
            $arr_data["code"] = ($arr_data["code"]);
            $arr_data["html_code"] = ($arr_data["html_code"]);
            if ($_GET["handler_id"] > 0)
            {
                $arr_param["handler"] = $this->obj_data->selectHandler($_GET["handler_id"]);
                $this->obj_data->updateHandler($_GET["handler_id"], $arr_data);
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

            if ($_GET["handler_id"] > 0)
            {
                $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
                $arr_obj["h_html_code_".$_GET["handler_id"]] = crc32($arr_data["html_code"]);
                $arr_obj["h_code_".$_GET["handler_id"]] = crc32($arr_data["code"]);
                file_put_contents("builder.crc32", json_encode($arr_obj));
            }

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
        }

        // create crc32 files
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["handler_id"] > 0 && $arr_obj["h_html_code".$arr_param["handler"]["handler_id"]] != crc32($arr_param["handler"]["html_code"]) || $arr_obj["h_code".$arr_param["handler"]["handler_id"]] != crc32($arr_param["handler"]["code"]))
        {
            $arr_obj["h_html_code_".$_GET["handler_id"]] = crc32($arr_param["handler"]["html_code"]);
            $arr_obj["h_code_".$_GET["handler_id"]] = crc32($arr_param["handler"]["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
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

            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["d_code_".$_GET["data_id"]] = crc32($arr_data["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));

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

        // create crc32 files
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["data_id"] > 0 && $arr_obj["d_code".$_GET["data_id"]] != crc32($arr_param["data"]["code"]))
        {
            $arr_obj["d_code_".$_GET["data_id"]] = crc32($arr_param["data"]["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));
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
        $this->obj_data->deleteLibrary($_GET["library_id"]);
        die ("success");
    }

    function editLibrary()
    {
        $_SESSION["library_id"] = $_GET["library_id"] = if_set($_GET["library_id"], $_SESSION["library_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
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
            if (strlen($arr_param["library"]["name"]) > 0)
            {
                if (ENV_PRODUCTION) {
                    @unlink("lib/custom/".$arr_param["library"]["name"].".php");
                } else {
                    @unlink("lib/custom/".$arr_param["library"]["name"].$_GET["library_id"].".php");
                }
            }
            echo $_GET["library_id"]."\n";

            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["l_code_".$_GET["library_id"]] = crc32($arr_library["code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));

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
        }

        $arr_param["library"] = $this->obj_data->selectLibrary($_GET["library_id"]);

        // create crc32 files
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["library_id"] > 0 && $arr_obj["l_code".$_GET["library_id"]] != crc32($arr_param["library"]["code"]))
        {
            $arr_obj["l_code_".$_GET["library_id"]] = crc32($arr_param["library"]["code"]);
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
        $_SESSION["tag_id"] = $_GET["tag_id"] = if_set($_GET["tag_id"], $_SESSION["tag_id"]);
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
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

            $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
            $arr_obj["t_html_code_".$_GET["tag_id"]] = crc32($arr_tag["html_code"]);
            file_put_contents("builder.crc32", json_encode($arr_obj));

            return $this->resetModule();
            //           die("success");
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

        // create crc32 files
        $arr_obj = json_decode(file_get_contents("builder.crc32"), true);
        if ($_GET["tag_id"] > 0 && $arr_obj["t_html_code".$_GET["tag_id"]] != crc32($arr_param["tag"]["html_code"]))
        {
            $arr_obj["t_html_code_".$_GET["tag_id"]] = crc32($arr_param["tag"]["html_code"]);
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
        }

        $arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"]);
        $arr_param["module"] = $this->obj_data->selectModule($_GET["module_id"]);

        die ($this->_callPrinter("editResource", $arr_param));
    }

    function previewResource()
    {
        if ($_GET["resource_id"] > 0)
        {
            $arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"]);
            header("Content-Type: ".$arr_param["resource"]["type"]);
            die (base64_decode($arr_param["resource"]["data"]));
        } else
        {
            readfile("templates/builder/images/empty_resource.gif");
            die ();
        }
    }

    function createResource()
    {
        $arr_param["module"] = $this->obj_data->selectModuleByUserAndName($_SESSION["builder"]["user_id"], $_GET["mod"]);
        $arr_param["resource"] = $this->obj_data->selectResourceByName($arr_param["module"]["module_id"], $_GET["resource"]);

        if ($arr_param["resource"] != null)
        {
            $mod = strtolower($arr_param["module"]["name"]);

            $basePath = "templates/".$mod."/images/";
            $path = $basePath.$arr_param["resource"]["name"];
            if (!file_exists($basePath))
            {
                mkdir($basePath, 0755, true);
                @chmod($basePath, 0755);
                @chmod("templates/".$mod, 0755);
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
            $arr_param["resource"] = $this->obj_data->selectResource($_GET["resource_id"]);
            $arr_param["module"] = $this->obj_data->selectModule($arr_param["resource"]["fk_module_id"]);

            $mod = strtolower($arr_param["module"]["name"]);

            $basePath = "templates/".$mod."/images/";
            $path = $basePath.$arr_param["resource"]["name"];

            $this->obj_data->deleteResource($_GET["resource_id"]);
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

        require ($filename);
        die ();
    }

    function logout()
    {
        $_SESSION["builder"] = Array();
        session_destroy();
        header('WWW-Authenticate: Basic realm="Prails Web Framework Realm"');
        header('HTTP/1.0 401 Unauthorized');
        require ("templates/builder/html/not_allowed.html");
        die ();
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
            if ($_GET["table_id"] > 0)
            {
                $arr_param["table"] = $this->obj_data->selectTable($_GET["table_id"]);
                $this->obj_data->updateTable($_GET["table_id"], $arr_table);
            } else
            {
                $arr_table["fk_user_id"] = $_SESSION["builder"]["user_id"];
                $arr_table["fk_module_id"] = $_GET["module_id"];
                $_SESSION["table_id"] = $_GET["table_id"] = $this->obj_data->insertTable($arr_table);

                $arr_module = $this->obj_data->selectModule($_POST["scaffold"]["fk_module_id"]);
                $arr_module["name"] = strtolower($arr_module["name"]);
                if ($_POST["d_scaffold"])
                {
                	$needFlush = true;
                    foreach ($_POST["d_scaffold"] as $data=>$one)
                    {
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
            }
            $this->obj_data->insertTableHistory($_GET["table_id"], $arr_param["table"], $arr_table);

            // re-deploy this table
            $arr_fields = Array();
            $items = explode(":", $arr_table["field_names"]);
            $types = explode(":", $arr_table["field_types"]);
            foreach ($items as $i=>$field)
            {
                $arr_fields[$field] = $types[$i];
            }
            $arr_db = Array();
            $arr_db[$arr_table["name"]] = $arr_fields;
            DBDeployer::deploy($arr_db);
            echo $_GET["table_id"]."\n";
            die ("success");
        }

        $arr_param["modules"] = $this->obj_data->listModulesFromUser($_SESSION["builder"]["user_id"]);
        $arr_param["table"] = $this->obj_data->selectTable($_GET["table_id"]);

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
        $_SESSION["module_id"] = $_GET["module_id"] = if_set($_GET["module_id"], $_SESSION["module_id"]);

        if ($_GET["check"] == "1")
        {
            $arr_configuration = $_POST["configuration"];

            if ($_GET["module_id"] < 0)
            {
                // store the changed data in our configuration.php
				$cnt = file_get_contents("conf/configuration.php");
				$settings = Array();
				foreach ($arr_configuration as $conf) {
					$value = $conf["value"];
					if (is_numeric($value) || (strtolower($value) == "true" || strtolower($value) == "false")) {
						$var = @eval("return (".$value.");");
					} else $var = $value;
					if (gettype($var) == "string") {
						$var = "\"".$var."\"";
					} else if (gettype($var) == "boolean") {
						$var = $var ? "true" : "false";
					}
					array_push($settings, "\"".$conf["name"]."\" => ".$var);
				}
				$pre = substr($cnt, 0, strpos($cnt, "/*<CUSTOM-SETTINGS>*/")+strlen("/*<CUSTOM-SETTINGS>*/")+1);
				$post = substr($cnt, strpos($cnt, "/*</CUSTOM-SETTINGS>*/")-1);
				file_put_contents("conf/configuration.php", $pre.implode(",\n", $settings).$post);
            } else
            {
                $this->obj_data->clearConfiguration($_GET["module_id"]);
                foreach ($arr_configuration as $arr_conf)
                {
                    $arr_conf["fk_module_id"] = $_GET["module_id"];
                    $this->obj_data->insertConfiguration($arr_conf);
                }
            }

            die ("success");
        }

        if ($_GET["module_id"] < 0)
        {
        	global $arr_settings;
            $arr_param["configuration"] = Array();
            foreach ($arr_settings as $name=>$value)
            {
            	if (gettype($value) == "boolean") $value = ($value ? "true" : "false");
                array_push($arr_param["configuration"], Array(
                	"fk_module_id"=>-1,
                	"flag_public"=>1,
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
            $arr_param["configuration"] = $this->obj_data->listConfigurationFromModule($_GET["module_id"]);
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
			$arr_param["result"] = $this->obj_data->SqlQuery($query);
		} else {
			$arr_param["result"] = $this->obj_data->SqlQuery("SELECT name AS table_name, REPLACE(':', ', ', field_names) AS fields FROM tbl_table WHERE fk_user_id=\"".$_SESSION["builder"]["user_id"]."\"");
		}
		$arr_param["error"] = $this->obj_data->obj_mysql->lastError;

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

		$arr_param["result"] = Array();
		if (is_array($arr_param["handlerResult"]))$arr_param["result"] = $arr_param["handlerResult"];
		if (is_array($arr_param["dataResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["dataResult"]);
		if (is_array($arr_param["libraryResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["libraryResult"]);
		if (is_array($arr_param["moduleResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["moduleResult"]);
		if (is_array($arr_param["tagResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["tagResult"]);
		if (is_array($arr_param["tableResult"]))$arr_param["result"] = array_merge($arr_param["result"], $arr_param["tableResult"]);
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
	
	function export() {
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT\n");
		header("Content-Transfer-Encoding: binary");
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"".$_POST["file"]."\"");
		$magic_border = md5(serialize($_POST));
		if ($_POST["modules"]) {
			echo "---".$magic_border."\n";
			$modules = Array();
			foreach ($_POST["modules"] as $mod) {
				$arr_module = $this->obj_data->selectModule($mod);
				$arr_module["handlers"] = $this->obj_data->listHandlers($mod);
				$arr_module["datas"] = $this->obj_data->listDatas($mod);
				$arr_module["configs"] = $this->obj_data->listConfigurationFromModule($mod);
				$arr_module["resources"] = $this->obj_data->listResources($mod);
				array_push($modules, $arr_module);				
			}
			echo "M";
			echo gzcompress(serialize($modules), 9);
			unset($modules);
		}
		if ($_POST["libraries"]) {
			echo "---".$magic_border."\n";
			$libraries = Array();
			foreach ($_POST["libraries"] as $lib) {
				$arr_library = $this->obj_data->selectLibrary($lib);
				array_push($libraries, $arr_library);
			}
			echo "L";
			echo gzcompress(serialize($libraries), 9);
			unset($libraries);
		}
		if ($_POST["tags"]) {
			echo "---".$magic_border."\n";
			$tags = Array();
			foreach ($_POST["tags"] as $tag) {
				array_push($tags, $this->obj_data->selectTag($tag));
			}
			echo "T";
			echo gzcompress(serialize($tags), 9);
			unset($tags);
		}
		if ($_POST["tables"]) {
			echo "---".$magic_border."\n";
			$tables = Array();
			foreach ($_POST["tables"] as $table) {
				array_push($tables, $this->obj_data->selectTable($table));
			}
			echo "D";
			echo gzcompress(serialize($tables), 9);
			unset($tables);
		}
		die();
	}
	
	function import() {
		if ($_FILES["file"]) {
			$content = file_get_contents($_FILES["file"]["tmp_name"]);
		} else {
			$content = file_get_contents("php://input");
		}
		if (isset($content) && !empty($content)) {
			// import everything we find
			if (substr($content, 0, 3) !== "---") {
				die("error parsing input file...");
			}
			$magic = substr($content, 0, strpos($content, "\n")+1);
			$sections = explode($magic, substr($content, strlen($magic)));
			foreach ($sections as $section) {
				$data = unserialize(gzuncompress(substr($section, 1)));
				if ($section[0] == "D") {
					// import database table
					foreach ($data as $arr_table) {
					    $arr_table["fk_user_id"] = $_SESSION["builder"]["user_id"];
						$this->obj_data->insertTable($arr_table);
						// deploy table
			            $arr_fields = Array();
			            $items = explode(":", $arr_table["field_names"]);
			            $types = explode(":", $arr_table["field_types"]);
			            foreach ($items as $i=>$field)
			            {
			                $arr_fields[$field] = $types[$i];
			            }
			            $arr_db = Array();
			            $arr_db[$arr_table["name"]] = $arr_fields;
			            DBDeployer::deploy($arr_db);
					}
				} else if ($section[0] == "T") {
					foreach ($data as $tag) {
                        $tag["fk_user_id"] = $_SESSION["builder"]["user_id"];
						$this->obj_data->insertTag($tag);
					}
				} else if ($section[0] == "L") {
					foreach ($data as $library) {
 					    $library["fk_user_id"] = $_SESSION["builder"]["user_id"];
						$this->obj_data->insertLibrary($library);
					}
				} else if ($section[0] == "M") {
					foreach ($data as $mod) {
					    $mod["fk_user_id"] = $_SESSION["builder"]["user_id"];
						$this->obj_data->insertModule($mod);
						foreach ($mod["handlers"] as $handler) {
							$this->obj_data->insertHandler($handler);
						}
						foreach ($mod["datas"] as $data) {
							$this->obj_data->insertData($data);
						}
						foreach ($mod["configs"] as $config) {
							$this->obj_data->insertConfiguration($config);
						}
						foreach ($mod["resources"] as $res) {
							$this->obj_data->insertResource($res);
						}
					}
				}
			} 
		}
		die("success");
	}

	function editText() {
		$_SESSION["texts_id"] = $_GET["texts_id"] = if_set($_GET["texts_id"], $_SESSION["texts_id"]);
		
		if ($_GET["check"] == "1") {
			// update / insert texts
			$arr_data = $_POST["texts"];
			Generator::getInstance()->obj_lang->updateTexts($arr_data);
		} else if ($_GET["check"] == "2") {
		    Generator::getInstance()->obj_lang->updateTextType($_POST["id"], $_POST["type"]);
		    die("success");
	    }
		
		if ($_GET["ident"]) {
		    $arr_param["texts"] = Generator::getInstance()->obj_lang->getAllTextsByIdentifier($_GET["ident"]);
		    $_SESSION["texts_id"] = $_GET["texts_id"] = $arr_param["texts"][0]["texts_id"];
		} else {
    		$arr_param["texts"] = Generator::getInstance()->obj_lang->getAllTextsById($_GET["texts_id"]);
		}

		if ($_GET["path"] && $_GET["texts_id"] == 0) {
			$arr_param["text"]["path"] = $_GET["path"];
		} else {
		    $arr_param["text"]["path"] = substr($arr_param["texts"][0]["identifier"], 0, strrpos($arr_param["texts"][0]["identifier"], ".")+1);
		    $arr_param["text"]["name"] = substr($arr_param["texts"][0]["identifier"], strrpos($arr_param["texts"][0]["identifier"], ".")+1);
		    $arr_param["text"]["type"] = $arr_param["texts"][0]["type"];
		    $arr_param["text"]["decorator"] = $arr_param["texts"][0]["decorator"];
		}
		
        if ($arr_param["text"]["type"] == 2 || substr($arr_param["text"]["path"], 0, 4) == "cms.") {
            $arr_param["decorators"] = $this->obj_data->selectDecoratorEventsFromUser($_SESSION["builder"]["user_id"]);
        }

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
