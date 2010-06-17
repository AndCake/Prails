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

/**
* @desc Abstraction layer for database access
*/
class Database extends TblClass implements AbstractDatabase {
   
   function Database() 
   {
      parent::TblClass();
   }
   
   function select ($arr_entities, $obj_condition = null, $arr_view = null, $group=null, $int_limit = null)
   {
      $str_view = "";
      if (is_array($arr_view)) 
      {
         foreach ($arr_view as $str_entry)
         {
            $str_view .= $str_entry.", ";
         }
         $str_view = substr($str_view, 0, -2);
      } else
      {
         $str_view = "*";
      }
      
      $str_tables = "";
      foreach ($arr_entities as $arr_entity)
      {
         $str_tables .= $arr_entity.", ";
      }
      $str_tables = substr($str_tables, 0, -2);
      $str_where = $obj_condition->getClause($this);
      $arr_result = $this->SqlQuery("SELECT ".$str_view." FROM ".$str_tables." WHERE ".$str_where." ".($group!=null?"GROUP BY ".$group:"")."".$int_limit);
      
      return $arr_result;
   }
   
   function delete($str_entity, $obj_condition)
   {
      $str_query = "DELETE FROM ".$str_entity." WHERE ".$obj_condition->getClause($this);
      $this->SqlQuery($str_query);
   }
   
   function insert($str_entity, $arr_data) 
   {
      $this->InsertQuery($str_entity, $arr_data);
   }
   
   function update($str_entity, $obj_condition, $arr_data)
   {
      $this->UpdateQuery($str_entity, $arr_data, $obj_condition->getClause($this));
   }
}

?>
