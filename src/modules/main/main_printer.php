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
    var $gen;

    function MainPrinter($str_lang)
    {
        $this->gen = OutputGenerator::getInstance();
		if (IS_SETUP) {
	        $this->obj_lang = new LangData($str_lang);
	        $this->gen->setLanguage($str_lang);
		}

        /*<CSS-INCLUDES>*/
        $this->gen->addStyleSheet("templates/main/css/main.css");
        $this->gen->addStyleSheet("templates/main/css/controls.css");
        $this->gen->addStyleSheet("templates/main/css/s2-ui.css");
        /*</CSS-INCLUDES>*/
        $this->gen->addStyleSheet("templates/main/css/global.css");

        /*<JAVASCRIPT-INCLUDES>*/
        $this->gen->addJavaScript("templates/main/js/prototype.js");
        $this->gen->addJavaScript("templates/main/js/s2.js");
        $this->gen->addJavaScript("templates/main/js/modernizr.min.js");
        $this->gen->addJavaScript("templates/main/js/main.js");
        $this->gen->addJavaScript("templates/main/js/fileselector.js");
        $this->gen->addJavaScript("templates/main/js/validator.js");
        $this->gen->addJavaScript("templates/main/js/overlabels.js");
        $this->gen->addJavaScript("templates/main/js/base64.js");
        $this->gen->addJavaScript("templates/main/js/cookie.js");
        $this->gen->addJavaScript("templates/main/js/jquery.js");
        $this->gen->addJavaScript("templates/main/js/jquery-ui.js");
        /*</JAVASCRIPT-INCLUDES>*/
        $this->gen->addJavaScript("templates/main/js/global.js");
		
		if (!ENV_PRODUCTION) {
			$this->gen->addJavaScript("templates/main/js/testing/testing.js");
		}

        $this->gen->setTitle("Prails Home");
        $this->gen->setDescription("To develop web applications at extreme speed");
        $this->gen->setKeywords(explode(",", "web development,php,framework"));

        $this->str_base = "?";
    }

    function home($arr_param) {
        $str_content = $this->gen->includeTemplate("templates/main/html/home.html", $arr_param);
        return $str_content;
    }
    
    function pageNotFound($arr_param) {
		$this->gen->setTitle(PROJECT_NAME." - 404: Page not found");
    	return $this->gen->includeTemplate("templates/main/html/404.html", $arr_param);
    }
    
    function cmsHandler($arr_param) {
        $decorator = "<!--[content]-->";
        
        if (strlen($arr_param["text"]["decorator"]) > 0) {
            $decorator = invoke($arr_param["text"]["decorator"], null, true);
        } else {
			$this->gen->setIsCachable(true);        	
        }
        
		if (strlen($arr_param["text"]["title"]) > 0) {
			$this->gen->setTitle($arr_param['text']['title']);
		}
		if (strlen($arr_param['text']['description']) > 0) {
			$this->gen->setDescription($arr_param['text']['description']);
		}
        
		return str_replace("<!--[content]-->", $arr_param["text"]["content"], $decorator);
    }
    
    function setup($arr_param) {
    	return $this->gen->includeTemplate("templates/main/html/setup.html", $arr_param);
    }
}
?>
