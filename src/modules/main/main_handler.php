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
