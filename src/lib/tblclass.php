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

class TblClass { ///////////////////////////////////////////////////////////////

   // Member variables ////////////////////////////////////////////////////////
   ////////////////////////////////////////////////////////////////////////////


   // public //////////////////////////////////////////////
   var $int_affectedId;
   var $int_affectedRows;
   var $bol_dumpSqlQuery = DEBUG_MYSQL;
   var $obj_mysql = null;
   var $bol_cache = true;
   var $str_prefix = null;

   // constructor /////////////////////////////////////////////////////////////
   ////////////////////////////////////////////////////////////////////////////

   function TblClass ($prefix = "tbl_") { ////////////////////////////////////////////////
   		// connect to mysql database
		$this->str_prefix = $prefix;
		$this->obj_mysql = call_user_func(Array(DB_TYPE, "getInstance"), $prefix);
   	
   } // end TblClass ////////////////////////////////////////////////////

    function setCache($bol_cache) {
        $this->bol_cache = $bol_cache;
    }
    
    function escape($str) {
    	return $this->obj_mysql->escape($str);
    }
 
   
   /**
    * An SQL-SELECT is executed on the specified tables
    *
    * @param ARRAY $arr_tables list of tables (for LEFT JOINs the array's entry is itself an array where the first field if the table's name and the second field the ON condition)
    * @param STRING $str_where WHERE-Clause condition (default value is "1") OPTIONAL
    * @param STRING $str_id name of the field the array should be sorted by OPTIONAL
    * @param STRING $str_selection selection part of the query (default is "*"). OPTIONAL
    * @return ARRAY
    */
   function SelectQuery ($arr_tables = array(), $str_where = "1", $str_id = "", $str_selection = "*") {
      $str_tables = "";
      $str_joins = "";
      foreach ($arr_tables as $mix_table) {
          if (is_array($mix_table)) {
             if ($mix_table[1]) {
                
                $str_joins .= "LEFT JOIN ".$mix_table[0]." ON ".$mix_table[1]." ";
             }
          } else {
            $str_tables .= $mix_table.", ";
          }
      }
      if (strlen($str_tables) > 0) {
         $str_tables = substr($str_tables, 0, -2);    // ", " abhacken
      }
      $str_tables .= $str_joins;
      $arr_result = $this->SqlQuery ( "SELECT ".$str_selection." FROM ".$str_tables." WHERE (".$str_where.")" );
      
      $arr_return = array();
      if ($str_id != "") {
         foreach ($arr_result as $arr_entry) {
            $arr_return[$arr_entry[$str_id]] = $arr_entry;
         }
      } else {
         $arr_return = $arr_result;
      }
      
      return $arr_return;
   }
   
   /**
    * Inserts a tupel into the specified table.
    *
    * Example:
    * <code>
    * 	$id = $this->InsertQuery("tbl_test", Array(
    * 		"first_name"=>"Test",
    * 		"last_name"=>"User",
    * 		"email"=>"tester@example.org"
    * 	));
    * </code>
    *
    * @param STRING $str_table table's name
    * @param ARRAY $arr_data data to be inserted
    *
    * @return INT ID of the inserted tupel
    */
   function InsertQuery ($str_table, $arr_data, $bol_enclose=true, $bol_escape = true) {
      $arr_columns = $this->obj_mysql->listColumns($str_table);

      $str_query = "INSERT INTO ".$str_table." (";
	  $fields = Array();
	  $values = Array();
      foreach ($arr_columns as $arr_col) {
         if (isset($arr_data[$arr_col["Field"]])) {        	
/*
         	 if (preg_match("/[^\\\]'/", $arr_data[$arr_col["Field"]]) && $bol_escape && $bol_enclose) {
         		 $arr_data[$arr_col["Field"]] = addslashes($arr_data[$arr_col["Field"]]);
         	 }*/         	
			 array_push($fields, $arr_col["Field"]);
			 array_push($values, ($bol_escape?$this->obj_mysql->escape($arr_data[$arr_col["Field"]]):$arr_data[$arr_col["Field"]]));
         }
      }
      $str_query .= implode(", ", $fields) . ") VALUES ('" . implode("', '", $values)."')";
      $this->SqlQuery ( $str_query );
      $this->remoteSqlQuery($str_query);
      
      return $this->int_affectedId;
   }

