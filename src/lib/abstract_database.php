<?php

interface AbstractDatabase
{
   function select($arr_entities, $obj_condition=null, $arr_view=null, $int_limit=null);
   function delete($str_entity, $obj_condition);
   function insert($str_entity, $arr_data);
   function update($str_entity, $obj_condition, $arr_data);
}

?>