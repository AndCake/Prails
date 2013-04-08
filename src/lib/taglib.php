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

/** Section Tags
 *
 * Control Tags are HTML/XML tags that allow for a standardized, secure and fast generation of dynamic pages. 
 * They are part of the template engine at Prails' core and are translated into PHP code, which makes them 
 * easy to use, create and understand. 
 *
 * A series of control tags are pre-defined in Prails and can be used in any template and output code.
 **/
class TagLib {

	private $tagLibDir = "lib/tags/";
	private $allowedPrefixes = Array("c");

	private $tagMatch = Array();
	private $unclosedPos = Array();
	private $depth = 0;
	private $html = "";
	private $template = "";
	
	public function TagLib($template = "") {
		$this->template = $template;
		$this->depth = 0;
		$this->tagMatch = Array();
		$this->unclosedPos = Array();
	}
	
	public function compile($html, $allowedDepth = 0) {
		$this->depth = 0;
		$this->tagMatch = Array();
		$this->unclosedPos = Array();
		$this->html = $html;
		$this->match($html);
		if ($this->depth > 0) {
			throw new Exception("ERROR compiling template code: unclosed tag \"".array_pop(array_keys($this->unclosedPos))."\"\n");
		}
		if ($this->debugMode) {
			echo $html."\n";
			var_dump($this->tagMatch);
		}
		foreach ($this->tagMatch as $tag=>$arr_tag) {
			foreach ($arr_tag as $entry) {
				if ($this->debugMode) echo "Handling tag '".$tag."'\n";
				if ($entry["depth"] > $allowedDepth) continue;
				$rc = new TagLib();
				if ($this->debugMode) echo "Didn't skip\n";
				if (strlen($entry["body"]) > 0) {
					if ($this->debugMode) echo "Compiling body...\n";
					$entry["body"] = $rc->compile($entry["body"], $allowedDepth);
				}
				$content = trim($this->loadTagLib($tag, $entry));
				if ($this->debugMode) echo "Loaded tag '$tag'. Result: '".$content."'\n";
				$html = str_replace($entry["match"], $content, $html);
			}
		}
		
		$html = $this->makeAllVars($html);
		$c = SNOW_MODE === true ? "%" : "?";
		$html = str_replace(Array('<%', '%>', "<@", "@>"), Array("<".$c, $c.">", "<?", "?>"), $html);
		
		$html = $this->integrate($html);
			
		return $html;
	}
	
	private function loadTagLib($name, $tag) {
		$path = $this->tagLibDir.$name.".tag";
		if (!file_exists($path)) {
			$path = $this->tagLibDir."custom/".$name.".tag";
			if (!file_exists($path)) {
				$path = $this->tagLibDir."custom/".$name.((int)$_SESSION["builder"]["user_id"]).".tag";
			}
		} 
		ob_start();
		require($path);
		$content = ob_get_contents();
		ob_end_clean();
		$c = SNOW_MODE === true ? "%" : "?";
		$content = str_replace(Array("@>", "<@", "%>", "<%"), Array("?>", "<?", $c.">", "<".$c), $content);
		return $content;
	}
	
	private function makeAllVars($buffer) {
        preg_match_all("/(#|#!)([a-zA-Z_0-9]+[.][.A-Za-z0-9_]*[a-zA-Z0-9]*)(\[([a-zA-Z0-9]+)\](\[([^\]]+)\])?)?/", $buffer, $arr_matches);
        $c = (SNOW_MODE === true ? "%" : "?");
        foreach ($arr_matches[2] as $key => $str_match) {
        	$toClose = false;
        	preg_match_all('/<!--\[noeval\]-->.*<!--\[\/noeval\]-->/sU', $buffer, $arr_test);
            $found = false;
            foreach ($arr_test[0] as $test) {
                $found = $found || (strpos($test, $arr_matches[0][$key]) !== false);
            }
            if ($found) continue;
            $parts = explode(".", $str_match);
            if (strlen($arr_matches[4][$key]) > 0) {
            	if (file_exists("lib/tags/".$arr_matches[4][$key].".var")) {
            		ob_start();
            		$var["name"] = $arr_matches[4][$key];
            		$var["modifier"] = $arr_matches[6][$key];
            		require("lib/tags/".$arr_matches[4][$key].".var");
            		$str_param = ob_get_clean();
            		$toClose = $var["close"]; 
            	} else
                {
                	if (SNOW_MODE === true) {
                		$str_param = $arr_matches[4][$key]."val(param";
                		$toClose = true;
                	} else {
                    	$str_param = "(" . $arr_matches[4][$key] . ")\$arr_param";
                    }
                }
            } else {
            	if (SNOW_MODE === true) {
            		$str_param = "param";
            	} else {
                	$str_param = "\$arr_param";
                }
            }
            foreach ($parts as $part) {
            	if (is_numeric($part)) {
            		$str_param .= "[" . $part . "]";	
            	} else {
                	$str_param .= "[\"" . $part . "\"]";
            	}
            }
            if ($toClose) $str_param .= ")";
			if ($arr_matches[1][$key] == "#!") {
				$buffer = str_replace($arr_matches[0][$key], $str_param, $buffer);
			} else {
	            $buffer = str_replace($arr_matches[0][$key], "<".$c."=".$str_param.$c.">", $buffer);
			}
        }
        return $buffer;		
	}
	
