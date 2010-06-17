<?php

class EmptyPrinter
{
   var $obj_lang;
   var $str_base;
   
   function EmptyPrinter($str_lang)
   {
      $this->obj_lang = new LangData("empty", $str_lang);
      $obj_gen = Generator::getInstance();
      $obj_gen->addStyleSheet("templates/empty/css/empty.css");
	  $obj_gen->addJavaScript("templates/empty/js/empty.js");
      $obj_gen->str_template = "templates/template.html";
      $this->str_base = "?module=empty";
   }
   
   /*<PRINTER-METHODS>*/
   /*</PRINTER-METHODS>*/
}

?>
