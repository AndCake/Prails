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

/** Class OutputGenerator
 * 
 * This class is used to manage all output-related meta information, like what caching policy applies to the current page, 
 * the HTML header information being sent to the client, the stylesheets to be used for rendering the page, javascripts, 
 * and the ouput policy.
 **/
class OutputGenerator {
    static $obj_instance;

    var $str_template;
    var $str_title;
    var $str_description;
    var $arr_keywords;
    var $str_navigation;
    var $str_currentLanguage;
    var $obj_lang;
    var $arr_styles;
    var $arr_noCacheStyles;
    var $arr_header;
    var $arr_js;
    var $arr_noCacheJS;
    var $int_time;
    var $obj_mod;
    var $bol_isCachable;
    var $bol_isAjax;
    var $str_cacheId;

    function __construct() {
        $this->int_time = 0;
        $this->str_template = DEFAULT_TEMPLATE;
        $this->str_title = "";
        $this->arr_styles = Array ();
        $this->arr_js = Array ();
        $this->arr_header = Array();
        $this->obj_lang = null;
        $this->bol_isCachable = false;
        $this->arr_noCacheStyles = Array();
        $this->arr_noCacheJS = Array();
    }

    /** 
     * getInstance() -> OutputGenerator
     * 
     * This is a static method that will create a generator and return it. In case an existing generator is found, that one will be returned.
     **/
    static function getInstance() {
        if (OutputGenerator :: $obj_instance) {
            return OutputGenerator :: $obj_instance;
        } else {
            OutputGenerator :: $obj_instance = new OutputGenerator();
            return OutputGenerator :: $obj_instance;
        }
    }

    /**
     * setIsCachable([$cachable]) -> void
     * - $cachable (Boolean) - if `true`, the current page will be cached on production. Defaults to `true`.
     *
     * sets the cache to enabled or disabled. By default no page is cached, except for static content pages. By calling this method, you can activate caching for the current page.
     * This can also be achieved by the respective checkbox at the handler. 
     **/
    function setIsCachable($bol_cachable = true) {
        $this->bol_isCachable = $bol_cachable;
    }

    /**
     * setIsAjax([$ajax]) -> void
     * - $ajax (Boolean) - if `true`, the current page won't be decorated with the basic HTML structure. Defaults to `true`.
     * 
     * This method defines whether or not the current event handler will be requested via AJAX and thus should not be decorated with the HTML structure as it will be included
     * into another page (which already has one). By default, no page is an AJAX page. It can also be achieved via the respective checkbox at the handler.
     **/
    function setIsAjax($bol_ajax = true) {
        $this->bol_isAjax = $bol_ajax;
    }

    function setLanguage($lang) {
    	global $currentLang;
        $this->obj_lang = new LangData($lang);
        $currentLang = $this->obj_lang;
    }

    /**
     * getLanguage() -> Language
     *
     * returns the language object that can be used to access translation data and content assets. This basically returns the same as `$currentLang` within an event handler.
     **/
    function getLanguage() {
        return $this->obj_lang;
    }

    function setCacheId($id) {
        $this->str_cacheId = $id;
        if (!is_dir("cache/".$id)) {
            mkdir("cache/".$id);
        }
    }
    
