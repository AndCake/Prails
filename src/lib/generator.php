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

class Generator {
    static $obj_instance;

    var $str_template;
    var $str_title;
    var $str_description;
    var $arr_keywords;
    var $str_navigation;
    var $str_currentLanguage;
    var $obj_lang;
    var $arr_styles;
    var $arr_header;
    var $arr_js;
    var $int_time;
    var $obj_mod;
    var $bol_isCachable;
    var $bol_isAjax;
    var $str_cacheId;

    function Generator() {
        $this->int_time = 0;
        $this->str_template = DEFAULT_TEMPLATE;
        $this->str_title = "";
        $this->arr_styles = Array ();
        $this->arr_js = Array ();
        $this->arr_header = Array();
        $this->obj_lang = null;
        $this->bol_isCachable = false;
    }

    static function getInstance() {
        if (Generator :: $obj_instance) {
            return Generator :: $obj_instance;
        } else {
            Generator :: $obj_instance = new Generator();
            return Generator :: $obj_instance;
        }
    }

    function setIsCachable($bol_cachable = true) {
        $this->bol_isCachable = $bol_cachable;
    }

    function setIsAjax($bol_ajax = true) {
        $this->bol_isAjax = $bol_ajax;
    }

    function setModule($obj_module) {
        $this->obj_mod = $obj_module;
    }

    function setLanguage($lang) {
        $this->obj_lang = new LangData($lang);
    }

    function getLanguage() {
        $lang = new LangData(0);
        $arr_langs = $lang->listLanguages();
        foreach ($arr_langs as $arr_lang) {
            $str_content .= "<a href='" . $_SERVER["REQUEST_URI"] . (strpos($_SERVER["REQUEST_URI"], "?") !== false ? "&" : "?") . "lang=" . strtolower($arr_lang["abbreviation"]) . "'><img src='templates/main/" . strtolower($arr_lang["abbreviation"]) . ".gif' border='0' alt='" . $arr_lang["name"] . "' /></a>&nbsp;";
        }

        return $str_content;
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
                $cacheFile = "cache/".$this->str_cacheId.md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
            } else {
                $cacheFile = "cache/".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
            }
            $content = $str_content;
            $content = str_replace("<!!", "<"."?", $content);
            $content = str_replace("!!>", "?".">", $content);
            $fp = fopen($cacheFile, "w+");
            fwrite($fp, $content);
            fclose($fp);

