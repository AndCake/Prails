<?PHP
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

class DBDeployer {
	static function loadClasses($arr_db) {
		include_once 'conf/database/dbaccess.php';
		foreach ($arr_db as $table=>$arr_table) {
			include_once 'conf/database/'.$table.'.php';		
			include_once 'conf/database/'.$table.'_list.php';		
		}
		
		DBDeployer::loadState($arr_db);
		
		register_shutdown_function(array("DBDeployer", "saveState"), $arr_db);
	}
	
	static function saveState($arr_db) {
		foreach ($arr_db as $table=>$arr_table) {
			$list = explode("_", $table);
			$table = "";
			foreach ($list as $entry) {
				$table .= ucfirst($entry);
			}
			$obj = "return ".$table."List::getInstance();";
			$value = serialize(eval($obj));
			$fp = fopen(RAMFS_PATH.$table.".tmp", "wb+");
			// acquire exclusive writing lock
			$startTime = microtime();
			do {
				$canWrite = flock($fp, LOCK_EX);
				if (!$canWrite) usleep(round(rand(0,100)*1000));
			} while ((!$canWrite) && ((microtime()-$startTime) < 1000));
			if ($canWrite) {
				fwrite($fp, $value);
			}
			// release lock
			flock($fp, LOCK_UN);
			fclose($fp);
		}
	}
	
	static function loadState($arr_db) {
		foreach ($arr_db as $table=>$arr_table) {
			$list = explode("_", $table);
			$table = "";
			foreach ($list as $entry) {
				$table .= ucfirst($entry);
			}
			if (file_exists(RAMFS_PATH.$table.".tmp")) 
			{
				$value = implode("", file(RAMFS_PATH.$table.".tmp"));
				$obj = unserialize($value);
			}
		}		
	}
	
	static function createObjects($arr_db) {
		$path = "conf/database/";		
      	foreach ($arr_db as $table=>$arr_table)
      	{
            $list = explode("_", $table);
            $cname = "";
            foreach ($list as $entry) {
            	$cname .= ucfirst($entry);
            }
            $file = $path.$table.".php";
            $contents = "<?php\nclass ".$cname." {\n";
            $attribs = "";
            $getters = "";
            $setters = "";
            $convert = "";
            $loading = "";
            $attribs .= "	private \$id = 0;\n";
            $getters .= "	function getId() {\n";
            $getters .= "		return \$this->id;\n";
            $getters .= "	}\n";
            $setters .= "	function setId(\$value) {\n";
            $setters .= "		\$this->id = \$value;\n";
            $setters .= "	}\n";
            $convert .= "		\$arr_result[\"".$table."_id\"] = \$this->getId();\n";
            $index = Array("id");
            foreach ($arr_table as $key=>$value) {
	            $list = explode("_", $key);
	            $name = "";
	            $flag = false;
	            foreach ($list as $entry) {
					if ($entry == "flag") {
						$name .= "is";
						$flag = true;
						continue;
					}
	            	$name .= ucfirst($entry);
	            }
	            if (preg_match("/fk_(.*)_id/", $key, $found)) {
	            	$list = explode("_", $found[1]);
	            	$found[1] = "";
	            	foreach ($list as $entry) {
	            		$found[1] .= ucfirst($entry);
	            	}	            	
	            	array_push($index, $found[1]);
		            $loading .= "			if (\$key == \"".$key."\") {\n";
		            $loading .= "				\$this->".$found[1]." = ".ucfirst($found[1])."List::getInstance()->get(\$value);\n";
		            $loading .= "				continue;\n";
		            $loading .= "			}\n";
	            	$attribs .= "     private \$".$found[1]." = null;\n";
		            $getters .= "	function get".ucfirst($found[1])."() {\n";
		            $getters .= "		return \$this->".$found[1].";\n";
		            $getters .= "	}\n";
		            $setters .= "	function set".ucfirst($found[1])."(\$value) {\n";
		            $setters .= "		\$this->".$found[1]." = \$value;\n";
		            $setters .= "	}\n";
		            $convert .= "		\$arr_result[\"".$key."\"] = (\$this->".$found[1]." != null?\$this->".$found[1]."->getId():0);\n";
	            } else if (strpos($value, "COLLECTION") !== false) {
	            	$type = str_replace("_COLLECTION", "", $value);
	            	if ($type == "COLLECTION") $type = $name;
	            	$list = explode("_", $type);
	            	$type = "";
	            	foreach ($list as $entry) {
	            		$type .= ucfirst($entry);
	            	}
					$getters .= "	function get".$name."() {\n";
					$getters .= "		return ".ucfirst($type)."List::getInstance()->getAllWith(\n";
					$getters .= "			create_function(\n";
					$getters .= "				'\$obj',\n";
					$getters .= "				'if((\$obj->get".ucfirst($table)."() != null) && \$obj->get".ucfirst($table)."()->getId() == '.\$this->id.')return true;else return false;'\n";
					$getters .= "			)\n";
					$getters .= "		);\n";
					$getters .= "	}\n";					
	            } else {
	            	$attribs .= "     private \$".$key." = null;\n";
	            	if ($flag) {
		            	$getters .= "	function ".$name."() {\n";
	            	} else {
		            	$getters .= "	function get".$name."() {\n";
	            	}
		            $getters .= "		return \$this->".$key.";\n";
		            $getters .= "	}\n";
		            if ($flag) {
		            	$setters .= "	function set".ucfirst($name)."(\$value) {\n";
		            } else {
						$setters .= "	function set".$name."(\$value) {\n";
		            }
		            $setters .= "		\$this->".$key." = \$value;\n";
		            $setters .= "	}\n";
		            $convert .= "		\$arr_result[\"".$key."\"] = \$this->".$key.";\n";
	            } 
            }
            $contents .= $attribs;
            $contents .= "\n   function __construct(\$arr_data) {\n";
            $contents .= "	     \$this->load(\$arr_data);\n";
            $contents .= "   }\n";
            $contents .= "\n".$getters.$setters;
            $contents .= "\n	 function load(\$arr_data) {\n";
            $contents .= "		\$this->id = \$arr_data[\"".$table."_id\"];\n";
            $contents .= "		if (is_array(\$arr_data)) foreach (\$arr_data as \$key=>\$value) {\n";
			$contents .= $loading;
            $contents .= "			\$this->\$key = \$value;\n";
            $contents .= "	 	}\n";
            $contents .= "	}\n";
            $contents .= "\n	function toArray() {\n";
            $contents .= "		\$arr_result = Array();\n";
            $contents .= $convert;
            $contents .= "		return \$arr_result;\n";
            $contents .= "	 }\n";
            $contents .= "}\n?>";
            file_put_contents($file, $contents);

            $file = $path.$table."_list.php";
            $contents = "<?php\nclass ".$cname."List extends DBAccessList {\n";
			$contents .= "   static \$instance = null;\n";
            $contents .= "   function __construct(\$arr_data = Array()) {\n";
            $contents .= "	     parent::__construct(\"".$table."\", Array(\"".implode(",\"", explode(",",implode("\",", $index)))."\"), \$arr_data);\n";
            $contents .= "   }\n";
      		$contents .= "	 static function getInstance(\$arr_data = Array()) {\n";
			$contents .= "		 if (".$cname."List::\$instance == null) {\n";
			$contents .= "		    ".$cname."List::\$instance = new ".$cname."List(\$arr_data);\n";
			$contents .= "		 }\n";
			$contents .= "		 return ".$cname."List::\$instance;\n";
			$contents .= "   }\n";
            $contents .= "}\n?>";
            file_put_contents($file, $contents);
		}
	}
	
