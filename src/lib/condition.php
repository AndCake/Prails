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

class Condition
{
   var $arr_conditions;

   function Condition()
   {
      $this->arr_conditions = Array();
   }

   function addEquals($str_left, $str_right) 
   {
      array_push($this->arr_conditions, array("="=>array($str_left, $str_right)));
   }

   function getClause($obj_db) 
   {
      $str_result = "";
      foreach ($this->arr_conditions as $operation)
      {
         $op = key($operation);
         $arr_parts = $operation[$op];
         $str_result .= $arr_parts[0].$op.$arr_parts[1]." AND ";
      }
      $str_result .= "1 ";
      
      return $str_result;
   }
}

?>