            // continue interpretion of code... (post processing of session data)
            // eval everything that is between <!! and !!>
            if (file_exists($cacheFile))
            require($cacheFile);
            if (!$this->bol_isCachable) {
                @unlink($cacheFile);
            }
            die();
        } else {
            if ($this->bol_isCachable)
            {
                ob_start();
                require ($this->str_template);
                $content = ob_get_contents();
                ob_end_clean();
                $content = str_replace("<!!", "<"."?", $content);
                $content = str_replace("!!>", "?".">", $content);
                
                if (strlen($this->str_cacheId) > 0) {
                    $cacheFile = "cache/".$this->str_cacheId.md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
                } else {
                    $cacheFile = "cache/".md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
                }
                
                $fp = fopen($cacheFile, "w+");
                fwrite($fp, $content);
                fclose($fp);

                // continue interpretion of code... (post processing of session data)
                // eval everything that is between <!! and !!>
                if (file_exists($cacheFile))
                require($cacheFile);
            } else
            {
                ob_start();
                require ($this->str_template);
                $content = ob_get_contents();
                ob_end_clean();
                $content = str_replace("<!!", "<"."?", $content);
                $content = str_replace("!!>", "?".">", $content);
                $name = tempnam(realpath("cache"), time());

                $fp = fopen($name, "w+");
                fwrite($fp, $content);
                fclose($fp);

                // continue interpretion of code... (post processing of session data)
                // eval everything that is between <!! and !!>
                require($name);
                @unlink($name);
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
        preg_match_all("/\\{([a-zA-Z0-9.]+)\\}/", $buffer, $arr_matches);

        foreach ($arr_matches[1] as $key => $str_match) {
            $buffer = str_replace($arr_matches[0][$key], $this->obj_lang->getText($str_match), $buffer);
        }

        return $buffer;
    }
    
    /**
     * concept ideas for new caching algorithm:
     *	- definition of data sinks (=event)
     *	- when change event for certain data occurs, data sink is used to re-generate the templates
     *	-
     *	- sub templates are also cached
 
     *
     */
    function includeTemplate($str_name, $arr_param = null) {
		$startTime = time()+microtime();
		
		$tl = new TagLib();
		$str_content = $tl->compile(file_get_contents($str_name));
		file_put_contents("cache/".md5($str_name), $str_content);		
		
        ob_start();
        require ("cache/".md5($str_name));
        $str_content = ob_get_contents();
        ob_end_clean();
		$endTime = time()+microtime();

        if (substr(basename($str_name), -5) == ".html" || substr(basename($str_name), -4) == ".xml" ) {
            return "<!-- TEMPLATE " . $str_name . " (".round($endTime - $startTime, 4)."s) -->\n" . $this->parseApplyLanguage($str_content)."\n<!-- END TEMPLATE ".$str_name." -->\n";
        } else {
            return $this->parseApplyLanguage($str_content);
        }
    }

    function setTitle($str_title, $bol_override = false) {
    	if ($bol_override || strlen($this->str_title) == 0)
        	$this->str_title = $str_title;
    }

    function getTitle() {
        return $this->str_title;
    }

    function setDescription($str_desc, $bol_override = false) {
    	if ($bol_override || strlen($this->str_description) == 0)
        	$this->str_description = $str_desc;
    }

    function getDescription() {
        return $this->str_description;
    }

    function setKeywords($mixed) {
        $this->arr_keywords = Array();
        if (is_array($mixed))
        {
            $this->arr_keywords = array_merge($this->arr_keywords, $mixed);
        } else {
            array_push($this->arr_keywords, $mixed);
        }
    }

    function getKeywords() {
        $str_k = "";
        foreach ($this->arr_keywords as $str_keyword) {
            if (strlen($str_k) > 0) $str_k .= ",";
            $str_k .= $str_keyword;
        }
        return $str_k;
    }

    function getStyleSheets() {
        $str_styles = "";

		$time = time();
		$styles = Array();
		foreach ($this->arr_styles as $style) {
            if ($style["browser"] == "all") {
            	array_push($styles, $style["path"]);
			}
		}

		$prefix = md5(implode("", $styles));
		$dp = opendir("cache/");
		while (($file = readdir($dp)) !== false) {
			if (strpos($file, $prefix) !== false) {
				$points = explode(".", $file);
				$time = (int)$points[1];
				break;
			}
		}
		
        foreach ($this->arr_styles as $css) {
        	if (strpos($css["path"], "http://") === false && strpos($css["path"], "ftp://") === false && strpos($css["path"], "https://") === false) { 
	        	if (@filectime($css["path"]) > $time) {
	        		// we need to regenerate the javascript files
					@unlink("cache/".$prefix.".".$time.".css");
					$time = time();
					break;				
	        	}
			}
		}
		
		$path = "cache/".$prefix.".".$time.".css";
		if (!file_exists($path)) {
			$fp = fopen($path, "w+");
			foreach ($styles as $style) {
				$content = file_get_contents($style);
				$fpath = str_replace(basename($style), "", $style);
				preg_match_all('@url\(["\']{0,1}([^)\'"]+)["\']{0,1}\)@', $content, $matches);
				if (count($matches[1]) > 0) {
					foreach ($matches[1] as $pos=>$match) {
						$content = str_replace($matches[0][$pos], "url(\"../".($fpath.str_replace(basename($match), "", $match)).basename($match)."\")", $content);
					}
				}
				fwrite($fp, $content);
			}
			fclose($fp);
			// minify css result
			$css = file_get_contents($path);
			$css = str_replace("\r\n", "\n", $css);
	        $css = preg_replace('@>/\\*\\s*\\*/@', '>/*keep*/', $css);
	        $css = preg_replace('@/\\*\\s*\\*/\\s*:@', '/*keep*/:', $css);
	        $css = preg_replace('@:\\s*/\\*\\s*\\*/@', ':/*keep*/', $css);
    	    $css = preg_replace('/\\s*{\\s*/', '{', $css);
	        $css = preg_replace('/;?\\s*}\\s*/', '}', $css);
	        $css = preg_replace('/\\s*;\\s*/', ';', $css);
	        $css = preg_replace('/
	                url\\(      # url(
	                \\s*
	                ([^\\)]+?)  # 1 = the URL (really just a bunch of non right parenthesis)
	                \\s*
	                \\)         # )
	            /x', 'url($1)', $css);
	        $css = preg_replace('/
	                \\s*
	                ([{;])              # 1 = beginning of block or rule separator 
	                \\s*
	                ([\\*_]?[\\w\\-]+)  # 2 = property (and maybe IE filter)
	                \\s*
	                :
	                \\s*
	                (\\b|[#\'"])        # 3 = first character of a value
	            /x', '$1$2:$3', $css);
	        $css = preg_replace('/([^=])#([a-f\\d])\\2([a-f\\d])\\3([a-f\\d])\\4([\\s;\\}])/i', '$1#$2$3$4$5', $css);
	        $css = preg_replace('/@import\\s+url/', '@import url', $css);
	        $css = preg_replace('/[ \\t]*\\n+\\s*/', "\n", $css);
        	$css = preg_replace('/([\\w#\\.\\*]+)\\s+([\\w#\\.\\*]+){/', "$1\n$2{", $css);
	        $css = preg_replace('/
            	((?:padding|margin|border|outline):\\d+(?:px|em)?) # 1 = prop : 1st numeric value
            	\\s+
            	/x', "$1\n", $css);
	        $css = preg_replace('/:first-l(etter|ine)\\{/', ':first-l$1 {', $css);
        	file_put_contents($path, trim($css));
		}
		$str_styles .= "<link rel='stylesheet' media='screen' href='".$path."' />\n";

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

    function addStyleSheet($path, $media = "screen", $browser = "all") {
        // check if stylesheet has already been loaded
        foreach ($this->arr_styles as $arr_style) {
            if ($path == $arr_style["path"])
            return;
        }
        array_push($this->arr_styles, Array (
			"media" => $media,
			"path" => $path,
			"browser"=> $browser
        ));
    }

    function getJavaScripts() {
    	$str_js = "";
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
				$time = time();
				break;				
        	}
    	}
    		
    	$path = "cache/".$prefix.".".$time.".js";
    	if (!file_exists($path)) {
    		$fp = fopen($path, "w+");
            foreach ($this->arr_js as $js) {
            	$str = file_get_contents($js);
                if (ENV_PRODUCTION === true) {
    			    $str = JSMIN::minify($str);
    			}
            	fwrite($fp, $str);
            }
    		fclose($fp);
           	@chmod($path, 0755);
        }
        $str_js .= "<script src='" . $path . "' type='text/javascript'></script>\n";
    		
        return $str_js;
    }

    function addJavaScript($path) {
        // check if stylesheet has already been loaded
        if (!in_array($path, $this->arr_js)) {
        	array_push($this->arr_js, $path);
    	}
    }

    function getHeaders() {
        $str_headers = "";
        foreach ($this->arr_header as $header) {
            $str_headers .= $header;
        }
         
        return $str_headers;
    }

    function addHeader($str_header) {
        if (!in_array($str_header, $this->arr_header)) {
            array_push($this->arr_header, $str_header);
        }
    }
}
?>
