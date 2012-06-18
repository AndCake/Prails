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

/**
 * This class is used to deploy database structures. It keeps track of indices, 
 * new columns, column changes, removal of columns and whole tables.
 */
class DBDeployer {

	static function deploy($arr_db, $table_prefix = "tbl_", $indexFields = Array()) {
		$obj_db = new Database($table_prefix);
		foreach ($arr_db as $table=>$arr_table) {
			if (strpos($table, "_key") > 0) 
				$pk = str_replace("_key", "_id", $table); 
			else
				$pk = $table."_id";
	            
			if ($table == "sessions" && $table_prefix == "tbl_prailsbase_") {
				$str_query = "CREATE TABLE IF NOT EXISTS ".$table_prefix.$table." (".$pk." VARCHAR(20) PRIMARY KEY, ";
			} else {
				$str_query = "CREATE TABLE IF NOT EXISTS ".$table_prefix.$table." (".$pk." ".$obj_db->sql->constructs["pk"].", ";
			}
			$bol_exists = $obj_db->sql->tableExists($table_prefix.$table);
			if ($bol_exists) $arr_fields = $obj_db->sql->listColumns($table_prefix.$table);
			foreach ($arr_table as $key=>$value) {
				if (strpos($value, "_COLLECTION") !== false) continue;		// ignore keys with type COLLECTION
				if ($bol_exists) {
					$int_isIn = -1;
					foreach ($arr_fields as $id=>&$arr_field) {
						if ($arr_field["Field"] == $key) {
							$int_isIn = $id;
							$arr_field["isIn"] = true;
						}
					}
					if ($int_isIn >= 0) {
						if (strtoupper($arr_fields[$int_isIn]["Type"]) != strtoupper($value)) {
							$obj_db->query("ALTER TABLE ".$table_prefix.$table." CHANGE COLUMN ".$key." ".$key." ".$value);
						}
					} else if ($key != $pk) {
						if (strpos($value, "NOT NULL") !== false) {
							$value .= " DEFAULT 0";
						}
						$obj_db->query("ALTER TABLE ".$table_prefix.$table." ADD COLUMN ".$key." ".$value);
						if (preg_match('/^fk_[a-zA-Z_0-9\-]+_id$/mi', $key)) {
							$obj_db->query("CREATE INDEX ".$table."_".$key."_index ON ".$table_prefix.$table." (".$key.")");
						}
					}
				} else {
					if (strpos($value, "NOT NULL") !== false) {
						$value .= " DEFAULT 0";
					}
					$str_query .= $key." ".$value.", ";
				}
			}
			if (is_array($arr_fields)) foreach ($arr_fields as $arr_field) {
				if (!$arr_field["isIn"] && $arr_field["Field"] != $pk) {
					$obj_db->query("ALTER TABLE ".$table_prefix.$table." DROP COLUMN ".$arr_field["Field"]);
					if (preg_match('/^fk_[a-zA-Z_0-9\-]+_id$/mi', $arr_field["Field"])) {
						$obj_db->query("DROP INDEX ".$table."_".$arr_field["Field"]."_index");
					}
				}
			}
			if (!$bol_exists) {
				$str_query = substr($str_query, 0, -2).")";
				$obj_db->query($str_query);
	            
	            // create indices for all foreign keys of that table
	            // ...
			}        
		}

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
	}
}
?>