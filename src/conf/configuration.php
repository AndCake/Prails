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

define ("PROJECT_NAME", "PRails Web Framework");
define ("FRAMEWORK_VERSION", "1.0.2.1");
define ("PROJECT_LOG", "log/framework.log");
define ("ERROR_MAIL", "<enter your email here to get notified on errors>");

$SERVER = "http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
$SERVER = substr($SERVER, 0, -strlen(basename($SERVER)));
$SECURE_SERVER = str_replace("http://", "https://", str_replace($_SERVER["SERVER_NAME"], $_SERVER["SERVER_NAME"].":443", $SERVER));

define ("DEFAULT_TEMPLATE", "templates/template.html");

define ("DEBUG_EMAIL", 0);
define ("DEBUG_LEVEL", "2");
define ("DEBUG_MYSQL", 0);

$ARR_LOGGER_ENABLED_PROPERTIES = Array();
switch (DEBUG_LEVEL) {
	case "4":
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "trace";
	case "3":
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "info";
	case "2":
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "debug";
	case "1":
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "warn";
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "error";
	case "0":
		$ARR_LOGGER_ENABLED_PROPERTIES[] = "fatal";
}

$arr_settings = Array(
/*<CUSTOM-SETTINGS>*/
/*</CUSTOM-SETTINGS>*/
);

foreach ($arr_settings as $key=>$value) {
	define ($key, $value);
}

define ("SQLITE", "SQLite");
define ("MYSQL", "MySQL");

define ("DB_TYPE", SQLITE);
define ("DB_CACHE", "cache/db/");
define ("USE_AUTO_DEPLOY", false || FIRST_RUN);

switch ($_SERVER["SERVER_ADDR"])
{
   default:
   	  $arr_dbs = Array(
   	  	"offline"=>Array(
   	  		"host"=>"localhost",
   	  		"name"=>"test",			// database name - change this
   	  		"user"=>"root",			// database user - change this
   	  		"pass"=>"",			// database password - change this
   	  	),
   	  );
}

?>