	static function deploy($arr_db, $table_prefix = "tbl_")
	{
		$obj_db = new TblClass($table_prefix);

		if (FIRST_RUN) {
	  		$cnt = file_get_contents("conf/configuration.php");
			$pre = substr($cnt, 0, strpos($cnt, "/*<CUSTOM-SETTINGS>*/")+strlen("/*<CUSTOM-SETTINGS>*/")+1);
			$post = substr($cnt, strpos($cnt, "/*</CUSTOM-SETTINGS>*/")-1);
			$settings = substr($cnt, strpos($cnt, "/*<CUSTOM-SETTINGS>*/")+strlen("/*<CUSTOM-SETTINGS>*/")+1);
			$settings = substr($settings, 0, strpos($settings, "/*</CUSTOM-SETTINGS>*/"));
			$lines = explode("\n", trim($settings));
			$bol_found = false;
			foreach ($lines as &$line) {
				if (strpos($line, "FIRST_RUN")) {
					$line = "\"FIRST_RUN\" => false,";
					$bol_found = true;
					break;
				}
			}
			if (!$bol_found) {
				array_push($lines, "\"FIRST_RUN\" => false,");
			}
			@file_put_contents("conf/configuration.php", $pre.implode("\n", $lines).$post);
		}
      
	    foreach ($arr_db as $table=>$arr_table)
	    {
	        if (strpos($table, "_key") > 0) 
	            $pk = str_replace("_key", "_id", $table); 
	        else
	            $pk = $table."_id";
	            
	        $str_query = "CREATE TABLE IF NOT EXISTS ".$table_prefix.$table." (".$pk." ".$obj_db->obj_mysql->constructs["pk"].", ";
	        $bol_exists = $obj_db->obj_mysql->tableExists($table_prefix.$table);
	        if ($bol_exists) $arr_fields = $obj_db->obj_mysql->listColumns($table_prefix.$table);
	        foreach ($arr_table as $key=>$value)
	        {
	            if (strpos($value, "_COLLECTION") !== false) continue;		// ignore keys with type COLLECTION
	         	if ($bol_exists) 
	            {
	               $int_isIn = -1;
	               foreach ($arr_fields as $id=>&$arr_field) 
	               {
	                  if ($arr_field["Field"] == $key) {
	                  	$int_isIn = $id;
						$arr_field["isIn"] = true;
					  }
	               }
	               if ($int_isIn >= 0) 
	               {
	                  if (strtoupper($arr_fields[$int_isIn]["Type"]) != strtoupper($value)) 
	                  {
	                     $obj_db->SqlQuery("ALTER TABLE ".$table_prefix.$table." CHANGE COLUMN ".$key." ".$key." ".$value);
	                  }
	               } else if ($key != $pk)
	               {
	                  $obj_db->SqlQuery("ALTER TABLE ".$table_prefix.$table." ADD COLUMN ".$key." ".$value);
	               }
	            } else
	            {
	            	if (strpos($value, "NOT NULL") !== false) {
	            		$value .= " DEFAULT 0";
	            	}
	               	$str_query .= $key." ".$value.", ";
	            }
	        }
			if (is_array($arr_fields)) foreach ($arr_fields as $arr_field) {
			 	if (!$arr_field["isIn"] && $arr_field["Field"] != $pk) {
			 		$obj_db->SqlQuery("ALTER TABLE ".$table_prefix.$table." DROP COLUMN ".$arr_field["Field"]);
				}
			}
	        if (!$bol_exists) 
	        {
	            $str_query = substr($str_query, 0, -2).")";
	            $obj_db->SqlQuery($str_query);
	        }        
		}
	}
}

?>
