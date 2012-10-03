<?php
/**
 Prails Web Framework
 Copyright (C) 2012  Robert Kunze
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

class MainPrinter
{
    var $obj_lang;
    var $str_base;

    function MainPrinter($str_lang)
    {
        $obj_gen = Generator::getInstance();
		if (IS_SETUP) {
	        $this->obj_lang = new LangData($str_lang);
	        $obj_gen->setLanguage($str_lang);
		}

        /*<CSS-INCLUDES>*/
        $obj_gen->addStyleSheet("templates/main/css/main.css");
        $obj_gen->addStyleSheet("templates/main/css/controls.css");
        $obj_gen->addStyleSheet("templates/main/css/s2-ui.css");
        /*</CSS-INCLUDES>*/
        $obj_gen->addStyleSheet("templates/main/css/global.css");

        /*<JAVASCRIPT-INCLUDES>*/
        $obj_gen->addJavaScript("templates/main/js/prototype.js");
        $obj_gen->addJavaScript("templates/main/js/s2.js");
        $obj_gen->addJavaScript("templates/main/js/modernizr.min.js");
        $obj_gen->addJavaScript("templates/main/js/main.js");
        $obj_gen->addJavaScript("templates/main/js/fileselector.js");
        $obj_gen->addJavaScript("templates/main/js/validator.js");
        $obj_gen->addJavaScript("templates/main/js/overlabels.js");
        $obj_gen->addJavaScript("templates/main/js/base64.js");
        $obj_gen->addJavaScript("templates/main/js/cookie.js");
        $obj_gen->addJavaScript("templates/main/js/jquery.js");
        $obj_gen->addJavaScript("templates/main/js/jquery-ui.js");
        /*</JAVASCRIPT-INCLUDES>*/
        $obj_gen->addJavaScript("templates/main/js/global.js");
		
		if (!ENV_PRODUCTION) {
			$obj_gen->addJavaScript("templates/main/js/testing/testing.js");
		}

        $obj_gen->setTitle("Prails Home");
        $obj_gen->setDescription("To develop web applications at extreme speed");
        $obj_gen->setKeywords(explode(",", "web development,php,framework"));

        $this->str_base = "?";
    }

    function home($arr_param) {
        $str_content = Generator::getInstance()->includeTemplate("templates/main/html/home.html", $arr_param);
        return $str_content;
    }
    
    function pageNotFound($arr_param) {
		Generator::getInstance()->setTitle(PROJECT_NAME." - 404: Page not found");
    	return Generator::getInstance()->includeTemplate("templates/main/html/404.html", $arr_param);
    }
    
    function cmsHandler($arr_param) {
        $decorator = "<!--[content]-->";
        
        if (strlen($arr_param["text"]["decorator"]) > 0) {
            $decorator = invoke($arr_param["text"]["decorator"], null, true);
        } else {
			Generator::getInstance()->setIsCachable(true);        	
        }
        
		if (strlen($arr_param["text"]["title"]) > 0) {
			Generator::getInstance()->setTitle($arr_param['text']['title']);
		}
		if (strlen($arr_param['text']['description']) > 0) {
			Generator::getInstance()->setDescription($arr_param['text']['description']);
		}
        
		return str_replace("<!--[content]-->", $arr_param["text"]["content"], $decorator);
    }
    
    function setup($arr_param) {
    	return Generator::getInstance()->includeTemplate("templates/main/html/setup.html", $arr_param);
    }
}
?>
