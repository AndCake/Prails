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
 
class AbstractHandler {
 	protected $obj_data = null;
 	protected $obj_print = null;
 	protected $obj_parent = null;
 	protected $obj_lang = null;
 	
 	protected $session = null;
 	
    /**
     * @desc calls the corresponding method in printer
     * @param $str_func [STRING]   function to call
     * @param $arr_param [ARRAY]   some data that may be needed
     * @returns [BOOLEAN]    TRUE if call successful, else FALSE
     */
    public function _callPrinter ($str_func, $arr_param, $decorator = "", $template = "")
    {
        if (method_exists($this->obj_print, $str_func))
        {
            return $this->obj_print->$str_func($arr_param, $decorator, $template);
        } else
        {
            pushError("Could not call ".$str_func." in Printer.");
            return false;
        }
    }
    
    public function registerEvents() {
        // empty method stub for registering any events
    }
    
    public function getSession() {
        if ($this->session == null) $this->session = new Session();
        return $this->session;
    }
 	
 	/**
 	 * retrieves the data object of this module
 	 *
 	 * @return Database		Database access object for this module
 	 */
 	public function getData() {
 		return $this->obj_data;
 	}
 	
 	/**
 	 * retrieves the parent module of this module
 	 *
 	 * @return	AbstractHandler	parent module handler
 	 */
 	public function getParent() {
 		return $this->obj_parent;
 	}
 }
 
?>
