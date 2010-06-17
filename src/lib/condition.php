<?php

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