    function generateOutput($str_content) {
        if ($_SERVER["REQUEST_METHOD"] == "POST") $this->bol_isCachable = false;
        if ($this->bol_isAjax) {
            if (strlen($this->str_cacheId) > 0) {
                $cacheFile = "cache/page_".$this->str_cacheId.md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]).".".$this->obj_lang->language_id;
            } else {
                $cacheFile = "cache/page_".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]).".".$this->obj_lang->language_id;
            }
            $content = $str_content;
            $content = str_replace("<!!", "<"."?", $content);
            $content = str_replace("!!>", "?".">", $content);
            $fp = fopen($cacheFile, "w+");
            fwrite($fp, $content);
            fclose($fp);

            // continue interpretion of code... (post processing of session data)
            // eval everything that is between <!! and !!>
            if (file_exists($cacheFile)) require($cacheFile);
            if (!$this->bol_isCachable) {
                @unlink($cacheFile);
            }
            if (PROFILING_ENABLED === true) {
            	global $profiler;
            	$profiler->logEvent("page_no_cache_hit#".$_SERVER["REQUEST_URI"]);
            }
            
            session_write_close();
            die();
        } else {
            ob_start();
            require ($this->str_template);
            $content = ob_get_contents();
            ob_end_clean();
            $content = str_replace("<!!", "<"."?", $content);
            $content = str_replace("!!>", "?".">", $content);
        	if ($this->bol_isCachable) {
                
                if (strlen($this->str_cacheId) > 0) {
                    $cacheFile = "cache/page_".$this->str_cacheId.md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]).".".$this->obj_lang->language_id;
                } else {
                    $cacheFile = "cache/page_".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]).".".$this->obj_lang->language_id;
                }
                
                $fp = fopen($cacheFile, "w+");
                fwrite($fp, $content);
                fclose($fp);

                // continue interpretion of code... (post processing of session data)
                // eval everything that is between <!! and !!>
                if (file_exists($cacheFile))
                	require($cacheFile);
            } else {
                $name = tempnam(realpath("cache"), time());

                $fp = fopen($name, "w+");
                fwrite($fp, $content);
                fclose($fp);

                // continue interpretion of code... (post processing of session data)
                // eval everything that is between <!! and !!>
                require($name);
                @unlink($name);
                session_write_close();
            }
        }
    }

    function removeCache($str_path, $bol_subsites = false)
    {
        $dp = opendir("cache/");
        while (($file=readdir($dp)) !== false)
        {
            $isCurrent = strpos($file, md5($str_path)) == (strlen($file) - strlen(md5($str_path)));
            @unlink("cache/".$file);
        }
        closedir($dp);
    }

    function parseApplyLanguage($buffer) {
        preg_match_all("/\\{([a-zA-Z0-9-]+\\.[a-zA-Z0-9.-]+)(\\\$?)\\}/", $buffer, $arr_matches);
        foreach ($arr_matches[1] as $key => $str_match) {
        	if (strpos($buffer, "{".$str_match.(strlen($arr_matches[2][$key]) > 0 ? '$' : '')."}") !== false) {
			    $text = $this->obj_lang->getText($str_match);
			    if (ENV_PRODUCTION != true && strlen($arr_matches[2][$key])<=0) {
			        $text = "<!--[LANG:".$str_match."]-->" . $text . "<!--[/LANG:".$str_match."]-->";
			    }
			    $buffer = str_replace($arr_matches[0][$key], $text, $buffer);
        	}
        }

        return $buffer;
    }
    
    /**
     * concept ideas for new caching algorithm:
     *	- definition of data sinks (=event)
     *	- when change event for certain data occurs, data sink is used to re-generate the templates
     *	- sub templates are also cached
     *
     */
    function includeTemplate($str_name, $arr_param = null, $bol_parseLanguage = true) {
		$startTime = time()+microtime();
		$nname = "cache/".md5($str_name).microtime(true);
		$tl = new TagLib($str_name);
		$str_content = $tl->compile(file_get_contents($str_name));
		if (!file_put_contents($nname, $str_content, LOCK_EX)) {
			global $log;
			$error = error_get_last();
			$log->fatal("Unable to create cache entry! Please enable write access to all files and folders within the Prails directory." . $error['message'] . $error['line']);
		}		
    	unset($str_content, $tl);
        $param = &$arr_param;
		
        ob_start();
        require ($nname);
        $str_content = ob_get_contents();
        ob_end_clean();
        @unlink($nname);
        unset($nname);
		if ($bol_parseLanguage) {
            $str_content = $this->parseApplyLanguage($str_content);
		}
		$endTime = time()+microtime();

        if (substr(basename($str_name), -5) == ".html" || substr(basename($str_name), -4) == ".xml" ) {
            $str_content = "<!-- TEMPLATE " . $str_name . " (".round($endTime - $startTime, 4)."s) -->\n" . $str_content."\n<!-- END TEMPLATE ".$str_name." -->\n";
        }
        
        return $str_content;
    }

    /**
     * setTitle($title) -> void
     * - $title (String) - the new page's title
     *
     * This method sets the current page's HTML title.
     **/
    function setTitle($str_title, $bol_override = true) {
    	if ($bol_override || strlen($this->str_title) == 0)
        	$this->str_title = $str_title;
    }

    /**
     * getTitle() -> String
     *
     * This method returns the currently set HTML title.
     **/
    function getTitle() {
        return $this->str_title;
    }

    /**
     * setDescription($desc) -> void
     * - $desc (String) - the page's meta description
     *
     * This method sets the current page's HTML meta description.
     **/
    function setDescription($str_desc, $bol_override = true) {
    	if ($bol_override || strlen($this->str_description) == 0)
        	$this->str_description = $str_desc;
    }

    /**
     * getDescription() -> String
     *
     * This method returns the currently set HTML meta description.
     **/
    function getDescription() {
        return $this->str_description;
    }

    /**
     * setKeywords($keywords) -> void
     * - $keywords (Array|String) - the list of keywords to be set, can either be a comma-separated string of keywords or an array of keywords.
     *
     * This method sets the current page's HTML meta keywords.
     **/
    function setKeywords($mixed) {
        $this->arr_keywords = Array();
        if (is_array($mixed))
        {
            $this->arr_keywords = array_merge($this->arr_keywords, $mixed);
        } else {
            array_push($this->arr_keywords, $mixed);
        }
    }

    /**
     * getKeywords() -> String
     * 
     * This method returns the currently set HTML meta keywords as a comma-separated list.
     **/
    function getKeywords() {
        $str_k = "";
        foreach ($this->arr_keywords as $str_keyword) {
            if (strlen($str_k) > 0) $str_k .= ",";
            $str_k .= $str_keyword;
        }
        return $str_k;
    }

    function getStyleSheets() {
		global $SERVER;
        $str_styles = "";

		$time = time();
		$styles = Array();
		foreach ($this->arr_styles as $style) {
            if ($style["browser"] == "all" && file_exists($style["path"])) {
            	array_push($styles, $style["path"]);
			} else if ($style["browser"] == "all" && !file_exists($style["path"])) {
				array_push($this->arr_noCacheStyles, $style);
			}
		}

		$cssLib = new CSSLib($styles);
		$cssLib->cleanOldCache();
		$path = $cssLib->mergeStyles(ENV_PRODUCTION === true, CSS_EMBED_RESOURCES);

		$str_styles .= "<link rel='stylesheet' type='text/css' media='screen' href='".str_replace('http:', '', $SERVER).$path."' />\n";
		$str_styles .= "<!--[if lte IE 7]><link rel='stylesheet' media='screen' href='".str_replace('http:', '', $SERVER).str_replace(".css", ".header.css", $path)."' /><![endif]-->";
		
		if (is_array($this->arr_noCacheStyles)) foreach ($this->arr_noCacheStyles as $ncs) {
			$str_styles .= "<link rel='stylesheet' media='".$ncs["media"]."' href='".$ncs["path"]."' />\n";
		}

		foreach ($this->arr_styles as $style) {
            if ($style["browser"] != "all") {
                if ($style["browser"] == "IE")
                {
                    $str_styles .= "<!--[if IE]>\n";
                    $str_styles .= "<link rel='stylesheet' media='" . $style["media"] . "' href='" . $style["path"] . "' />\n";
                    $str_styles .= "<![endif]-->";
                } else if (is_array($style["browser"])) {
                    $str_styles .= "<!--[if ".$style["browser"]["operator"]." IE ".$style["browser"]["version"]."]>\n";
                    $str_styles .= "<link rel='stylesheet' media='" . $style["media"] . "' href='" . $style["path"] . "' />\n";
                    $str_styles .= "<![endif]-->";
                } else {
                    $str_styles .= "<![if !IE]>\n";
                    $str_styles .= "<link rel='stylesheet' media='" . $style["media"] . "' href='" . $style["path"] . "' />\n";
                    $str_styles .= "<![endif]>";
                }
            }
        }
        return $str_styles;
    }

    /**
     * addStyleSheet($path) -> void
     * - $path (String) - the path to the stylesheet to be included.
     *
     * This method includes a style sheet into the current page. It can contain LESS CSS code. It can be located on the same or on a different server.
     **/
    function addStyleSheet($path, $media = "screen", $browser = "all") {
        // check if stylesheet has already been loaded
        if ($media === false || in_array(substr($path, 0, 6), Array("http:/", "https:", "ftp://"))) {
	        foreach ($this->arr_noCacheStyles as $arr_style) {
	            if ($path == $arr_style["path"])
	            return;
	        }
	        array_push($this->arr_noCacheStyles, Array (
				"media" =>"screen",
				"path" => $path,
				"browser"=> $browser
	        ));
        } else {
	        foreach ($this->arr_styles as $arr_style) {
	            if ($path == $arr_style["path"])
	            return;
	        }
	        array_push($this->arr_styles, Array (
				"media" => ($media === true ? "screen" : $media),
				"path" => $path,
				"browser"=> $browser
	        ));
        } 
    }

    function getJavaScripts() {
		global $SERVER;
    	$str_js = "";
    	
    	if (ENV_PRODUCTION === true) {
	    	$time = time();
	        $prefix = md5(implode("", $this->arr_js));
	    	$dp = opendir("cache/");
	        while (($file = readdir($dp)) !== false) {
	    		if (strpos($file, $prefix) !== false) {
	    			$points = explode(".", $file);
	    			$time = (int)$points[1];
	    			break;
	    		}
	    	}
			
	        foreach ($this->arr_js as $js) {
	        	if (@filectime($js) > $time) {
	        		// we need to regenerate the javascript files
					@unlink("cache/".$prefix.".".$time.".js");
					if (file_exists("cache/".$prefix.".".$time.".jsgz")) {
						@unlink("cache/".$prefix.".".$time.".jsgz");
					}
					$time = time();
					break;				
	        	}
	    	}
	    		
	    	$path = "cache/".$prefix.".".$time.".js";
	    	if (!file_exists($path)) {
	    		$fp = fopen($path, "w+");
	    		$gp = gzopen(str_replace(".js", ".jsgz", $path), "w9");
				$gzData = "";
	            foreach ($this->arr_js as $js) {
	            	if (file_exists($js)) {
		            	$str = ";".file_get_contents($js)."\n";
	    			    $str = JSMIN::minify($str);
		            	fwrite($fp, $str);
						gzwrite($gp, $str);
	            	} else {
	            		if (!is_array($this->arr_noCacheJS)) $this->arr_noCacheJS = Array();
	            		array_push($this->arr_noCacheJS, $js);
	            	}
	            }
	    		fclose($fp);
	    		gzclose($gp);
	           	@chmod($path, 0755);
	           	@chmod(str_replace(".js", ".jsgz", $path), 0755);
	        }
	        $str_js .= "<script src='" . str_replace('http:', '', $SERVER).$path . "' type='text/javascript'></script>\n";
	        
	        if (is_array($this->arr_noCacheJS)) {
	        	foreach ($this->arr_noCacheJS as $ncjs) {
		        	$str_js .= "<script src='" . $ncjs . "' type='text/javascript'></script>\n";
	        	}
	        }
    	} else {
    		// for development environments we keep the JS files separate
    		if (is_array($this->arr_js)) {
    			foreach ($this->arr_js as $js) {
		        	$str_js .= "<script src='" . $js . "' type='text/javascript'></script>\n";
    			}
    		}
	        if (is_array($this->arr_noCacheJS)) {
	        	foreach ($this->arr_noCacheJS as $ncjs) {
		        	$str_js .= "<script src='" . $ncjs . "' type='text/javascript'></script>\n";
	        	}
	        }    		
    	}
    		
        return $str_js;
    }

    /**
     * addJavaScript($path) -> void
     * - $path (String) - the path to the JS file.
     * 
     * This method includes the given javascript file into the current page. Can only be used for non-AJAX event handlers.
     **/
    function addJavaScript($path, $toCache = true) {
        // check if stylesheet has already been loaded
        if ($toCache) {
	        if (!in_array($path, $this->arr_js)) {
	        	array_push($this->arr_js, $path);
	    	}
        } else {
	        if (!in_array($path, $this->arr_noCacheJS)) {
	        	array_push($this->arr_noCacheJS, $path);
	    	}
        }
    }

    function getHeaders() {
        $str_headers = "";
        foreach ($this->arr_header as $header) {
            $str_headers .= $header;
        }
         
        return $str_headers;
    }

    /**
     * addHeader($header) -> void
     * - $header (String) - the header content as pure HTML code
     *
     * Adds a new HTML code to the `head` section of the resulting HTML page. Can be used, for example, to add Google's Site verification tags or additional meta tags.
     **/
    function addHeader($str_header) {
        if (!in_array($str_header, $this->arr_header)) {
            array_push($this->arr_header, $str_header);
        }
    }
}
?>