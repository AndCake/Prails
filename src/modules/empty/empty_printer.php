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

class EmptyPrinter
{
   var $obj_lang;
   var $str_base;
   
   function EmptyPrinter($str_lang)
   {
      $this->obj_lang = new LangData("empty", $str_lang);
      $obj_gen = Generator::getInstance();
      $obj_gen->addStyleSheet("templates/empty/css/empty.css");
	  /*<CSS-INCLUDES>*/
	  /*</CSS-INCLUDES>*/
	  $obj_gen->addJavaScript("templates/empty/js/empty.js");
	  /*<JAVASCRIPT-INCLUDES>*/
	  /*</JAVASCRIPT-INCLUDES>*/
      $obj_gen->str_template = "templates/template.html";
      $this->str_base = "?module=empty";
   }
   
   /*<PRINTER-METHODS>*/
   /*</PRINTER-METHODS>*/
}

?>
