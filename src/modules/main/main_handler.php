<?php

class MainHandler
{
    var $obj_data;
    var $obj_print;
    var $str_lang;
    var $arr_param;

    function MainHandler($str_lang = "en")
    {
        $this->obj_data = new MainData();
        $this->str_lang = $str_lang;
		
		// clean up any transferred vars in GET request
		$mod = explode(":", $_GET["event"]);
		$result = Array();
		foreach ($_GET as $key => $value) {
			$result[$key] = $value;
			if (strpos($key, "_id") !== false) {
				if (strpos($mod[1], str_replace("_id", "", $key)) !== false) {
					$result[strtolower($key[0]).substr($key, 1)] = $value;
				}
			}
		}
		$_GET = $result;
        
		$obj_gen = Generator::getInstance();
        $obj_gen->setLanguage($this->str_lang);
        $this->obj_print = new MainPrinter($this->str_lang);
    }

    function home()
    {
		/** BEGIN_CODE **/
   $arr_param = Array(
      "modules" => $this->obj_data->listModules()
   );
   return $this->_callPrinter("home", $arr_param);
  



/** END_CODE **/
    }

   	/**
   	 * @desc calls the corresponding method in printer
   	 * @param $str_func [STRING]   function to call
   	 * @param $arr_param [ARRAY]   some data that may be needed
   	 * @returns [BOOLEAN]    TRUE if call successful, else FALSE
   	 */
   	function _callPrinter ($str_func, $arr_param)
   	{
   	    if (method_exists($this->obj_print, $str_func))
   	    {
   	        return $this->obj_print->$str_func($arr_param);
   	    } else
   	    {
   	        $error = "Could not call ".$str_func." in MainPrinter.";
   	        pushError($error);

   	        return false;
   	    }
   	}
}

?>
