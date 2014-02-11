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
/** Section Architecture
* 
* ## General Architecture Overview
* ![General Architecture Overview](static/images/doc/architecture-overview2.png)
* The Prails Web Framework consists of a variety of components that act very closely together while keeping re-usability as high as possible. The framework itself is built of five main components, called agents. These are the Request Worker agent, the Generator agent, the Modules agent, the Global Libraries agent and last the Database Manager agent. I will shortly explain how these interact in order to render a dynamic web page.
* 
* ### The Request worker
* ![The Request worker](static/images/doc/architecture-overview-request_worker2.png)
* The Request Worker takes the current request from the user and decides what needs to be done next. Therefore it first creates the environment needed to execute whatever comes next. If a completely processed template (called runlet) does exist for the current request, then the Request Worker communicates with the Generator agent in order to re-parse it and thus to re-evaluate dynamic contents found in the respective runlet. These runlets are for most parts pure static HTML, but they may also contain dynamic contents. If you want to know more about templates and runlets, have a look at Chapter 2: The Generator.
*
* If no such runlet exists, it will check which module to load next and directly run the respective event handler from this module. This decision is made using the URL parameter event. "Directly" means in this case, that if the event parameter is set, the standard Global Library is asked to invoke the respective event, else the home event of the main module is invoked by default. As soon as the event result is returned by the module, it is given to the Generator to create a runlet of it.
*
* The Request Worker is also able to do some basic load balancing if needed. Therefore it can call an external service script, which returns the number of current users. If this exceeds a defined threshold, the request can be redirected to a different server. This feature will be available shortly as plugin and is currently disabled.
*
* ### The Generator
* ![The Generator](static/images/doc/architecture-overview-generator2.png)
* The Generator agent is responsible for general output related processing and generation. Thus it plays a major role in the processing of almost every request. It consists of two main components: first the Content Generator agent and second the Template Engine. The generator itself is also responsible for handling registered Javascript and CSS files.
*
* The following two sections will explain what the different components do and what they are supposed to be used for.
*
* #### Content Generator
* Currently this component contains a set of methods for controlling the base template, which is mainly independent of the actual template engine. These methods are used to set the page's title, meta descriptions, keywords, minifying and packing of CSS and JS files and other header information.
* 
* Javascript files registered in the Generator are minimized and packed together into one, static file, stored in the Javascript Cache. For every used combination of Javascript files registered, a different Javascript Cache file is created. The CSS files are first collected into one file then existent LESS code is compiled. Afterwards the resulting CSS is minified and stored in the CSS Cache. Minification of Javascript code and CSS code is both only turned on in production environments (ENV_PRODUCTION set to true).
*
* #### Template Engine
* The Template Engine is the most complex part in the Generator agent. It manages the include of templates, their compilation and the evaluation. It also takes care of caching the resulting runlets.
* 
* So what are runlets actually? Runlets are in fact compiled and decorated templates. If not otherwise specified, the normal way of an event is, that it's content is wrapped into a main template, which is not much more than a very general page skeleton in HTML. It contains some placeholders for Javascript, CSS files, placeholders for meta data, like the author and the page's title and finally the placeholder for the actual page's content. When all placeholders are filled, the resulting content is writing to the cache as a runlet. If caching is disabled, the runlet will be instantly removed after evaluation. So a runlet is in fact a static and complete HTML page with one exception: it may contain some more dynamic code too. Sometimes it may be quite difficult to decide what needs to be dynamic until the end and what not. There is a rule of thumb, which says: only things that are directly dependent on session data should be dynamic in a runlet. This is mostly because no contextual information is existent at the evaluation time of the runlet, but only session data (and all standard library tools). Note that dynamic code in runlets only makes sense if caching is enabled for this page.
* 
* The Template Engine does not only pull everything together when creating runlets, it also is responsible for including templates from within modules. Included templates are first compiled by the TagLib compiler and then evaluated with the context they were included in. So including templates keeps the current context.
* 
* The TagLib compiler, which is run before evaluation, converts any special tags from the tag library into normal, evaluable content (be it HTML or PHP or mixed HTML/PHP). These special tags can be used to change the control flow within a template while remaining fully HTML compatible. It is also a way to minimize code duplicates (and thus increase stability and maintainability), as these tags can also stand in place for very complex HTML/PHP content to be rendered. Because they are extremely easy to create (it's simply a HTML/PHP code fragment), they can be easily extended and in case of errors faster be debugged. To see how to create and work with tag library tags, see the TagLib API documentation.
* 
* A template consists of HTML data. It can be pure HTML, but also have PHP code embedded (using <? ... ?>). Furthermore the Template Engine provides an easy way to access the dynamic context it was included in using a short notation.
*
* Within a template you can use a special PHP opening/closing tag, in order to keep it's content as dynamic content in the final runlet (and thus not letting it being evaluated at the include process). Because the contents of these special PHP opening/closing tags are not evaluated until the final evaluation of the runlet, it is possible to use any kind of dynamic code that is evaluated beforehand within these area. See the following example code:
*
* {{{
* <!! if (strlen("#user.name") > 0 && <?=$arr_param["user"]["user_id"]?> > 0) { !!>
*        <div class="username"><b>Hello, #user.name</b></div>
* <!! } else { !!>
*        <div class="username">Who are you?</div>
* <!! } !!> 	
* }}}
* This code would in a runlet look like:
* {{{
* <? if (strlen("Test User") > 0 && 21 > 0) { ?>
*       <div class="username"><b>Hello, Test User</b></div>
* <? } else { ?>
*       <div class="username">Who are you?</div>
* <? } ?>
* }}}
* So the #user.name and the <?=$arr_param["user"]["user_id"]?> are being evaluated before the creation of the runlet.
* 
* ### The Modules
* ![The modules](static/images/doc/architecture-overview-module2.png)
* Modules are the core component and the basis for every dynamic content created using the framework. Each module is supposed to handle one complete topic of the web application. A topic is something like "user management" or "products", and so on. Because those topics are often very complex, each module consists of different events, which handle specific aspects/actions of the topic, like "list" or "edit".
* 
* Modules consist of a list of events, some shared data (shared resources and module-wide configuration data) and the data agent.
* 
* The events are usually called by specifying the event URL parameter. This parameter's value consists of two parts: the name of the module and the name of the event. Both are concatenated via a colon. So a correct event URL looks for example like so: `http://localhost/framework/?event=user:edit` . Here the first part "user" indicates, that the event "edit" is within the user module.
*
* Each event has three parts: a handler, who takes the user's request and calls requests the Data agent of the module for some database information, the printer, who is requested by the handler after it got all needed data from the database and finally the template, which is included by the printer (and thus evaluated at that time).
*
* The shared resources are basically all images that are needed by the events for the display. Normally these are static images, pulled by the user's browser. The shared resources will be put into a special directory under `templates/&lt;module name>/images/&lt;resource name>` . Although it is possible to put other resource types than images into that storage, it is not recommended.
*
* The config variables are basically normal key-value pairs as can often be found in configuration files. These are defined constants, that can be accessed from anywhere within the module. Often they are used to control certain behavior or hold semi-static data (data which will be changed extremely seldom). The config variables are divided into two types: public configuration variables and private configuration variables. The latter ones change whenever the application in installed on a different machine and are specific for the setup of that machine. The public configuration variables are valid regardless of the setup/environment the applications runs in.
*
* The Data agent is responsible for requests to the database or any other data source (like Webservices). Therefore it provides queries that can be asked by the handler of that module. The data queries of a module cannot be used outside the module. Usually it will build up a query, send it to the Database Manager and finally return the result.
*
* ### The Global Libraries
* ![The global libraries](static/images/doc/architecture-overview-global_library2.png)
* The Global Library agents are each complete libraries, written in PHP. They are available and can be used throughout the whole web application. One library, which is called the standard global library, is pre-defined and contains a set of tools helpful in everyday coding business, like redirections, internal invocations of events, detection of embeds, and so on. For a full list and description of the whole standard global library, refer to the Global Library API documentation.
*
* Other global libraries can be created and are to be put into a special directory, called lib/custom/. In the ideal case, one file equals one library. But because many external libraries, like PayPal API library or Facebook Library are much bigger, you should create sub directories for the bigger ones.
*
* ### The Database Manager
* ![The Database Manager](static/images/doc/architecture-overview-database_manager2.png)
* The Database Manager handles all requests that have something to do with database queries. It manages the connection to the underlying database automatically, manages the database cache, which speeds up large queries, constantly synchronizes database contents with other (external) databases and provides access for easier usability.
*
* Whenever a database query is sent to the Database Manager, it is first checked if a result of this query is already existent in the database cache. If it is, the result if read from the file system, decoded and instantly returned. If nothing is cached for the current query, it checks if it contains table names of "deferred" tables - tables which have different names in the current environment than their original one. If it does, it replaces them by their actual names. Then, in preparation of the actual sending of the query to the database, it is made sure, that there is a connection to the database established. Then the query is sent and the result is written into an associative array (list of key-value pairs, whereas the key is the field name in the database).
*
* After this is done, it checks if the current query has been a SELECT statement. If this is the case, then the result is encoded and stored in the cache. Also together with the actual data, references for the affected tables are created, linking to the now cached query result. If the current statement has been no SELECT statement, then it is very likely to be a data changing query (like UPDATE, DELETE and INSERT). So if it is such a query, then all data referenced by the affected table is removed and thus needs to be freshly read from the database as soon as the next query for it arrives.
*
* Furthermore whenever a data-changing query appears, and there is more than one database management system that should be kept in synchronization, the original query (without adapted table names) is written to a query log, which is then exported to the other database(s), thus keeping them synchronized. This process is also multi-directional: if the web application is installed across multiple servers - in style of mirrors for example - then every instance would have their own database and would change it's own data and will automatically synchronize these changes to all other instances, and vice versa.
* 
* For a more detailed list of methods available for use from the Database Manager, refer to the Database API documentation.
* 
* ## Complete Overview
* ![Complete Overview](static/images/doc/architecture_complete.png)
**/
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

$__cacheName = "cache/page_".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]) . ".".one_of($_COOKIE['defaultLang'], DEFAULT_LANGUAGE);
// check if we have a cache entry for current request
if (file_exists($__cacheName)) {
	// yes, but is it too old?
	if (filemtime($__cacheName) < ($_SERVER["REQUEST_TIME"] - 3600)) {
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
