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

class MainData extends Database
{
   function MainData()
   {
      parent::Database();
   }
   
	function listModules($uid = -1) {
		return $this->query("SELECT * FROM tbl_prailsbase_module WHERE fk_user_id=%1 ORDER BY name ASC", $uid);
	}
}
?>