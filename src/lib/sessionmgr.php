<?php
class SessionManager extends TblClass {

	var $life_time;

	function SessionManager() {
		parent::TblClass("tbl_prailsbase_");

		// Read the maxlifetime setting from PHP
		$this->life_time = get_cfg_var("session.gc_maxlifetime");

		// Register this object as the session handler
		session_set_save_handler(
			array( &$this, "open" ),
			array( &$this, "close" ),
			array( &$this, "read" ),
			array( &$this, "write"),
			array( &$this, "destroy"),
			array( &$this, "gc" )
		);
		
		if (!is_dir("cache/session/")) {
			@mkdir("cache/session/", 0755, true);
		}

		session_start();
	}

	function open( $save_path, $session_name ) {
		global $sess_save_path;
		$sess_save_path = $save_path;
		return true;
	}
	
	function isActive() {
		return file_exists("cache/session/".crc32(session_id()));
	}

	function close() { return true; }

	/**
	 * Reads a session
	 * @param STRING $id
	 */
	function read( $id ) {
		// Set empty result
		$data = '';
		$newid = crc32($id);
		
		if (!file_exists("cache/session/".$newid)) {
			return $data;
		}
		
		// Fetch session data from the selected database
		$time = $_SERVER["REQUEST_TIME"];

		$sql = "SELECT session_data FROM tbl_prailsbase_sessions WHERE sessions_id = '$newid' AND expires > $time";

		$arr_data = $this->SqlQuery($sql);

		if (count($arr_data) > 0) {
			$data = $arr_data[0]["session_data"];
		}

		return $data;
	}

	/**
	 * Writes the data into session
	 * @param STRING $id
	 * @param STRING $data
	 */
	function write( $id, $data ) {
		// Build query
		$time = $_SERVER["REQUEST_TIME"] + $this->life_time;

		$newid = crc32($id);
		$newdata = $this->escape($data);

		$sql = "REPLACE INTO tbl_prailsbase_sessions (sessions_id, session_data, expires) VALUES ('$newid', '".$newdata."', $time)";
		$this->SqlQuery($sql);

		@touch("cache/session/".$newid);
		
		return TRUE;
	}

	/**
	 * Removes a session from the DB
	 * @param STRING $id session id to be removed
	 */
	function destroy( $id ) {
		// Build query
		$newid = crc32($id);
		@unlink("cache/session/".$newid);
		$sql = "DELETE FROM tbl_prailsbase_sessions WHERE sessions_id = '$newid'";
		$this->SqlQuery($sql);

		return TRUE;
	}

	/**
	 * Garbage Collection
	 */
	function gc() {
		// Build DELETE query.  Delete all records who have passed the expiration time
		$expires = time() - $this->life_time;
		$sql = 'DELETE FROM tbl_prailsbase_sessions WHERE expires < UNIX_TIMESTAMP()';
		$this->SqlQuery($sql);
		
		$dp = opendir("cache/session/");
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != '.' && filemtime("cache/session/".$file) < $expires) {
				@unlink("cache/session/".$file);
			} 
		}
		closedir($dp);
		
		// Always return TRUE
		return true;
	}

}
?>
