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

class BuilderPrinter
{
    var $obj_lang;
    var $str_base;

    function BuilderPrinter($str_lang)
    {
        $this->obj_lang = new LangData("builder", $str_lang);
        $obj_gen = Generator::getInstance();

        $event = explode(":", $_GET["event"]);
        if ($event[0] == "builder")
        {
        	$obj_gen->addJavaScript("templates/main/js/prototype.js");
        	$obj_gen->addJavaScript("templates/main/js/s2.js");
            $obj_gen->addJavaScript("templates/builder/js/ext.js");
            $obj_gen->addJavaScript("templates/builder/js/ux.util.js");
            $obj_gen->addJavaScript("templates/builder/js/fisheye_menu.js");
            $obj_gen->addJavaScript("templates/builder/js/fileselector.js");

            $obj_gen->addJavaScript("templates/builder/js/builder/builder.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.methods.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.module.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.resource.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.tag.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.table.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.data.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.handler.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.library.js");
            $obj_gen->addJavaScript("templates/builder/js/builder/builder.lang.js");
       	    $obj_gen->addJavaScript("templates/builder/js/builder/builder.testcases.js");

            $obj_gen->addStyleSheet("templates/builder/css/ext-all.css");
            $obj_gen->addStyleSheet("templates/builder/css/xtheme-slate.css");
            $obj_gen->addJavaScript("templates/builder/js/codemirror.js");
            $obj_gen->addJavaScript("templates/builder/js/mirrorframe.js");
            $obj_gen->addStyleSheet("templates/builder/css/builder.css");

            $obj_gen->str_template = "templates/builder/template.html";
        }
        $this->str_base = "?module=builder";
    }

    function home($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/home.html", $arr_param, false);
    }

    /*<PRINTER-METHODS>*/
    function listModules($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/listModules.html", $arr_param, false);
    }
    function editModule($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/editModule.html", $arr_param, false);
    }
    function moduleHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/moduleHistory.html", $arr_param, false);
    }
    function listHandlers()
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/listHandlers.html", $arr_param, false);
    }
    function editHandler($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/editHandler.html", $arr_param, false);
    }
    function handlerHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/handlerHistory.html", $arr_param, false);
    }
    function listDatas()
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/listDatas.html", $arr_param, false);
    }
    function editData($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/editData.html", $arr_param, false);
    }
    function dataModel($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/dataModel.html", $arr_param, false);
    }
    function dataHistory($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/dataHistory.html", $arr_param, false);
    }
    function editLibrary($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/editLibrary.html", $arr_param, false);
    }
    function editTag($arr_param)
    {
        return Generator::getInstance()->includeTemplate("templates/builder/html/editTag.html", $arr_param, false);
    }

    function listResources($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/listResources.html", $arr_param, false);
    }

    function editResource($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/editResource.html", $arr_param, false);
    }

    function editTable($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/editTable.html", $arr_param, false);
    }

    function editConfiguration($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/editConfiguration.html", $arr_param, false);
    }

    function queryTest($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/queryTest.html", $arr_param, false);
    }
	
	function niceUrl($arr_param) 
	{
        $arr_param["session"] = $_SESSION;
        return Generator::getInstance()->includeTemplate("templates/builder/html/niceUrl.html", $arr_param, false);
	}
    function editText($arr_param)
    {
        $arr_param["session"] = $_SESSION;
        Generator::getInstance()->setIsAjax(true);
        return Generator::getInstance()->includeTemplate("templates/builder/html/editText.html", $arr_param, false);
    }
	
	function listTestcase($arr_param) {
        $arr_param["session"] = $_SESSION;
        Generator::getInstance()->setIsAjax(true);
        return Generator::getInstance()->includeTemplate("templates/builder/html/listTestcase.html", $arr_param, false);		
	}
	
	function editTestcase($arr_param) {
        $arr_param["session"] = $_SESSION;
        Generator::getInstance()->setIsAjax(true);
        return Generator::getInstance()->includeTemplate("templates/builder/html/editTestcase.html", $arr_param, false);		
	}
	
	function debug($arr_param) {
        $arr_param["session"] = $_SESSION;
        Generator::getInstance()->setIsAjax(true);
        return Generator::getInstance()->includeTemplate("templates/builder/html/debug.html", $arr_param, false);		
	}
    /*</PRINTER-METHODS>*/
}

?>
