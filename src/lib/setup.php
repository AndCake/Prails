<?
# define the IN function
if (!function_exists('in')) {
	#
	# in(needle, haystack) -> boolean
	# - needle (mixed) - what to find in the haystack
	# - haystack (mixed) - what to search in
	# 
	# The `in(needle, haystack)` function will return true, if the first argument is a 
	# part of the second argument. The `haystack` can be numbers, arrays, strings and objects.
	#
	# Snow:
	# 
	# 	# will echo "3 is in."
	# 	if 3->in [1, 2, 3]
	# 		echo "3 is in.\n"
	#
	# 	# will echo "e is in test".
	# 	if "e"->in "test"
	# 		echo "e is in test.\n"
	#
	# 	# will echo "3 is in 10."
	# 	if 3->in 10
	# 		echo "3 is in 10.\n"
	#
	function in($needle, $haystack) {
		switch(gettype($haystack)) {
			case 'string': return strpos($haystack, $needle) !== false; 
			case 'array': return in_array($needle, $haystack); 
			case 'integer': return $haystack - $needle >= 0; 
			case 'object': return isset($haystack->$needle);default:return null;
		}
	}
}

# define the REPLACE function
if (!function_exists('replace')) {
	#
	# replace(haystack, pattern, replacement) -> mixed
	# - haystack (mixed) - where to replace things in
	# - pattern (mixed) - what to search for
	# - replacement (mixed) - what to replace it with
	# 
	# will replace the `needle` with the `replacement` in the `haystack`. The `needle` can 
	# be either a regular expression, a normal string. If the `haystack` is an array, the 
	# `replace()` function will simply replace the `needle` with the `replacement`.
	#
	# Snow:
	# 
	# 	# will output "TesT"
	# 	echo "test"->replace 't', 'T'
	#
	# 	# will output "Test"
	# 	echo "test"->replace /^[a-z]/, 'T'
	#
	# 	# will output "[1, 4, 3]"
	# 	echo [1, 2, 3]->replace(2, 4)->json_encode()
	#
	function replace($haystack, $pattern, $rep) { 
		if (is_array($haystack)) { 
			$pos = array_search($pattern, $haystack, true); 
			if ($pos === false) return $haystack; 

			return array_replace($haystack, Array($pos => $rep)); 
		} 
		$pp = '/^[^a-zA-Z0-9\\\\s].*' . str_replace('/', '\/', $pattern[0]) . '[imsxADSUXJu]*$/m'; 
		if (preg_match($pp, $pattern)) 
			return preg_replace($pattern, $rep, $haystack); 
		else 
			return str_replace($pattern, $rep, $haystack);
	}
}

