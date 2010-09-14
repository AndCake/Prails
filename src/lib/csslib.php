<?php

class CSSLib {
	
	private $prefix;
	private $styles;
	private $time;
	private $obj_sql;
	
	function __construct($styles=Array()) {
		$this->styles = $styles;
		$this->prefix = md5(implode("", $styles));
		$this->obj_sql = new TblClass();
	}
	
	/**
	 * cleans any previousy cached version of the CSS file combination, 
	 * but only if needed (actual files changed)
	 * 
	 * @return 
	 */
	function cleanOldCache() {
		$dp = opendir("cache/");
		while (($file = readdir($dp)) !== false) {
			if (strpos($file, $this->prefix) !== false) {
				$points = explode(".", $file);
				$this->time = (int)$points[1];
				break;
			}
		}
		closedir($dp);
		
        foreach ($this->styles as $css) {
        	if (strpos($css["path"], "http://") === false && strpos($css["path"], "ftp://") === false && strpos($css["path"], "https://") === false) { 
	        	if (@filectime($css["path"]) > $time) {
	        		// we need to regenerate the CSS files
					@unlink("cache/".$this->prefix.".".$this->time.".css");
					if (file_exists("cache/".$this->prefix.".".$this->time.".cgz")) {
						@unlink("cache/".$this->prefix.".".$this->time.".cgz");
					}
					$this->time = time();
					break;				
	        	}
			}
		}		
	}
	
	function collectStyles($path) {
		$fp = fopen($path, "w+");
		foreach ($this->styles as $style) {
			$content = file_get_contents($style);
			$fpath = str_replace(basename($style), "", $style);
			preg_match_all('@url\(["\']{0,1}([^)\'"]+)["\']{0,1}\)@', $content, $matches);
			if (count($matches[1]) > 0) {
				foreach ($matches[1] as $pos=>$match) {
					$content = str_replace($matches[0][$pos], "url(\"../".($fpath.str_replace(basename($match), "", $match)).basename($match)."\")", $content);
				}
			}
			$content = "\n\n/* FETCHED FROM FILE ".$style." */\n".$content;
			fwrite($fp, $content);
		}
		fclose($fp);
	}
	
	function lessifyCSS($css) {
		// convert less to css
		// first find all variable definitions
		preg_match_all('/((@[a-z_A-Z\-]+)\s*:\s*([^{};]+)\s*;)/', $css, $matches);
        if (is_array($matches[0])) foreach ($matches[0] as $key=>$match) {
            $css = str_replace($matches[1][$key], "", $css);
            $css = str_replace($matches[2][$key], $matches[3][$key], $css);
        }
        
        preg_match_all('/([\.]([a-zA-Z\-_]+))\s*(\(([^)]+)\))?\s*;/', $css, $matches);
        if (is_array($matches[0])) foreach ($matches[0] as $key => $match) {
            // find the corresponding mixin snippet
            preg_match('/'.str_replace(".", "\\.", $matches[1][$key]).'\s*(\((@[a-z_A-Z\-]+)\s*:\s*([^{})]+)\s*\))?\s*{([^}]+)}/', $css, $mixin);
            if (!empty($mixin[2])) {    // if it's a dynamic mixin
                // and the call was not giving an argument, then use the default
                if (empty($matches[4][$key])) $matches[4][$key] = $mixin[3];
                // create the actual mixin content by applying the dynamic variable by it's value
                $mixin[4] = str_replace($mixin[2], $matches[4][$key], $mixin[4]);
            }
            // finally replace the mixin call by it's contents
            $css = str_replace($match, $mixin[4], $css);
        }
        
        // remove all dynamic mixin declarations
        preg_match_all('/([\.]([a-zA-Z\-_]+))\s*(\((@[a-z_A-Z\-]+)\s*:\s*([^{})]+)\s*\))\s*{([^}]+)}/', $css, $mixins);
        if (is_array($mixins[0])) foreach ($mixins[0] as $key => $mixin) {
            $mixedUpIn = $mixins[1][$key]." {".str_replace($mixins[4][$key], $mixins[5][$key], $mixins[6][$key])."}";
            $css = str_replace($mixin, $mixedUpIn, $css);
        }
		return $css;		
	}
	
