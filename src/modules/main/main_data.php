<?php

class MainData extends Database
{
   function MainData()
   {
      parent::Database();
   }
   
	function listModules() {
		return $this->SqlQuery("SELECT * FROM tbl_module WHERE 1 ORDER BY name ASC");
	}
}
?>
