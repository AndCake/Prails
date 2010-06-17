<?php

class BuilderData extends Database
{
    function BuilderData()
    {
        parent::Database();
    }

    /*<DB-METHODS>*/

    // module
    function listModulesFromUser($user_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_module." WHERE fk_user_id='".$user_id."' ORDER BY name");
    }

    function selectModuleByUserAndName($user_id, $name)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_module." WHERE fk_user_id='".$user_id."' AND name='".$name."' ORDER BY name"));
    }

    function selectHandlerByNameAndModule($module_id, $event)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_handler." WHERE fk_module_id='".$module_id."' AND event='".$event."' ORDER BY event"));
    }

    function listHandlerFromModule($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_handler." WHERE fk_module_id='".$module_id."' ORDER BY event");
    }

    function listDataFromModule($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_data." WHERE fk_module_id='".$module_id."' ORDER BY name");
    }

    function selectModule($module_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_module." WHERE module_id='".$module_id."'"));
    }

    function insertModule($arr_data)
    {
        return $this->InsertQuery(tbl_module, $arr_data);
    }

    function updateModule($module_id, $arr_data)
    {
        return $this->UpdateQuery(tbl_module, $arr_data, "module_id='".$module_id."'");
    }

    function deleteModule($module_id)
    {
        return $this->DeleteQuery(tbl_module, "module_id='".$module_id."'");
    }

    // handler
    function listHandlers($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_handler." WHERE fk_module_id='".$module_id."' ORDER BY event");
    }

    function selectHandler($handler_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_handler." WHERE handler_id='".$handler_id."'"));
    }

    function insertHandler($arr_data)
    {
        return $this->InsertQuery(tbl_handler, $arr_data);
    }

    function updateHandler($handler_id, $arr_data)
    {
        return $this->UpdateQuery(tbl_handler, $arr_data, "handler_id='".$handler_id."'");
    }

    function deleteHandler($handler_id)
    {
        return $this->DeleteQuery(tbl_handler, "handler_id='".$handler_id."'");
    }
	
	function deleteHandlerFromModule($module_id) {
		return $this->DeleteQuery(tbl_handler, "fk_module_id='".$module_id."'");
	}

    // data
    function listDatas($module_id)
    {
        return $this->SqlQuery("SELECT * FROM ".tbl_data." WHERE fk_module_id='".$module_id."' ORDER BY name");
    }

    function selectData($data_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_data." WHERE data_id='".$data_id."'"));
    }

    function insertData($arr_data)
    {
        return $this->InsertQuery(tbl_data, $arr_data);
    }

    function updateData($data_id, $arr_data)
    {
        return $this->UpdateQuery(tbl_data, $arr_data, "data_id='".$data_id."'");
    }

    function deleteData($data_id)
    {
        return $this->DeleteQuery(tbl_data, "data_id='".$data_id."'");
    }

	function deleteDataFromModule($module_id) {
		return $this->DeleteQuery(tbl_data, "fk_module_id='".$module_id."'");
	}	

    // library
    function listLibrariesFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_library WHERE fk_user_id='".$id."'");
    }

    function selectLibrary($library_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_library." WHERE library_id='".$library_id."'"));
    }

    function insertLibrary($arr_library)
    {
        return $this->InsertQuery(tbl_library, $arr_library);
    }

    function updateLibrary($library_id, $arr_library)
    {
        return $this->UpdateQuery(tbl_library, $arr_library, "library_id='".$library_id."'");
    }

    function deleteLibrary($library_id)
    {
        return $this->DeleteQuery(tbl_library, "library_id='".$library_id."'");
    }

    // tag
    function listTagsFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_tag WHERE fk_user_id='".$id."'");
    }

    function selectTag($tag_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_tag." WHERE tag_id='".$tag_id."'"));
    }

    function insertTag($arr_tag)
    {
        return $this->InsertQuery(tbl_tag, $arr_tag);
    }

    function updateTag($tag_id, $arr_tag)
    {
        return $this->UpdateQuery(tbl_tag, $arr_tag, "tag_id='".$tag_id."'");
    }

    function deleteTag($tag_id)
    {
        return $this->DeleteQuery(tbl_tag, "tag_id='".$tag_id."'");
    }

    // table
    function listTablesFromUser($id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_table WHERE fk_user_id='".$id."'");
    }

    function selectTable($table_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_table." WHERE table_id='".$table_id."'"));
    }

    function insertTable($arr_table)
    {
        return $this->InsertQuery(tbl_table, $arr_table);
    }

    function updateTable($table_id, $arr_table)
    {
        return $this->UpdateQuery(tbl_table, $arr_table, "table_id='".$table_id."'");
    }

    function deleteTable($table_id)
    {
        return $this->DeleteQuery(tbl_table, "table_id='".$table_id."'");
    }

    function listConfigurationFromModule($module_id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_configuration WHERE fk_module_id='".$module_id."'");
    }

    function selectConfiguration($configuration_id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM ".tbl_configuration." WHERE configuration_id='".$configuration_id."'"));
    }

    function insertConfiguration($arr_configuration)
    {
        return $this->InsertQuery(tbl_configuration, $arr_configuration);
    }

    function updateConfiguration($configuration_id, $arr_configuration)
    {
        return $this->UpdateQuery(tbl_configuration, $arr_configuration, "configuration_id='".$configuration_id."'");
    }

    function deleteConfiguration($configuration_id)
    {
        return $this->DeleteQuery(tbl_configuration, "configuration_id='".$configuration_id."'");
    }
	
	function clearConfiguration($module_id) 
	{
		return $this->DeleteQuery(tbl_configuration, "fk_module_id='".$module_id."'");	
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
        $this->insertHistory("tbl_module_history", $module_id, $arr_old, $arr_new);
    }
    function listModuleHistory($module_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_module_history WHERE fk_original_id='".$module_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(style_code) OR NOT ISNULL(js_code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_module_history WHERE fk_original_id='".$module_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(style_code) OR NOT ISNULL(js_code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertHandlerHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_handler_history", $module_id, $arr_old, $arr_new);
    }
    function listHandlerHistory($handler_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_handler_history WHERE fk_original_id='".$handler_id."' AND (fk_module_id>0 OR NOT ISNULL(event) OR NOT ISNULL(code) OR NOT ISNULL(html_code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_handler_history WHERE fk_original_id='".$handler_id."' AND (fk_module_id>0 OR NOT ISNULL(event) OR NOT ISNULL(code) OR NOT ISNULL(html_code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertDataHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_data_history", $module_id, $arr_old, $arr_new);
    }
    function listDataHistory($data_id)
    {
		$arr_days = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_data_history WHERE fk_original_id='".$data_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(code)) GROUP BY FLOOR(change_time / 86400) ORDER BY change_time DESC LIMIT 0,30) AS x ORDER BY x.change_time ASC");
		if (count($arr_days) > 0) {
			$last = $arr_days[count($arr_days) - 1];
			$arr_last = $this->SqlQuery("SELECT * FROM (SELECT * FROM tbl_data_history WHERE fk_original_id='".$data_id."' AND (fk_module_id>0 OR NOT ISNULL(name) OR NOT ISNULL(code)) AND change_time > ".$last["change_time"]." ORDER BY change_time DESC LIMIT 0,20) AS x ORDER BY x.change_time ASC");
			@array_pop($arr_last);
		} else $arr_last = Array();
        return array_merge($arr_days, $arr_last);
    }
    function insertLibraryHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_library_history", $module_id, $arr_old, $arr_new);
    }
    function insertConfigurationHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_configuration_history", $module_id, $arr_old, $arr_new);
    }
    function insertTagHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_tag_history", $module_id, $arr_old, $arr_new);
    }
    function insertTableHistory($module_id, $arr_old, $arr_new)
    {
        $this->insertHistory("tbl_table_history", $module_id, $arr_old, $arr_new);
    }

    function listResources($module_id)
    {
        return $this->SqlQuery("SELECT * FROM tbl_resource WHERE fk_module_id='".$module_id."'");
    }
    function selectResource($id)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM tbl_resource WHERE resource_id='".$id."'"));
    }
    function selectResourceByName($module_id, $name)
    {
        return @array_pop($this->SqlQuery("SELECT * FROM tbl_resource WHERE fk_module_id='".$module_id."' AND name='".$name."'"));
    }
    function insertResource($arr_data)
    {
        return $this->InsertQuery("tbl_resource", $arr_data);
    }
    function updateResource($resource_id, $arr_data)
    {
        $this->UpdateQuery("tbl_resource", $arr_data, "resource_id='".$resource_id."'");
    }
    function deleteResource($resource_id)
    {
        $this->DeleteQuery("tbl_resource", "resource_id='".$resource_id."'");
    }
	
	function findHandlerByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('h_', handler_id) AS id, CONCAT(event, ' (Event)') AS name, 'Event' AS type FROM tbl_handler AS a, tbl_module AS b WHERE event LIKE '%".$name."%' AND a.fk_module_id=module_id AND fk_user_id='".$uid."'");
	}
	function findDataByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('d_', data_id) AS id, CONCAT(a.name,' (Data Query)') AS name, 'Data Query' AS type FROM tbl_data AS a, tbl_module AS b WHERE a.name LIKE '%".$name."%' AND a.fk_module_id=module_id AND fk_user_id='".$uid."'");
	}
	function findLibByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('l_', library_id) AS id, CONCAT(a.name,' (Library)') AS name, 'Library' AS type FROM tbl_library AS a, tbl_module AS b WHERE a.name LIKE '%".$name."%' AND ((a.fk_module_id=module_id AND b.fk_user_id='".$uid."') OR (a.fk_user_id='".$uid."')) GROUP BY library_id");
	}
	function findTagByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('t_', tag_id) AS id,  CONCAT(name,' (Tag)') AS name, 'Tag' AS type FROM tbl_tag WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
	}
	function findModuleByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('m_', module_id) AS id,  CONCAT(name,' (Module)') AS name, 'Module' AS type FROM tbl_module WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
	}
	function findTableByName($name, $uid) {
		return $this->SqlQuery("SELECT CONCAT('db_', table_id) AS id,  CONCAT(name,' (Database Table)') AS name, 'Database Table' AS type FROM tbl_table WHERE name LIKE '%".$name."%' AND fk_user_id='".$uid."'");
	}
    /*</DB-METHODS>*/
}

?>
