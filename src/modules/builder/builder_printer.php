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

class BuilderPrinter
{
    var $obj_lang;
    var $str_base;
    var $gen;

    function BuilderPrinter($str_lang)
    {
        $this->obj_lang = new LangData("builder", $str_lang);
        $this->gen = OutputGenerator::getInstance();
        $this->gen->setLanguage($str_lang);

        $event = explode(":", $_GET["event"]);
        if ($event[0] == "builder")
        {
        	$this->gen->addJavaScript("templates/main/js/prototype.js");
        	$this->gen->addJavaScript("templates/main/js/s2.js");
        	$this->gen->addJavaScript("templates/builder/js/ext-base.js");
            $this->gen->addJavaScript("templates/builder/js/ext.js");
            $this->gen->addJavaScript("templates/builder/js/ext.ux/ux.util.js");

			$this->gen->addJavaScript("templates/builder/js/fisheye_menu.js");
            $this->gen->addJavaScript("templates/builder/js/fileselector.js");
    	    $this->gen->addJavaScript("templates/builder/js/jslint.js");

            $this->gen->addJavaScript("templates/builder/js/builder/builder.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.methods.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.module.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.resource.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.tag.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.table.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.data.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.handler.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.library.js");
            $this->gen->addJavaScript("templates/builder/js/builder/builder.lang.js");
       	    $this->gen->addJavaScript("templates/builder/js/builder/builder.testcases.js");

            $this->gen->addStyleSheet("templates/builder/css/ext-all.css");
            if (SNOW_MODE === true) {
                $this->gen->addStyleSheet("templates/builder/css/builder.css");
                $this->gen->addStyleSheet("templates/builder/css/xtheme-snow.css");
            } else {
                $this->gen->addStyleSheet("templates/builder/css/xtheme-newgentheme.css");
                $this->gen->addStyleSheet("templates/builder/css/builder.css");
            }

            $this->gen->str_template = "templates/builder/template.html";
        }
        $this->str_base = "?module=builder";
    }

    function home($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/home.html", $arr_param, false);
    }

    /*<PRINTER-METHODS>*/
    function listModules($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/listModules.html", $arr_param, false);
    }
    function editModule($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/editModule.html", $arr_param, false);
    }
    function moduleHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/moduleHistory.html", $arr_param, false);
    }
    function listHandlers()
    {
        return $this->gen->includeTemplate("templates/builder/html/listHandlers.html", $arr_param, false);
    }
    function editHandler($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/editHandler.html", $arr_param, false);
    }
    function handlerHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/handlerHistory.html", $arr_param, false);
    }
    function listDatas()
    {
        return $this->gen->includeTemplate("templates/builder/html/listDatas.html", $arr_param, false);
    }
    function editData($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/editData.html", $arr_param, false);
    }
    function dataModel($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/dataModel.html", $arr_param, false);
    }
    function dataHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/dataHistory.html", $arr_param, false);
    }
    function editLibrary($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/editLibrary.html", $arr_param, false);
    }
    function libraryHistory($arr_param) {
        $arr_param['session'] = &$_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/libraryHistory.html", $arr_param, false);
    }
    function editTag($arr_param)
    {
        return $this->gen->includeTemplate("templates/builder/html/editTag.html", $arr_param, false);
    }

    function listResources($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/listResources.html", $arr_param, false);
    }

    function editResource($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/editResource.html", $arr_param, false);
    }

    function editTable($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/editTable.html", $arr_param, false);
    }

    function editConfiguration($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/editConfiguration.html", $arr_param, false);
    }

    function queryTest($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/queryTest.html", $arr_param, false);
    }
	
	function niceUrl($arr_param) 
	{
        $arr_param["session"] = $_SESSION;
        return $this->gen->includeTemplate("templates/builder/html/niceUrl.html", $arr_param, false);
	}
    function editText($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/editText.html", $arr_param, false);
    }
	
	function listTestcase($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/listTestcase.html", $arr_param, false);		
	}
	
	function editTestcase($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/editTestcase.html", $arr_param, false);		
	}
	
	function debug($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/debug.html", $arr_param, false);		
	}
	
	function editUser($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/editUser.html", $arr_param, false);		
	}
	
	function showLog($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setIsAjax(true);
        return $this->gen->includeTemplate("templates/builder/html/showLog.html", $arr_param, false);		
	}
	
	function fileBrowser($arr_param) {
        $arr_param["session"] = &$_SESSION;
        $this->gen->setTitle("Media Browser");
        return $this->gen->includeTemplate("templates/builder/html/fileBrowser.html", $arr_param, false);
	}
/*</PRINTER-METHODS>*/
}

?>