# the TEMPLATE function
if (!function_exists('template')) {
	# 
	# template(file, data) -> mixed
	# - file (string) - what to use as a template (can be the template name (without .tpl) or the actual template)
	# - data (Array) - the data to be used in the template variables
	#
	# provides a basic templating system with a syntax resembling [Mustache](http://mustache.github.io). The 
	# first argument `tpl` can either be the template itself or the name of the template file. All template 
	# file names must end in `.tpl`. The file path provided should be without the ending `.tpl`. 
	#
	# Template `nameList.tpl`:
	#
	# 	<h2>Names</h2>
	# 	{{#names}}
  	# 		{{> user}}
	# 	{{/names}}
	#
	# Template `user.tpl`:
	#
	#	<strong>{{name}}</strong>
	#
	# Snow:
	#
	# 	echo template "nameList", ["names": [
	# 		["name": Carl"], 
	# 		[name: "Fred"], 
	# 		[name: "Dan"]
	# 	]]
	#
	# will output:
	# 
	#	<h2>Names</h2>
	#	  <strong>Carl</strong>
	#	  <strong>Fred</strong>
	#	  <strong>Dan</strong>
	#
	function template($file, $data, $rootCore = '$data', $eval = true, $depth = 0) { 
		$code = str_replace('\'', '\\\'', (file_exists($file.'.tpl') ? file_get_contents($file . '.tpl') : $file)); 

		# parse a single variable
		$parseVar = function($var, $root = '$data') use ($rootCore) { 
			# if the variable is just a dot, then return the whole root variable
			if (trim($var) == '.') return Array($root, $root); 
			# split the variable name by dot
			$parts = explode('.', trim($var)); 
			# if the first part is empty
			if ($parts[0] == '')  {
				# we want the base root
				$start = $obj = $rootCore; 
			} else {
				# else just use the current root and add the first part
				$start = $root . '[\'' . $parts[0] . '\']'; 
				$obj = $root . "->" . $parts[0];
			}
			# finally add all other parts
			for ($i = 1; $i < count($parts); $i++) { 
				$start .= '[\'' . $parts[$i]. '\']';
				$obj .= "->" . $parts[$i];
			}
			# returns an array expression and an object expression (p.e. $data['test']['me'], $data->test->me)
			return Array($start, $obj);
		};
		# parse all variables
		$parseVars = function($code, $root = '$data') use ($parseVar) {
			preg_match_all('/\{\{(?!#\/\^)((?:[^}]|\}[^}])+)\}\}/m', $code, $matches); 
			foreach ($matches[1] as $key => $match) { 
				list($var, $obj) = $parseVar($match, $root);
				$parts = explode("->", $obj);
				$pre = implode("->", array_slice($parts, 0, -1));
				$post = array_pop($parts);
				# check if the current variable is a function; if so: call it, else just use the variable's value
				if ($var === $obj) {
					$var = '(is_object('.$var.') && is_callable('.$var.') ? '.$var.'() : '.$var.')';
				} else {
					$var = '(isset('.$obj.') ? ' . $obj . ' : (isset('.$pre.') && is_callable(Array('.$pre.', \''.$post.'\')) ? ' . $obj . '() : (is_object('.$var.') && is_callable('.$var.') ? '.$var.'() : ' . $var . ')))';
				}
				if (trim($match) != '.') { 
					$var = 'function_exists(\''.$match.'\') ? '.$match.'() : ' . $var;
				}
				# replace the expression in the resulting code
				$code = str_replace($matches[0][$key], '\' . (' . $var . ') . \'', $code);
			}
			return $code;
		};
		# parse a template include
		$parseTpl = function($code, $root = '$data') use ($depth, $file) {
			preg_match_all('/\{\{>((?:[^}]|\}[^\}])+)\}\}/m', $code, $matches);
			foreach ($matches[1] as $key => $match) {
				# recursively parse templates includes via {{>blalbll}}
				$code = str_replace($matches[0][$key], template(dirname((file_exists($file.'.tpl') ? $file : __FILE__))."/".trim($match), null, $root, false, $depth + 1), $code);
			}
			# return the non-evaluated result
			return $code;
		};

		# parse expressions
		preg_match_all('/\{\{(#|\^)((?:[^}]|\}[^}])+)\}\}((?:[^{]|\{[^{]|\{\{[^#\^])*)\{\{\/\2\}\}/m', $code, $matches); 
		foreach ($matches[1] as $key => $match) { 
			# parse the respective expression's variable
			list($var, $obj) = $parseVar($matches[2][$key]);
			# if the current expression starts with a ^
			$parts = explode("->", $obj);
			$pre = implode("->", array_slice($parts, 0, -1));
			$post = array_pop($parts);
			if ($obj === $var) {
				$replacement = '\'; $__l'.$depth.' = (is_object('.$var.') && is_callable('.$var.') ? '.$var.'() : isset('.$var.') ? '.$var.' : \'\');';
			} else {
				$replacement = '\'; $__l'.$depth.' = (isset('.$obj.') ? '.$obj.' : (isset('.$pre.') && is_callable(Array('.$pre.', \''.$post.'\')) ? '.$obj.'() : (!is_object('.$pre.') && isset('.$var.') ? '.$var.' : \'\')));';
			}
			if ($match == '^') {
				# then we check if the variable is empty
				$code = str_replace($matches[0][$key], $replacement . 'if (empty($__l'.$depth.')) { $__p .= \'' . $matches[3][$key] . '\'; } unset($__l'.$depth.');$__p .= \'', $code);
			} else if ($match == '#') {
				# else we treat it like a loop
				$replacement .= 'if (!empty($__l'.$depth.')) { if (is_array($__l'.$depth.')) foreach ($__l'.$depth.' as $counter => $entry'.$depth.') { $__p .= \'';
				$replacement .= $parseVars($parseTpl($matches[3][$key], '$entry'.$depth), '$entry'.$depth) . '\'; } else { $__p .= \'' . $matches[3][$key] . '\'; }} unset($__l'.$depth.'); $__p .= \'';
				$code = str_replace($matches[0][$key], $replacement, $code);
			}
		}
		$code = $parseVars($parseTpl($code, $rootCore), $rootCore);
		# if the result should be evaluated
		if ($eval) {
			# do the evaluation
			$code = '$__p = \'' . $code . '\';';
			eval($code);
			# and return the resulting string
			return $__p;
		}
		return $code;
	}
}

