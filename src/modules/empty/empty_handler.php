<?php

class EmptyHandler extends AbstractHandler
{

   function EmptyHandler($str_lang = "en")
   {
      $this->obj_data = new EmptyData();
      $this->str_lang = $str_lang;
      $this->obj_print = new EmptyPrinter($str_lang);
	  $this->obj_parent = $this;		// by default each module is it's own parent
   }
   
   /*<EVENT-HANDLERS>*/
   /*</EVENT-HANDLERS>*/
}

?>