<?php
class EmptyData extends Database {

	function EmptyData() {
		parent :: Database();
	}

	/**
	 * list all custom objects that have certain properties
	 * 
	 * Example: 
	 * <code>
	 *    $arr_customObjects = $this->listCustomObjects(Array(
	 *				"name"=>"Test"
	 *                         ));
	 * </code>
	 *
	 * @param ARRAY $arr_cond array of properties to search for (OPTIONAL)
	 * 
	 * @return ARRAY list of matching database tupel
	 */
	function listCustomObjects($arr_cond = Array ()) {
		$str_cond = "%";
		foreach ($arr_cond as $key => $value) {
			$str_cond .= "&" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($key))) . "=" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($value)));
		}
		$str_cond .= "%";

		$arr_result = $this->SqlQuery("SELECT * FROM " . tbl_custom_object . " WHERE fk_module_id='<MODULEID>' AND data LIKE '" . $str_cond . "'");

		$arr_return = Array ();
		foreach ($arr_result as $arr_res) {
			array_push($arr_return, $this->_deserializeCustomObject($arr_res["data"]));
		}

		return $arr_return;
	}

	/**
	 * select one single custom object from database that has certain properties
	 * 
	 * Example: 
	 * <code>
	 *    $arr_customObject = $this->selectCustomObjects(Array(
	 *				"myObject_id"=>"123"
	 *                         ));
	 * </code>
	 *
	 * @param ARRAY $arr_cond array of properties to search for
	 *
	 * @return ARRAY one tupel that contains the custom object
	 */
	function selectCustomObject($arr_cond = Array ()) {
		$str_cond = "%";
		foreach ($arr_cond as $key => $value) {
			$str_cond .= "&" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($key))) . "=" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($value)));
		}
		$str_cond .= "%";

		$arr_result = @ array_pop($this->SqlQuery("SELECT * FROM " . tbl_custom_object . " WHERE fk_module_id='<MODULEID>' AND data LIKE '" . $str_cond . "'"));
		return $this->_deserializeCustomObject($arr_result["data"]);
	}

	/**
	 * insert a custom object into database
	 *
	 * Example: 
	 * <code>
	 *   $this->insertCustomObject(Array(
	 *		"myobject_id"=>"123",
	 *		"name"=>"my custom object",
	 *		"phone"=>"just an example"
	 *	));
	 * </code>
	 *
	 * @param ARRAY $arr_data a one dimensional array that describes the custom object
	 */ 
	function insertCustomObject($arr_data) {
		$str_data = "";
		foreach ($arr_data as $key => $value) {
			$str_data .= "&" . urlencode($key) . "=" . urlencode($value);
		}

		$arr_data = Array (
			"fk_module_id" => "<MODULEID>",
			"data" => $this->_serializeCustomObject($arr_data),

			
		);

		return $this->InsertQuery(tbl_custom_object, $arr_data);
	}

	/*
	 * Updates a custom object. Note that you should always submit the whole custom object (not only the parts that have changed)
	 *
	 * @param ARRAY $arr_cond array of properties to identify the custom object to be updated 
	 * @param ARRAY $arr_data a one dimensional array that describes the custom object
	 */
	function updateCustomObject($arr_cond = Array (), $arr_data = Array ()) {

		$arr_data = Array (
			"fk_module_id" => "<MODULEID>",
			"data" => $this->_serializeCustomObject($arr_data),

			
		);

		$str_cond = "%";
		foreach ($arr_cond as $key => $value) {
			$str_cond .= "&" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($key))) . "=" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($value)));
		}
		$str_cond .= "%";
		$this->UpdateQuery(tbl_custom_object, $arr_data, "fk_module_id='<MODULEID>' AND data LIKE '" . $str_cond . "'");
	}

	/**
	 * removes a custom object from the database
	 *
	 * @param ARRAY $arr_cond array of properties to identify the custom object to be updated 
	 */
	function deleteCustomObject($arr_cond = Array ()) {
		$str_cond = "%";
		foreach ($arr_cond as $key => $value) {
			$str_cond .= "&" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($key))) . "=" . str_replace("_", "\\_", str_replace("%", "\\%", urlencode($value)));
		}
		$str_cond .= "%";
		$this->DeleteQuery(tbl_custom_object, "fk_module_id='<MODULEID>' AND data LIKE '" . $str_cond . "'");
	}

	function _serializeCustomObject($arr_data) {
		$str_data = "";
		foreach ($arr_data as $key => $value) {
			$str_data .= "&" . urlencode($key) . "=" . urlencode($value);
		}
		return $str_data;
	}

	function _deserializeCustomObject($str_data) {
		$arr_result = Array ();
		$arr_items = explode("&", $str_data);
		foreach ($arr_items as $str_item) {
			if (strlen($str_item) > 0) {
				$parts = explode("=", $str_item);
				$arr_result[urldecode($parts[0])] = urldecode($parts[1]);
			}
		}
		return $arr_result;
	}

	/*<DB-METHODS>*/
	/*</DB-METHODS>*/
}
?>
