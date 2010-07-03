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

class MainPrinter
{
    var $obj_lang;
    var $str_base;

    function MainPrinter($str_lang)
    {
        $this->obj_lang = new LangData($str_lang);
        $obj_gen = Generator::getInstance();

        /*<CSS-INCLUDES>*/
        $obj_gen->addStyleSheet("templates/main/css/main.css");
        /*</CSS-INCLUDES>*/
        $obj_gen->addStyleSheet("templates/main/css/global.css");

        /*<JAVASCRIPT-INCLUDES>*/
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/lib/prototype.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/scriptaculous.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/builder.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/effects.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/controls.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/dragdrop.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/sound.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/slider.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/lib/control.modal.js");
        $obj_gen->addJavaScript("templates/main/js/main.js");
        $obj_gen->addJavaScript("templates/main/js/fileselector.js");
        $obj_gen->addJavaScript("templates/main/js/evalfields.js");
        $obj_gen->addJavaScript("templates/main/js/overlabels.js");
        $obj_gen->addJavaScript("templates/main/js/base64.js");
        $obj_gen->addJavaScript("templates/main/js/browserdetect.js");
        $obj_gen->addJavaScript("templates/main/js/cookie.js");
        $obj_gen->addJavaScript("templates/main/js/control.date.js");
        /*</JAVASCRIPT-INCLUDES>*/
        $obj_gen->addJavaScript("templates/main/js/global.js");

        $obj_gen->setTitle("Prails Home");
        $obj_gen->setDescription("To develop web applications at extreme speed");
        $obj_gen->setKeywords(explode(",", "web development,php,framework"));

        $this->str_base = "?";
    }

    function home($arr_param)
    {
        $str_content = Generator::getInstance()->includeTemplate("templates/main/html/home.html", $arr_param);
        return $str_content;
    }
}
?>