   /**
    * Update of one or more tupel.
    *
    * @param STRING $str_table table's name
    * @param ARRAY $arr_data data to be updated
    * @param STRING $str_where WHERE condition for deciding what to update (OPTIONAL)
    *
    * @return INT number of tupel changed
    */
   function UpdateQuery ($str_table, $arr_data, $str_where = "1", $bol_enclose=true) {
      $arr_columns = $this->obj_mysql->listColumns($str_table);

      $str_query = "UPDATE ".$str_table." SET ";
	  $i = 0;
      foreach ($arr_columns as $arr_col) {
         if (isset($arr_data[$arr_col["Field"]])) {
         	$i++;
         	if ($bol_enclose) {
         		if (preg_match("/[^\\\]'/", $arr_data[$arr_col["Field"]])) {
	         		$arr_data[$arr_col["Field"]] = $this->obj_mysql->escape($arr_data[$arr_col["Field"]]);
	         	}
	            $str_query .= $arr_col["Field"]."='".$arr_data[$arr_col["Field"]]."', ";
         	} else {
         		$str_query .= $arr_col["Field"]."=".$arr_data[$arr_col["Field"]].", ";
         	}
         }
      }
	  // nothing needs to be updated... got no data.
	  if ($i == 0) return 0;
      $str_query = substr($str_query, 0, -2);     // ", " abhacken

      $str_query .= " WHERE (".$str_where.")";

      $this->SqlQuery ( $str_query );
      
      $this->remoteSqlQuery($str_query);
      
      return $this->int_affectedRows;
   }

   /**
    * Deletes one or more tupel from a table
    *
    * @param STRING $str_table table's name
    * @param STRING $str_where WHERE condition
    */
   function DeleteQuery ($str_table, $str_where = "0") {
      $str_query = "DELETE FROM ".$str_table." WHERE (".$str_where.")";
      
      $this->SqlQuery($str_query);
      
      $this->remoteSqlQuery($str_query);
            
      return;
   }


   /**
    * executes a SQL query
    *
    * @param STRING $str_sqlString SQL query string to be executed
    *
    * @return ARRAY result of the SQL query
    */
	function SqlQuery ($str_sqlString, $bol_cache=true) { //////////////////////////////

      	// dump query if needed
    	if ($this->bol_dumpSqlQuery!=0) print ($str_sqlString."<br/>");
    	$this->obj_mysql->setPrefix($this->str_prefix);
    	$arr_result = $this->obj_mysql->query($str_sqlString, ($this->bol_cache?DB_CACHE_TTL:0));
    	$this->int_affectedId = $this->obj_mysql->int_affectedId;
    	$this->int_affectedRows = $this->obj_mysql->int_affectedRows;
    	
    	return $arr_result;
	} // end SqlQuery //////////////////////////////////////////////////////////

    function remoteSqlQuery($str_query) {
        global $arr_dbs;
      
        $arr_dbList = Array();
      
        foreach ($arr_dbs as $title=>$arr_db) {
            array_push($arr_dbList, $arr_db);
        }
      
        if (count($arr_dbList) > 1) {
          	for ($i = 1; $i < count($arr_dbList); $i++) {
          	    $str_sqlString = $str_query;
                if (is_array($arr_dbList[$i]["table_overrides"])) {
    	            foreach ($arr_dbList[$i]["table_overrides"] as $table=>$newTable) {
    	      	        $str_sqlString = str_replace(" ".$table." ", " ".$newTable." ", $str_sqlString);
    	            }
                }
          	    // add to todo-query_list
          	    $str_sqlString .= "\n";
          	    $str_sqlString = "~".$str_sqlString;
          	    $fp = @fopen("open_queries_".$i.".sql", "a+");
          	    @fwrite($fp, $str_sqlString);
          	    @fclose($fp);
          	    @chmod("open_queries_".$i.".sql", 0666);
          	}
        }
    }
} // end class TblClass //////////////////////////////////////////////////


?>