# the DEBUGGER functions
if (!function_exists("debugger")) {
	$break = false;

	# 
	# debugger() -> void
	# 
	# This function stops the execution at the current point and renders the current program status (defined variables,
	# the current line of code that is executed next, call stack and some controls). The controls can be used to
	# resume execution, show the stack trace, do step-wise execution, and stop further execution of the program. 
	# It is designed to work even without Xdebug and other debugging extensions and works from within a web interface 
	# and the command line interface. 
	#
	# Pre-condition for the debugger() function to work is, that the Snow code was compiled with the -d compiler
	# switch (if using the `snow.php` script) or the third parameter of the SnowCompiler constructor was set to
	# `true` (if using a custom script to compile/execute snow code). 
	#
	function debugger() {
		global $break;
		$break = true;
	}

	# this is a "private" function for the debugger, which renders a breakpoint
	function breakpoint($vars = null) {
		global $break;
		if (!$break) return;
		set_time_limit(600);
		# if we're not on the command line
		if (PHP_SAPI !== 'cli') {
			# we probably are on a web server => prepare the environment to support our implicit flush
			if (function_exists("apache_setenv")) @apache_setenv('no-gzip', 1);
    		@ini_set('zlib.output_compression', 0);
    		@ini_set('implicit_flush', 1);
			$id = str_replace(".", "-", microtime(true));
			# have the background php file & log file be created
			$log = '__debug_action_current.log';
			$run = '__debug_action_snow.php';
			file_put_contents("./$run", "<?php\nfile_put_contents('./$log', json_encode(\$_GET), LOCK_EX);echo base64_decode('R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');?".">", LOCK_EX);
			# and write out the controls for debugging
			echo "<div class='__snow_debug' id='__snow_debug$id'><button onclick='new Image().src=\"$run?x=n&l=\"+(+new Date());'>step</button><button onclick='document.getElementById(\"__snow_stack$id\").style.display=\"block\";'>call stack</button><button onclick='new Image().src=\"$run?x=c&l=\"+(+new Date());'>resume</button><button onclick='new Image().src=\"$run?x=t&l=\"+(+new Date());'>stop</button><br/><pre>";
			ob_start();
		} else {
			# we're on the command line => clear the screen so that we see what happens
			echo chr(27) . "[2J" . chr(27) . "[;H";
		}
		$trace = debug_backtrace();
		echo $trace[0]['file'].":".(empty($trace[1])?'{main}' : $trace[1]["function"]) . " - Line " . $trace[0]["line"]."\n";
		if (file_exists($trace[0]['file'])) {
			$file = array_slice(file($trace[0]['file']), max(0, $trace[0]['line'] - 1), 1);
			echo preg_replace('/\\bbreakpoint\\(get_defined_vars\\(\\)\\);/m', '', implode("\n", $file));
		}
		print_r(@array_diff($vars, Array(Array())));
        if (PHP_SAPI === 'cli') {
        	# on the command line, we show the controls after rendering all environment data
        	$msg = "\nDEBUGGER HALT.\n[RETURN] - step next\n[P]+[RETURN] - print call stack\n[SPACE]+[RETURN] - resume\n"; 
        	echo $msg;
        	# read in the user input in order for him to control the behavior
			while ($c=fgets(STDIN, 1024)) {
				if ($c == "\n") {
					# just return, then next step
					return;
				} else if ($c == " \n") {
					# space + return: resume execution
					$break = false;
					return;
				} else if ($c == "p\n") {
					# p + return: print call stack but don't do the next step yet
					$e = new Exception();
					echo implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 1));
		        	echo $msg;
				}
			}
		} else {
			# we're on a web server, so we just print out the stack trace directly
			echo ob_get_clean()."</pre><pre id='__snow_stack$id' style='display:none;'>";
			$e = new Exception();
			echo implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 1));
			# clean up the HTML structure printed out
			echo "</pre></div>" . str_repeat(" ", 1024);
			# flush all prepared output to the client
			for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
			flush();
			ob_implicit_flush(1);
			$die = false;
			# when wait
			while (1) {
				sleep(.25);
				# until we find something useful in the action log file
				if (file_exists("$log")) {
					$action = @json_decode(file_get_contents($log))->x;
					@unlink($log);
					# if it's a "c", then we should resume execution
					if ($action === "c") $break = false;
					# for a "t", we just terminate any further execution
					if ($action === "t") { $die = true; }
					# for any "n", "c" or "t", we stop waiting and proceed 
					if (in($action, "nct")) break;
				}
 			}
 			# ok we need to clean up the debug script
			@unlink($run);
			# hide the last output
			echo "<style type='text/css'>div#__snow_debug$id { display: none; }</style>" . str_repeat(" ", 1024);
			# if necessary, terminate further execution
			if ($die) die();
			# else just flush it out to the client and proceed
			flush();
			ob_implicit_flush(1);
			sleep(.01);
		}
		set_time_limit(30);
	}
}