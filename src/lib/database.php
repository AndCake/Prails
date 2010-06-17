<?php

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
