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

class BuilderData extends Database
{
    function BuilderData()
    {
        parent::Database("tbl_prailsbase_");
    }

    /*<DB-METHODS>*/

    // module
    function listModulesFromUser($user_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_prailsbase_module." WHERE fk_user_id='".$user_id."' ORDER BY name");
    }

    function selectModuleByUserAndName($user_id, $name, $fs = false)
    {
    	if ($fs) $nq = "LOWER(name)"; else $nq = "name";
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_module." WHERE fk_user_id='".$user_id."' AND ".$nq."='".$name."' ORDER BY name"));
    }

    function selectHandlerByNameAndModule($module_id, $event)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_handler." WHERE fk_module_id='".$module_id."' AND event='".$event."' ORDER BY event"));
    }

    function listHandlerFromModule($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_prailsbase_handler." WHERE fk_module_id='".$module_id."' ORDER BY event");
    }

    function listDataFromModule($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_prailsbase_data." WHERE fk_module_id='".$module_id."' ORDER BY name");
    }

    function selectModule($module_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_module." WHERE module_id='".$module_id."'"));
    }

    function insertModule($arr_data)
    {
        return $this->InsertQuery(tbl_prailsbase_module, $arr_data);
    }

    function updateModule($module_id, $arr_data)
    {
        return $this->UpdateQuery(tbl_prailsbase_module, $arr_data, "module_id='".$module_id."'");
    }

    function deleteModule($module_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_module, "module_id='".$module_id."'");
    }

    // handler
    function listHandlers($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_prailsbase_handler." WHERE fk_module_id='".$module_id."' ORDER BY event");
    }

    function selectHandler($handler_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_handler." WHERE handler_id='".$handler_id."'"));
    }

    function insertHandler($arr_data)
    {
    	return $this->InsertQuery(tbl_prailsbase_handler, $arr_data);
    }

    function updateHandler($handler_id, $arr_data)
    {
    	return $this->UpdateQuery(tbl_prailsbase_handler, $arr_data, "handler_id='".$handler_id."'");
    }

    function deleteHandler($handler_id)
    {
    	return $this->DeleteQuery(tbl_prailsbase_handler, "handler_id='".$handler_id."'");
    }
	
	function deleteHandlerFromModule($module_id) {
		return $this->DeleteQuery(tbl_prailsbase_handler, "fk_module_id='".$module_id."'");
	}
	
	function selectDecoratorEventsFromUser($user) {
	   return $this->SqlQuery("SELECT *, CONCAT(m.name, ':', h.event) AS name FROM tbl_prailsbase_handler AS h, tbl_prailsbase_module AS m WHERE h.fk_module_id=module_id AND m.fk_user_id='".$user."' AND (h.html_code LIKE '%<!--[content]-->%' OR h.html_code LIKE '%<c:body%/>%')");
	}

    // data
    function listDatas($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_prailsbase_data." WHERE fk_module_id='".$module_id."' ORDER BY name");
    }

    function selectData($data_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_data." WHERE data_id='".$data_id."'"));
    }
	
	function getDataFromName($name, $module_id) {
		return @array_pop($this->SqlQuery("SELECT * FROM tbl_prailsbase_data WHERE name='".$name."' AND fk_module_id='".$module_id."'"));
	}

    function insertData($arr_data)
    {
        return $this->InsertQuery(tbl_prailsbase_data, $arr_data);
    }

    function updateData($data_id, $arr_data)
    {
        return $this->UpdateQuery(tbl_prailsbase_data, $arr_data, "data_id='".$data_id."'");
    }

    function deleteData($data_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_data, "data_id='".$data_id."'");
    }

	function deleteDataFromModule($module_id) {
		return $this->DeleteQuery(tbl_prailsbase_data, "fk_module_id='".$module_id."'");
	}	

    // library
    function listLibrariesFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_prailsbase_library WHERE fk_user_id='".$id."'");
    }
    
    function selectLibraryByUserAndName($user, $name) {
    	return @array_pop($this->get("library", Array("fk_user_id" => $user, "name" => $name)));
    }

    function selectLibrary($library_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_library." WHERE library_id='".$library_id."'"));
    }

    function insertLibrary($arr_library)
    {
        return $this->InsertQuery(tbl_prailsbase_library, $arr_library);
    }

    function updateLibrary($library_id, $arr_library)
    {
        return $this->UpdateQuery(tbl_prailsbase_library, $arr_library, "library_id='".$library_id."'");
    }

    function deleteLibrary($library_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_library, "library_id='".$library_id."'");
    }

    // tag
    function listTagsFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_prailsbase_tag WHERE fk_user_id='".$id."'");
    }
    
    function selectTagByUserAndName($user, $name) {
    	return @array_pop($this->SqlQuery("SELECT * FROM tbl_prailsbase_tag WHERE fk_user_id='".$user."' AND name='".$name."'"));
    }

    function selectTag($tag_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_tag." WHERE tag_id='".$tag_id."'"));
    }

    function insertTag($arr_tag)
    {
        return $this->InsertQuery(tbl_prailsbase_tag, $arr_tag);
    }

    function updateTag($tag_id, $arr_tag)
    {
        return $this->UpdateQuery(tbl_prailsbase_tag, $arr_tag, "tag_id='".$tag_id."'");
    }

    function deleteTag($tag_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_tag, "tag_id='".$tag_id."'");
    }

    // table
    function listTablesFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_prailsbase_table WHERE fk_user_id='".$id."'");
    }
    
    function selectTableFromUserAndName($user, $name) {
    	return @array_pop($this->get("table", Array("fk_user_id" => $user, "name" => $name)));
    }

    function selectTable($table_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_table." WHERE table_id='".$table_id."'"));
    }

    function insertTable($arr_table)
    {
        return $this->InsertQuery(tbl_prailsbase_table, $arr_table);
    }

    function updateTable($table_id, $arr_table)
    {
        return $this->UpdateQuery(tbl_prailsbase_table, $arr_table, "table_id='".$table_id."'");
    }

    function deleteTable($table_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_table, "table_id='".$table_id."'");
    }

    function listConfigurationFromModule($module_id, $type = 0)
    {
        return $this->SqlQuery("SELECT * FROM tbl_prailsbase_configuration WHERE fk_module_id='".$module_id."' AND (flag_public=0 OR flag_public='".$type."')");
    }

    function selectConfiguration($configuration_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_prailsbase_configuration." WHERE configuration_id='".$configuration_id."'"));
    }

    function insertConfiguration($arr_configuration)
    {
        return $this->InsertQuery(tbl_prailsbase_configuration, $arr_configuration);
    }

    function updateConfiguration($configuration_id, $arr_configuration)
    {
        return $this->UpdateQuery(tbl_prailsbase_configuration, $arr_configuration, "configuration_id='".$configuration_id."'");
    }

    function deleteConfiguration($configuration_id)
    {
        return $this->DeleteQuery(tbl_prailsbase_configuration, "configuration_id='".$configuration_id."'");
    }
	
	function clearConfiguration($module_id, $type = 0) 
	{
		return $this->DeleteQuery(tbl_prailsbase_configuration, "fk_module_id='".$module_id."' AND (flag_public=0 OR flag_public='".$type."')");	
	}

    // history
    function insertHistory($str_tbl, $id, $arr_old, $arr_new)
    {
        $arr_result = Array();
        foreach ($arr_new as $key=>$value)
        {
            if ($arr_old[$key] != $value)
            {
                if (is_numeric($value))
                {
                    $arr_result[$key] = $value;
                } else
                {
                    $res = diff($arr_old[$key], $value);
                    $arr_result[$key] = base64_encode(serialize($res));
                }
            }
        }

        if (count($arr_result) > 0)
        {
            $arr_result["fk_original_id"] = $id;
            $arr_result["change_time"] = time();
            $this->InsertQuery($str_tbl, $arr_result);
        }
    }

    function insertModuleHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_module_history", $module_id, $arr_old, $arr_new);
    }
    function listModuleHistory($module_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_module_history WHERE fk_original_id='".$module_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(style_code) OR NOT ISNULL(js_code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_module_history WHERE fk_original_id='".$module_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(style_code) OR NOT ISNULL(js_code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertHandlerHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_handler_history", $module_id, $arr_old, $arr_new);
    }
    function listHandlerHistory($handler_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_handler_history WHERE fk_original_id='".$handler_id."' AND (fk_module_id>0 OR NOT ISNULL(event) OR NOT ISNULL(code) OR NOT ISNULL(html_code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_handler_history WHERE fk_original_id='".$handler_id."' AND (fk_module_id>0 OR NOT ISNULL(event) OR NOT ISNULL(code) OR NOT ISNULL(html_code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertDataHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_data_history", $module_id, $arr_old, $arr_new);
    }
    function listDataHistory($data_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_data_history WHERE fk_original_id='".$data_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_prailsbase_data_history WHERE fk_original_id='".$data_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertLibraryHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_library_history", $module_id, $arr_old, $arr_new);
    }
    function insertConfigurationHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_configuration_history", $module_id, $arr_old, $arr_new);
    }
    function insertTagHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_tag_history", $module_id, $arr_old, $arr_new);
    }
    function insertTableHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_prailsbase_table_history", $module_id, $arr_old, $arr_new);
    }

    function listResources($module_id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_prailsbase_resource WHERE fk_module_id='".$module_id."'");
    }
    function selectResource($id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM tbl_prailsbase_resource WHERE resource_id='".$id."'"));
    }
    function selectResourceByName($module_id, $name)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM tbl_prailsbase_resource WHERE fk_module_id='".$module_id."' AND name='".$name."'"));
    }
    function insertResource($arr_data)
    {
        return $this->InsertQuery("tbl_prailsbase_resource", $arr_data);
    }
    function updateResource($resource_id, $arr_data)
    {
        $this->UpdateQuery("tbl_prailsbase_resource", $arr_data, "resource_id='".$resource_id."'");
    }
    function deleteResource($resource_id)
    {
        $this->DeleteQuery("tbl_prailsbase_resource", "resource_id='".$resource_id."'");
    }
    function clearResource($module_id)
    {
        $this->DeleteQuery("tbl_prailsbase_resource", "fk_module_id='".$module_id."'");
    }
    
	function findHandlerByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('h_', handler_id) AS id, CONCAT(event, ' (Event)') AS name, 'Event' AS type FROM tbl_prailsbase_handler AS a, tbl_prailsbase_module AS b WHERE event LIKE '%".$name."%' AND a.fk_module_id=module_id AND fk_user_id='".$uid."'");
		$arr_return = Array();
		foreach ($result as $res) {
			array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
		}
		return $arr_return;
	}
	function findDataByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('d_', data_id) AS id, CONCAT(a.name,' (Data Query)') AS name, 'Data Query' AS type FROM tbl_prailsbase_data AS a, tbl_prailsbase_module AS b WHERE a.name LIKE '%".$name."%' AND a.fk_module_id=module_id AND fk_user_id='".$uid."'");
                $arr_return = Array();
                foreach ($result as $res) {
                        array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
                }
                return $arr_return;
	}
	function findLibByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('l_', library_id) AS id, CONCAT(a.name,' (Library)') AS name, 'Library' AS type FROM tbl_prailsbase_library AS a, tbl_prailsbase_module AS b WHERE a.name LIKE '%".$name."%' AND ((a.fk_module_id=module_id AND b.fk_user_id='".$uid."') OR (a.fk_user_id='".$uid."')) GROUP BY library_id");
                $arr_return = Array();
                foreach ($result as $res) {
                        array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
                }
                return $arr_return;
	}
	function findTagByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('t_', tag_id) AS id,  CONCAT(name,' (Tag)') AS name, 'Tag' AS type FROM tbl_prailsbase_tag WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
                $arr_return = Array();
                foreach ($result as $res) {
                        array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
                }
                return $arr_return;
	}
	function findModuleByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('m_', module_id) AS id,  CONCAT(name,' (Module)') AS name, 'Module' AS type FROM tbl_prailsbase_module WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
                $arr_return = Array();
                foreach ($result as $res) {
                        array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
                }
                return $arr_return;
	}
	function findTableByName($name, $uid) {
		$result = $this->SqlQuery("SELECT CONCAT('db_', table_id) AS id,  CONCAT(name,' (Database Table)') AS name, 'Database Table' AS type FROM tbl_prailsbase_table WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
                $arr_return = Array();
                foreach ($result as $res) {
                        array_push($arr_return, Array("id" => $res["id"], "name" => $res["name"], "type" => $res["type"]));
                }
                return $arr_return;
	}
	
	function listUrlRules() {
		$file = file_get_contents(".htaccess");
		$startMarker = "#--START_CUSTOM--#";
		$endMarker = "#--END_CUSTOM--#";
		$start = strpos($file, $startMarker) + strlen($startMarker);
		$len = (strpos($file, $endMarker, $start) - 1) - $start;
		$area = substr($file, $start, $len);
		preg_match_all('@\s*RewriteRule\s+\^([^\s]+)\$\s+index.php\?([^\s]+)\s+\[.+\]@usix', $area, $matches);
		$arr_result = Array();
		// loop over all found rules
		foreach ($matches[0] as $key => $match) {
			$arr_result[$matches[1][$key]] = $matches[2][$key];
		}
		
		return $arr_result;
	}
	
	function listParametersFromRule($arr_rule) {
		if ($arr_rule == null) return Array();
		$nice = $arr_rule[0];
		$target = $arr_rule[1];
		
		// find out what the delimiter in $nice is...
		preg_match('/\[\^([\/\-.,_]+)\]/', $nice, $match);
		if (strlen($match[1]) > 0) {
			$delimiter = $match[1];
		}
	
		// extract prefix and suffix in $nice for current url (not parameter-wise!)
		preg_match('@^([^(]*)\(@', $nice, $match);
		$prefix = $match[1];
		preg_match('@\]\*\)([^'.$delimiter.']*)$@', $nice, $match);
		// need to filter the suffix somehow (although this might be not possible!)
		$suffix = $match[1];
		
		$arr_result = Array();
		$parts = explode("&", $target);
		$paramNum = 0;
		foreach ($parts as $part) {
			$nv = explode("=", $part);
			if ($nv[0] == "event") continue;
			$entry["name"] = $nv[0];
			$entry["type"] = 0;
			if (strpos($nice, $nv[0].$delimiter) !== false) {
				$entry["type"] += 1;
				if ($paramNum == 0) {
					preg_match('@^([^'.$delimiter.']*)['.$delimiter.']@', str_replace($nv[0], "", $nice), $match);
					$prefix = $match[1];
				}
			}
			if ($nv[1][0] == '$') {
				$entry["type"] += 2;
			} else if ($nv[1].length > 0) {
				$entry["value"] = $nv[1];
			}
			$entry["delimiter"] = $delimiter;
			$entry["prefix"] = $prefix;
			$entry["suffix"] = $suffix;
			
			array_push($arr_result, $entry);
			$paramNum++;
		}
		
		return $arr_result;
	}
	
	function updateUrlRules($arr_data) {
		$file = file_get_contents(".htaccess");
		$startMarker = "#--START_CUSTOM--#";
		$endMarker = "#--END_CUSTOM--#";
		$start = strpos($file, $startMarker) + strlen($startMarker);
		$len = (strpos($file, $endMarker, $start) - 1) - $start;

		$newArea = Array();
		foreach ($arr_data as $target) {
			$newArea[] = "RewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_FILENAME} !-f\n".
						 "RewriteRule ^".$target["nice"]."\$ index.php?".$target["original"]." [L]";
		}
		$file = substr($file, 0, $start)."\n".implode("\n\n", $newArea)."\n".substr($file, $start+$len);
		@file_put_contents(".htaccess", $file);
	}
	
	// TEST CASES
	function listTestcase($module_id = -1) {
		return $this->SqlQuery("SELECT * FROM tbl_prailsbase_testcase WHERE ".($module_id >= 0 ? "fk_module_id='".$module_id."'" : "1"));
	}
	function selectTestcase($testcase_id) {
		return @array_pop($this->SqlQuery("SELECT * FROM tbl_prailsbase_testcase WHERE testcase_id='".$testcase_id."'"));
	}
	function updateTestcase($testcase_id, $arr_data) {
		$this->UpdateQuery("tbl_prailsbase_testcase", $arr_data, "testcase_id='".$testcase_id."'");
	}
	function insertTestcase($arr_data) {
		return $this->InsertQuery("tbl_prailsbase_testcase", $arr_data);
	}
	function deleteTestcase($testcase_id) {
		$this->DeleteQuery("tbl_prailsbase_testcase", "testcase_id='".$testcase_id."'");
	}
	function clearTestcase($module_id) {
		$this->DeleteQuery("tbl_prailsbase_testcase", "fk_module_id='".$module_id."'");
	}
	
    /*</DB-METHODS>*/
}

?>
