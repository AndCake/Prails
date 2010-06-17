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

interface AbstractDatabase
{
   function select($arr_entities, $obj_condition=null, $arr_view=null, $int_limit=null);
   function delete($str_entity, $obj_condition);
   function insert($str_entity, $arr_data);
   function update($str_entity, $obj_condition, $arr_data);
}

?>
