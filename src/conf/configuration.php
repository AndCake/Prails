<?php
/**
    Prails Web Framework
    Copyright (C) 2013  Robert Kunze

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
/** Section Configuration
 * 
 * The configuration of Prails happens in two ways: first via the global configuration,
 * which can be accessed through the Global module by editing it's configuration, second
 * via the different modules installed. Each module can have it's own configuration.
 * All of those module-specific configurations are by default available on both: development
 * instances and production instances. However, by switching the current view mode for 
 * the configuration, you can enter separate configuration data for development and production
 * instances.
 **/
define ("FRAMEWORK_VERSION", "1.6.0");
define ("PROJECT_LOG", "log/framework_");

$SERVER = "http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
$SERVER = substr($SERVER, 0, -strlen(basename($SERVER)));
$SECURE_SERVER = str_replace("http://", "https://", str_replace($_SERVER["SERVER_NAME"], $_SERVER["SERVER_NAME"].":443", $SERVER));

define ("DEFAULT_TEMPLATE", "templates/template.html");

define ("DEBUG_MYSQL", 0);
define ("PRAILS_HOME_PATH", "https://raw.github.com/AndCake/prails/master/");

/**
 * Global configuration options include:
 * - `PROJECT_NAME` (String) - contains the project's name. This can be used for the text next to the logo image, or for referencing emails.
 * - `ENV_PRODUCTION` (Boolean) - when set to `true`, Prails will be in production mode. When set to `false` it will be in development mode (defaults to `false`). The production mode activates minification of CSS and JS resources, page caching and the corresponding configuration options for that mode (as defined in the modules).
 * - `DEVELOPER_CHAT_ENABLED` (Boolean) - when set to `true`, the chat area at the right edge of the Prails IDE screen will be continuously updated with the other developer's chat messages (including your own), which enables project-wide chats, defaults to `false`
 * - `DEBUG_LEVEL` (Number) - Debug level 0 only writes fatal problems to the log, 1 writes warnings, errors and fatal, 2 includes debug messages, warnings, errors and fatal problems, 3 adds info to that list and 4 includes traces, info, debug messages, warnings, errors and fatal problems. Defaults to 2.
 * - `DBCACHE_ENABLED` (Boolean) - will enable/disable the database cache. Disabling it will cause all database queries being directly sent to the database, regardless of changed data. If enabled, only queries that were previously not fetched from the database or in case the underlying data changed meanwhile will be sent to the database. Defaults to `true` (enabled)
 * - `PROFILING_ENABLED` (Boolean) - if set to `true` the system will be profilled. This activates the profiler button on the help tab and provides information about the database and page cache usage, memory usage and other data that can be used to optimize the system. It is recommended to not turn this on at a production environment - or if necessary, only for a short period of time as it will increase churn put on the server. Defaults to `false`.
 * - `CSS_EMBED_RESOURCES` (Boolean) - setting this to `true` will embed small images (up to 1kb) to be embedded as data URLs into the generated CSS. Defaults to true
 * - `ERROR_NOTIFICATION` (Boolean) - if set to `true`, an email will be sent in case an error occurs to the email address specified in `ERROR_EMAIL`. Defaults to `false`
 * - `ERROR_EMAIL` (String) - the email address error messages should be sent to, if `ERROR_NOTIFICATION` is set to `true`. Defaults to `notify@example.org`
 * - `IS_SETUP` (Boolean) - specifies whether or not this Prails instance was setup already.
 * - `USE_SMTP` (Boolean) - if set to `true`, this will cause all emails that are sent through `[Tools]sendMail` to be sent via direct SMTP connection rather than through the `sendmail`/`postfix`. Defaults to `false`.
 * - `SMTP_HOST` (String) - specifies the SMTP server to connect to, if `USE_SMTP` is set to `true`.
 * - `SMTP_POST` (Number) - specifies the SMTP port to be used for the SMTP connection, if `USE_SMTP` is set to `true`.
 * - `SMTP_USERNAME` (String) - defines the SMTP user name with which to connect to the SMTP server if `USE_SMTP` is set to `true`.
 * - `SMTP_PASSWORD` (String) - defines the SMTP password with which to connect to the SMTP server if `USE_SMTP` is set to `true`.
 * - `AUTO_REFRESH` (Boolean) - turns auto-refreshing of development instances on code changes on (`true`) or off (`false`). Defaults to `true`.
 **/
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
switch ($_SERVER["SERVER_ADDR"]) {
   default:
   	  $arr_dbs = Array(
   	  	"offline" => Array(
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
		} else if (empty($value)) {
      $var = "\"\"";
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
