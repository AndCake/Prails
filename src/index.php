<?php
/**
    Prails Web Framework
    Copyright (C) 2012  Robert Kunze

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
header("X-Powered-By:");

// check if we currently have too many visitors
if (!$_COOKIE["visited"] && file_exists("cache/heavyload") && filemtime("cache/heavyload") > $_SERVER["REQUEST_TIME"] - 600) {
	// if so, redirect to the overload.html
	header("Location: http://".$_SERVER["HTTP_HOST"].rtrim(dirname($_SERVER['PHP_SELF']), '/')."/overload.html");
	die();
} else if (file_exists("cache/heavyload") && filemtime("cache/heavyload") <= $_SERVER["REQUEST_TIME"] - 600) {
	// if the situation relaxed, remove the heavy load indicator
	@unlink("cache/heavyload");
}

// start profiling clock
$startTime = microtime(true);

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

// include all relevant scripts & configuration
include("conf/includes.php");

// initialize global log object
$log = new Logger();
$profiler = null;

if (IS_SETUP) {
	// deploy database, if something changed, initialize profiler, if enabled
	if (USE_AUTO_DEPLOY) DBDeployer::deploy($arr_database, "tbl_prailsbase_");
	if (PROFILING_ENABLED === true) $profiler = new Profiler("system");
	// initialize session manager
	$session = new SessionManager();
}
// set visited cookie, if not exists
if (!$_COOKIE["visited"]) {
	// auto-expires after 10 minutes
	setcookie("visited", "1", $_SERVER["REQUEST_TIME"] + 600, dirname($_SERVER['PHP_SELF']));
}

$__cacheName = "cache/page_".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]) . ".".one_of($_SESSION["LangData_LANGUAGE_SETTING"]["currentLanguageId"], $_COOKIE['defaultLanguage'], DEFAULT_LANGUAGE);
// check if we have a cache entry for current request
if (file_exists($__cacheName)) {
	// yes, but is it too old?
	if (filectime($__cacheName) < ($_SERVER["REQUEST_TIME"] - 3600)) {
		// yes, remove it
		@unlink($__cacheName);
	} else if (!$_SERVER["HTTPS"] && $_SERVER["SERVER_PORT"] == 80 && $_SERVER["REQUEST_METHOD"] != "POST" && HTML_CACHE_ENABLED) {
		// no, and we're not running on HTTPS and current request is no POST
		// so use the cache entry for rendering the page
	    require($__cacheName);
	    // report cache hit, if profiler is enabled
	    if ($profiler) $profiler->logEvent("page_cache_hit#".$_SERVER["REQUEST_URI"]);
	    // write any session changes, if there were any
	    if ($session->isActive()) session_write_close();
		$endTime = microtime(true);
		// if rendering of the cached page took more than 25 seconds on a production instance
		if (ENV_PRODUCTION && $endTime - $startTime > 25) {
			// we have an overload scenario, so place indicator
			touch("cache/heavyload");
		}
	    die();
	}
}

// from here on, no page cache entry could be found or be used
if (IS_SETUP) {
	HookCore::init();
}

// initialize the main ("global") module
$obj_main = new MainHandler();
$obj_generator = Generator::getInstance();

// if we have an event given
if ($_GET["event"]) {
	// call it and render it's result
	$obj_generator->generateOutput(invoke($_GET["event"], null, true));
} else if (($result = $obj_main->home()) !== false) {
	// no event given but main module has home handler, so use that one
	$obj_generator->generateOutput ($result);
} else {
	// error - should never happen!!! The main module needs to always exist
	throw new Exception("FATAL: Unable to call main home handler! Please make sure it exists!");
}
// report cache miss if profiler is enabled
if ($profiler) $profiler->logEvent("page_no_cache_hit#".$_SERVER["REQUEST_URI"]);

$endTime = microtime(true);
if (ENV_PRODUCTION && !isset($_GET['prailsjob']) && $endTime - $startTime > 25 && array_shift(explode(":", $_GET["event"])) !== "builder") {
	// when we're on production and rendering of the current page took more than 25 seconds, report heavy load
	touch("cache/heavyload");
}
?>