	private function makeVar($var) {
		return str_replace(".", "\"][\"", $var);
	}

	private function match($html) {
		$pattern = '@<(/)?(\w+):(\w+)(\s*|(\s+[a-zA-Z0-9\-_]+=(?:"[^"]*"|\'[^\']*\')\s*)+)(/)?>@usix';
		preg_match_all($pattern, $html = trim($html), $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
		foreach ($m as $set) {
			if (in_array($set[2][0], $this->allowedPrefixes)) {
				if ($set[1][1] >= 0) {
					$this->endTag($set[3][0], $set[0][1], strlen(trim($set[0][0])));
				} else {
					$this->startTag($set[3][0], $this->getAttribs($set[4][0]), $set[0][1], strlen(trim($set[0][0])));
					if ($set[6][1] > 0) {
						$this->endTag($set[3][0], $set[0][1], strlen(trim($set[0][0])));
					}
				}
			}
		}
	}
	
	private function startTag($tagName, $attributes, $startPos, $len) {
		$cur = count($this->tagMatch[$tagName]);
		if (!is_array($this->unclosedPos[$tagName])) {
			$this->unclosedPos[$tagName] = Array();
		}
		$this->tagMatch[$tagName][$cur]["attributes"] = $attributes;
		$this->tagMatch[$tagName][$cur]["startPos"] = $startPos;
		$this->tagMatch[$tagName][$cur]["startLen"] = $len;
		$this->tagMatch[$tagName][$cur]["depth"] = $this->depth;
		array_push($this->unclosedPos[$tagName], $cur);
		$this->depth++;
	}
	
	private function endTag($tagName, $endPos, $len) {
		$pos = array_pop($this->unclosedPos[$tagName]);
		$this->tagMatch[$tagName][$pos]["endPos"] = $endPos;
		$this->tagMatch[$tagName][$pos]["endLen"] = $len;
		$this->tagMatch[$tagName][$pos]["body"] = $this->getBody($this->tagMatch[$tagName][$pos]);
		$this->tagMatch[$tagName][$pos]["match"] = $this->getMatch($this->tagMatch[$tagName][$pos]);
		$this->depth--;
	}
	
	private function getAttribs($content) {
		$arr_attributes = Array();
		$pattern = '@\s*([a-zA-Z0-9\-_]+)=(?:(?:"([^"]*)")|(?:\'([^\']*)\'))\s*@usix';
		preg_match_all($pattern, $content=trim($content), $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
		foreach ($m as $set) {
			$arr_attributes[$set[1][0]] = empty($set[2][0]) ? $set[3][0] : $set[2][0];
		}

		return $arr_attributes;
	}
	
	private function getBody($tag) {
		if ($tag["startPos"] < $tag["endPos"]) {
			return trim(substr($this->html, $tag["startPos"] + $tag["startLen"], $tag["endPos"] - ($tag["startPos"] + $tag["startLen"])));
		} else {
			return "";
		}
	}
	
	private function getMatch($tag) {
		$match = trim(substr($this->html, $tag["startPos"], ($tag["endPos"] + $tag["endLen"]) - $tag["startPos"]));
		return $match;
	}
	
	private function integrate($content) {
		$content = preg_replace('@(<\?[^=]([^?]|\?[^>])+)\?>\s*<\?([^=])@usix', "\\1\\3", $content);

		if (SNOW_MODE === true) {
			preg_match_all('@<%(([^%]|%[^>])*)%>@', $content, $matches);
			if (is_array($matches[1]) && count($matches[1]) > 0) {
				foreach ($matches[1] as $key => $value) {
					$sc = new SnowCompiler(ltrim($value, "=") . "\n");
					$result = $sc->compile();
					$content = str_replace($matches[0][$key], "<?".($value[0] == "=" ? "=" . rtrim($result, ";\n\r") : $result) . "?>", $content);
				}
			}
		}

		return $content;
	}
}
?>
