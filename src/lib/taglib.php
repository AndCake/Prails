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

/**
 * This class implements the framework's tag lib base engine
 * 
 * @author RoQ
 */
class TagLib {

	private $tagLibDir = "lib/tags/";
	private $allowedPrefixes = Array("c");

	private $tagMatch = Array();
	private $unclosedPos = Array();
	private $depth = 0;
	private $html = "";
	
	public function compile($html, $allowedDepth = 0) {
		$this->html = $html;
		$this->match($html);

		foreach ($this->tagMatch as $tag=>$arr_tag) {
			foreach ($arr_tag as $entry) {
				if ($entry["depth"] > $allowedDepth) continue;
				$rc = new TagLib();
				if (strlen($entry["body"]) > 0) {
					$entry["body"] = $rc->compile($entry["body"], $allowedDepth+1);
				}
				$content = $this->loadTagLib($tag, $entry);
				$html = str_replace($entry["match"], $content, $html);
			}
		}
		
		$html = $this->makeAllVars($html);
		
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
		$content = str_replace("@>", "?".">", str_replace("<@", "<"."?", $content));
		return $content;
	}
	
	private function makeAllVars($buffer) {
        preg_match_all("/(#|@)([a-zA-Z]+[.][.A-Za-z0-9_]*[a-zA-Z0-9]*)(\[([a-zA-Z0-9]+)\])?/", $buffer, $arr_matches);
        foreach ($arr_matches[2] as $key => $str_match) {
            preg_match_all('/<!--\[noeval\]-->.*<!--\[\/noeval\]-->/sU', $buffer, $arr_test);
            $found = false;
            foreach ($arr_test[0] as $test) {
                $found = $found || (strpos($test, $arr_matches[0][$key]) !== false);
            }
            if ($found) continue;
            $parts = explode(".", $str_match);
            if (strlen($arr_matches[4][$key]) > 0) {
                if ($arr_matches[4][$key] == "price")
                {
                    $str_param = "sprintf(\"%.2f\", \$arr_param";
                } else
                {
                    $str_param = "(" . $arr_matches[4][$key] . ")\$arr_param";
                }
            } else {
                $str_param = "\$arr_param";
            }
            foreach ($parts as $part) {
                $str_param .= "[\"" . $part . "\"]";
            }
            if ($arr_matches[4][$key] == "price") $str_param .= ")";
			if ($arr_matches[1][$key] == "@") {
				$buffer = str_replace($arr_matches[0][$key], $str_param, $buffer);
			} else {
	            $buffer = str_replace($arr_matches[0][$key], "<"."?=".$str_param."?".">", $buffer);
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
		$pattern = '@\s*([a-zA-Z0-9\-_]+)=(?:"([^"]*)"|\'([^\']*)\')\s*@usix';
		preg_match_all($pattern, $content=trim($content), $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
		foreach ($m as $set) {
			$arr_attributes[$set[1][0]] = $set[2][0];
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
		return trim(substr($this->html, $tag["startPos"], ($tag["endPos"] + $tag["endLen"]) - $tag["startPos"]));
	}
	
	private function integrate($content) {
		return preg_replace('@(<\?[^=]([^?]|\?[^>])+)\?>\s*<\?([^=])@usix', "\\1\\3", $content);
	}
}
?>
