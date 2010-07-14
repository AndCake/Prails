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
include("auto_prepend.php");
header('P3P: CP="CAO PSA OUR"');

// placeholder for overload fallback plugin
// ...

session_start();
include("conf/includes.php");

$log = new Logger();

if (!isset($_SESSION["last_access"]) || (time() - $_SESSION["last_access"]) > 60) {
	$_SESSION["last_access"] = time();
}

if (!$_SERVER["HTTPS"] && $_SERVER["SERVER_PORT"] == 80 && $_SERVER["REQUEST_METHOD"] != "POST" && file_exists("cache/".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"])) && HTML_CACHE_ENABLED) {
    require("cache/".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]));

    die();
}

if (USE_AUTO_DEPLOY) DBDeployer::deploy($arr_database);

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
