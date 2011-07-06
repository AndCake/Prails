<?php
/**
    Prails Web Framework
    Copyright (C) 2011  Robert Kunze

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
include("auto_prepend.php");
header('P3P: CP="CAO PSA OUR"');

// placeholder for overload fallback plugin
// ...

// clean up env vars
if (get_magic_quotes_gpc() === 1) {
    function stripslashes_deep(&$value) { 
        $value = is_array($value) ? 
                array_map('stripslashes_deep', $value) : 
                stripslashes($value); 

        return $value; 
    }
    $_GET = stripslashes_deep($_GET);
    $_POST = stripslashes_deep($_POST);
    $_COOKIE = stripslashes_deep($_COOKIE);
    $_REQUEST = stripslashes_deep($_REQUEST);
}

include("conf/includes.php");
$log = new Logger();
if (IS_SETUP) {
	if (USE_AUTO_DEPLOY) DBDeployer::deploy($arr_database, "tbl_prailsbase_");
	$session = new SessionManager();
}

if (!isset($_SESSION["last_access"]) || ($_SERVER["REQUEST_TIME"] - $_SESSION["last_access"]) > 60) {
	$_SESSION["REQUEST_TIME"] = time();
}

$__cacheName = "cache/".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
if (file_exists($__cacheName)) {
	if (filectime($__cacheName) < ($_SERVER["REQUEST_TIME"] - 3600)) {
		@unlink($__cacheName);
	} else if (!$_SERVER["HTTPS"] && $_SERVER["SERVER_PORT"] == 80 && $_SERVER["REQUEST_METHOD"] != "POST" && HTML_CACHE_ENABLED) {
	    require($__cacheName);
	    session_write_close();
	    die();
	}
}

if (IS_SETUP) {
	HookCore::init();
}
$obj_main = new MainHandler();
$obj_generator = Generator::getInstance();
$obj_generator->setModule($obj_main);
if ($_GET["event"]) {
	$obj_generator->generateOutput(invoke($_GET["event"]));
} else if (($result = $obj_main->home()) !== false) {
	$obj_generator->generateOutput ($result);
} else {
	// error!!!
	throw new Exception("FATAL: Unable to call main home handler! Please make sure it exists!");
}

?>