	function minifyCSS($css) {
		// minify css result            
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
	
		return $css;		
	}
	
	function embedResources($css) {
		global $SERVER;
		
		// try finding URLs and replace them by resources
		preg_match_all('/(([^\nu]|u[^\nr]|ur[^\nl]|url[^\n(])+)\s*url\(([^)]+)\)\s*[^;\n]+/u', $css, $matches);
		$headerArea = "/*\r\nContent-Type: multipart/related; boundary=\"_ANY_SEPARATOR\"\r\n\r\n";
		if (count($matches[3]) > 0) {
			foreach ($matches[3] as $key=>$match) {
				$oMatch = $match;
				$match = trim(str_replace("'", '', str_replace('"', "", $match)));
				// check if it is an image
				if (strpos($match, ".png") !== false || strpos($match, ".gif") !== false ||
					strpos($match, ".jpg") !== false || strpos($match, ".jpeg") !== false) {
					preg_match('@templates/([^/0-9]+)([0-9]+)/.*/images/(.*)$@', $match, $pats);
					if (strlen($pats[1]) > 0) {
						$file = @array_pop($this->obj_sql->SqlQuery("SELECT a.* FROM tbl_prailsbase_resource AS a, tbl_prailsbase_module AS b WHERE a.name='".$pats[3]."' AND b.name='".$pats[1]."' AND b.module_id=a.fk_module_id"));
						// apply inline-images just for smaller images (each less than 128kB in Base64) 
						if ($file && strlen($file["data"]) < 131072) {
							$id = md5($pat[1].$pat[2].$file["resource_id"]);
							$headerArea .= "--_ANY_SEPARATOR\r\n";
							$headerArea .= "Content-Location:".$id."\r\n";
							$headerArea .= "Content-Transfer-Encoding:base64\r\n\r\n";
							$headerArea .= trim($file["data"])."\r\n";
							$newUrl = "\"data:".$file["type"].";base64,".trim($file["data"])."\"";
							$newUrl2 = "mhtml:".$SERVER.str_replace(".css", ".header.css", $path)."!".$id;
							$line = "\t".str_replace($oMatch, $newUrl, trim($matches[0][$key])).";\n";
							$line .= "\t".str_replace($oMatch, $newUrl2, "*".trim($matches[0][$key])).";\n";
							$css = str_replace($matches[0][$key], $line, $css);
						}
					}
				}
			}
		}
		$headerArea .= "*/\r\n";

		return Array("css" => $css, "header" => $headerArea);		
	}
	
	/**
	 * Merges all CSS files into one cached file, converts it into _real_ CSS
	 * (so compiles any LESS code), minifies it, embeds needed resources and
	 * finally save them as CSS and packed CSS files.
	 * 
	 * @param boolean $bol_minify[optional] if the result should be minified, this is true (default=false)
	 * @return $path - Path to the generated file 
	 */
	function mergeStyles($bol_minify = false, $embed = true) {
		$path = "cache/".$this->prefix.".".$this->time.".css";
		if (!file_exists($path)) {
			$this->collectStyles($path);

			$css = file_get_contents($path);
			$css = $this->lessifyCSS($css);			
            
            if ($bol_minify) {
            	$css = $this->minifyCSS($css);
    	    }
			
			if ($embed) {
				$data = $this->embedResources($css);
				$css = $data["css"];
				$headerArea = $data["header"];
			} else {
				$headerArea = "";
			}
			
        	file_put_contents($path, $css=trim($css));
			file_put_contents(str_replace(".css", ".cgz", $path), gzencode($css, 9));
			
			if (strlen($headerArea) > 0) {
				file_put_contents(str_replace(".css", ".header.css", $path), $headerArea);
				file_put_contents(str_replace(".css", ".header.cgz", $path), gzencode($headerArea, 9));
			}
		}
		
		return $path;
	}
}

?>