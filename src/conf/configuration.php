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

define ("FRAMEWORK_VERSION", "1.3.1");
define ("PROJECT_LOG", "log/framework_");

$SERVER = "http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
$SERVER = substr($SERVER, 0, -strlen(basename($SERVER)));
$SECURE_SERVER = str_replace("http://", "https://", str_replace($_SERVER["SERVER_NAME"], $_SERVER["SERVER_NAME"].":443", $SERVER));

define ("DEFAULT_TEMPLATE", "templates/template.html");

define ("DEBUG_MYSQL", 0);
define ("PRAILS_HOME_PATH", "http://prails.googlecode.com/svn/trunk/");

/*<KEEP-1>*/
$arr_settings = Array(
/*<CUSTOM-SETTINGS>*/
"PROJECT_NAME" => "Prails Web Framework",
"ENV_PRODUCTION" => false,
"DEVELOPER_CHAT_ENABLED" => false,
"DEBUG_LEVEL" => 2,
"DBCACHE_ENABLED" => true,
"PROFILING_ENABLED" => false,
"CSS_EMBED_RESOURCES" => true,
"ERROR_NOTIFICATION" => false,
"ERROR_EMAIL" => "notify@example.org",
"IS_SETUP" => false,
"USE_SMTP" => false,
"SMTP_HOST" => "localhost",
"SMTP_PORT" => 25,
"SMTP_USERNAME" => "",
"SMTP_PASSWORD" => "",
/*</CUSTOM-SETTINGS>*/
);
/*</KEEP-1>*/

foreach ($arr_settings as $key=>$value) {
	define ($key, $value);
}

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

define ("SQLITE", "SQLite");
define ("MYSQL", "MySQL");
define ("POSTGRESQL", "PostgreSQL");

/*<KEEP-2>*/
define ("DB_TYPE", SQLITE);
define ("DB_CACHE_SIZE", 4 * 1024 * 1024);
/*</KEEP-2>*/
define ("USE_AUTO_DEPLOY", false || FIRST_RUN);

/*<KEEP-3>*/
switch ($_SERVER["SERVER_ADDR"])
{
   default:
   	  $arr_dbs = Array(
   	  	"offline"=>Array(
   	  		"host"=>"localhost",
   	  		"name"=>"test",			// database name - change this
   	  		"user"=>"root",			// database user - change this
   	  		"pass"=>"",				// database password - change this
   	  	),
   	  );
}
/*</KEEP-3>*/

function updateConfiguration($arr_configuration, $module = false) {
    if (!$module) {
        $conf_file = "conf/configuration.php";
    } else {
        $conf_file = "modules/".$module."/".$module.".php";
        if (!file_exists($conf_file)) {
            $conf_file = "modules/".constant(strtoupper($module))."/".constant(strtoupper($module)).".php";
        }
    }
	$cnt = file_get_contents($conf_file);
	$settings = Array();
	foreach ($arr_configuration as $conf) {
		$value = $conf["value"];
		if (trim(strtolower($value)) == "true" || $value === true) 
			$var = "true"; 
		else if (trim(strtolower($value)) == "false" || $value === false) 
			$var = "false";
		else if (is_numeric(trim($value))) {
			if ($value == (string)(float)$value) $var = floatval($value);
			if ($value == (string)(int)$value) $var = intval($value);
		} else if (gettype($value) == "string") {  
			$var = "\"".$value."\"";
		} else {
			$var = $value;
		}
		array_push($settings, "\"".$conf["name"]."\" => ".$var);
	}
	$pre = substr($cnt, 0, strpos($cnt, "/*<CUSTOM-SETTINGS>*/")+strlen("/*<CUSTOM-SETTINGS>*/")+1);
	$post = substr($cnt, strpos($cnt, "/*</CUSTOM-SETTINGS>*/")-1);
	file_put_contents($conf_file, $pre.implode(",\n", $settings).$post);
}

function getConfiguration($module = false) {
    if (!$module) {
        global $arr_settings;
        return $arr_settings;    
    } else {
        $settingsName = "\$arr_".$module."_settings";
        if (is_array($GLOBALS[$settingsName])) {
            return $GLOBAL[$settingsName];
        } else {
            $settingsName = "\$arr_".constant(strtoupper($module))."_settings";
            return $GLOBAL[$settingsName];
        }
    }
}

?>
