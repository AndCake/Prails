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
class CSSLib {
	
	private $prefix;
	private $styles;
	private $time;
	private $obj_sql;
	
	function __construct($styles=Array()) {
		$this->styles = $styles;
		$this->prefix = md5(implode("", $styles));
		if (IS_SETUP) {
			$this->obj_sql = new Database("tbl_prailsbase_");
		}
	}
	
	/**
	 * cleans any previousy cached version of the CSS file combination, 
	 * but only if needed (actual files changed)
	 * 
	 * @return 
	 */
	function cleanOldCache() {
		$this->time = 0;
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
        	if (strpos($css, "http://") === false && strpos($css, "ftp://") === false && strpos($css, "https://") === false) { 
	        	if (@filectime($css) > $this->time && file_exists("cache/".$this->prefix.".".$this->time.".css")) {
	        		// we need to regenerate the CSS files
					@unlink("cache/".$this->prefix.".".$this->time.".css");
					if (file_exists("cache/".$this->prefix.".".$this->time.".cssgz")) {
						@unlink("cache/".$this->prefix.".".$this->time.".cssgz");
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
					if (strpos($match, "data:") !== 0 && strpos($match, "http:") !== 0 && strpos($match, "https:") !== 0) {
						$content = str_replace($matches[0][$pos], "url(\"../".($fpath.str_replace(basename($match), "", $match)).basename($match)."\")", $content);
					}
				}
			}
			$content = "\n\n/* FETCHED FROM FILE ".$style." */\n".$content;
			fwrite($fp, $content);
		}
		fclose($fp);
	}
	
	function lessifyCSS($css) {
		// convert less to css
		
		$less = new lessc();
		$css = $less->parse($css);

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
	
	function embedResources($css, $path = "") {
		global $SERVER;
		
		// try finding URLs and replace them by resources
		preg_match_all('/(([^\nu]|u[^\nr]|ur[^\nl]|url[^\n(])+)\s*url\(([^)]+)\)\s*[^;\n]+/u', $css, $matches);
		$headerArea = "/*\r\nContent-Type: multipart/related; boundary=\"_ANY_SEPARATOR\"\r\n\r\n";
		if (count($matches[3]) > 0) {
			foreach ($matches[3] as $key=>$match) {
				$matches[0][$key] = preg_replace('/(([^{;]+(\{|;)))/', "", $matches[0][$key]);
				$matches[0][$key] = trim($matches[0][$key], "}");
				$oMatch = $match;
				$match = trim(str_replace("'", '', str_replace('"', "", $match)));
				// check if it is an image
				if (strpos($match, ".png") !== false || strpos($match, ".gif") !== false ||
					strpos($match, ".jpg") !== false || strpos($match, ".jpeg") !== false) {
					preg_match('@templates/([^/0-9]+)([0-9]*).*/images/([^)]+)$@', $match, $pats);
					if (strlen($pats[1]) > 0 && ($pats[1] != "builder" && $pats[1] != "main")) {
						$file = @array_pop($this->obj_sql->SqlQuery("SELECT a.* FROM tbl_prailsbase_resource AS a, tbl_prailsbase_module AS b WHERE a.name='".$pats[3]."' AND LOWER(b.name)='".$pats[1]."' AND b.module_id=a.fk_module_id"));
						// apply inline-images just for smaller images (each less than 32kB in Base64)
						if ($file && strlen($file["data"]) <= 19660) {
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
		if (file_exists($path)) return $path;
			
		// remove previous version of that file
		$dp = opendir("cache/");
		while (($file = readdir($dp)) !== false) {
			if (substr($file, 0, strlen($this->prefix)) == $this->prefix) {
				@unlink("cache/".$file);
			}
		}
		closedir($dp);
		
		$this->collectStyles($path);

		try {
			file_put_contents($path, $this->lessifyCSS(file_get_contents($path)));
		} catch(Exception $e) {
			global $log;
			$log->error("Error in LESS CSS: ".$e->getMessage());
			echo "Error in LESS CSS: ".$e->getMessage();				
		}			
		
		$fp = fopen($path, "r");
		$pp = fopen($path.".new", "w+");
		$gp = gzopen(str_replace(".css", ".cssgz", $path), "w9");
		$hp = fopen(str_replace(".css", ".header.css", $path), "w+");
		$zp = gzopen(str_replace(".css", ".header.cssgz", $path), "w9");
		while (!feof($fp)) {
			$css = fgets($fp, 4096);
			
			if ($bol_minify) {
            	$css = $this->minifyCSS($css);
    	    }
			
			if ($embed) {
				$data = $this->embedResources($css, $path);
				$css = $data["css"];
				$headerArea = $data["header"];
			} else {
				$headerArea = "";
			}
           			
			fwrite($pp, $css);
			fwrite($hp, $headerArea);
			gzwrite($gp, $css);
			gzwrite($zp, $headerArea);
			
		}
		fclose($fp);
		fclose($pp);
		fclose($hp);
		gzclose($gp);
		gzclose($zp);
		if (@copy($path.".new", $path)) {
			@unlink($path.".new");
		}
		
		return $path;
	}
}

?>