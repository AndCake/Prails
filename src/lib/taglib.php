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
 * 
 * <c:if cond="<php-condition>">...</c:if>
 * 
 * An if condition tag, that will take a complete PHP condition and if it returns true, renders the tag's 
 * body content. 
 * _Note:_ you can use the `&lt;c:else/&gt;` tag to render something if the PHP condition returns `false`. 
 * 
 * *Example:*
 * {{{
 * &lt;c:if cond="$arr_param['test'] == '123'"&gt;
 *    &lt;span class="really-a-test"&gt;This is nice!&lt;/span&gt;
 * &lt;/c:if&gt;
 * // the above does exactly the same as the following:
 * &lt;c:if cond="#local.test == '123'"&gt;
 *    &lt;span class="really-a-test"&gt;This is nice!&lt;/span&gt;
 * &lt;/c:if&gt;
 * }}}
 *
 *
 * <c:foreach var="(inline-var)" name="(loop-var-name)"[ key="(key-name)"]></c:foreach>
 *
 * A foreach tag, which repeats rendering it's body content for every item in the `inline-var` array. 
 * The single entries can be accessed by using the `loop-var-name` variable name. Optionally an 
 * additional key can be provided to use the current position. 
 * _Note:_ if the array given is empty, you can use the `&lt;c:else/&gt;` tag to render something in 
 * that case. 
 *
 * *Example:*
 * {{{
 * &lt;ol class="user-list"&gt;
 *    &lt;c:foreach var="users" name="user"&gt;
 *       &lt;li&gt;#user.name&lt;/li&gt;
 *    &lt;/c:foreach&gt;
 * &lt;/ol&gt;
 * }}}
 *
 *
 * <c:else/>
 *
 * Used together with `&lt;c:if&gt;` or `&lt;c:foreach&gt;` to render something in an alternative branch of 
 * execution. Needs to be written within the respective if/loop tag's body. 
 * 
 * *Example:*
 * {{{
 * &lt;c:if cond="true == false"&gt;
 *    Should never happen!
 * &lt;c:else/&gt;
 *    Wonderful!
 * &lt;/c:if&gt;
 * }}}
 *
 * 
 * <c:include (event="<event-name>" | file="<event-name>" | template="<template-name>")/>
 *
 * Includes a whole event handler's result or simply a template of another event handler. In case that 
 * just a template should be included, the path to that template is `&lt;module-name&gt;/&lt;event-handler-name&gt;`. 
 * It then has some similar characteristics to a decorator, except it does not embed something, but is 
 * embedded into something. 
 * 
 * *Example:*
 * {{{
 * &lt;!-- calls the "user:list" event handler and renders it's result --&gt;
 * &lt;c:include event="user:list"/&gt;
 * &lt;!-- includes the default template from module "user" and event handler "detail", it is evaluated immediately --&gt;
 * &lt;c:include file="user/detail"/&gt;
 * &lt;!-- includes the template "mail" from the current event handler --&gt;
 * &lt;c:include template="mail"/&gt;
 * }}}
 *
 *
 * <c:print value="<inline-var>"/>
 *
 * Safely prints a variable's value. Usually text entered by the user, that you want to display might be
 * used for code injection or even cross-site scripting attacks. To prevent this, the print tag encodes 
 * all dangerous characters as HTML entites. 
 *
 * *Example:*
 * {{{
 * &lt;p class="content"&gt;
 *    &lt;c:print value="user.description"/&gt;
 * &lt;/p&gt;
 * }}}
 *
 *
 * <c:input [type="<type>"] [name="<name>"] [value="<value>"] [values="<values>"] [class="<css classes>"] [label="<label>"] [rel="<rel>"] [overlabel="<overlabel text>"] [error="<validation-error>"] [multiple="<size>"]/>
 * - `type` (String) - type of input; can be: `text`, `password`, `file`, `checkbox`, `radio`, `select`, `date`, `email`
 * - `name` (String) - name of the input to be used for submission
 * - `value` (String) - single value (for text, password, date), selected value (for select, radio), selected values (for checkbox and select box multiple; values split by ";")
 * - `values` (Array) - all values (for radio, checkbox, select) : Array(value : label)
 * - `class` (String) - CSS classes to add
 * - `label` (String) - input field's label which will be placed in front of it
 * - `rel` (String) - custom regular expression validating for required inputs (for text, password and date)
 * - `overlabel` (String) - overlabel to use (for text, password, date). An overlabel is a placeholder text.
 * - `error` (String) - validation error message
 * - `multiple` (Integer) - size to show (for select), also enables selecting multiple entries at once; for text input's it will enable entering multiple lines of text
 *
 * Renders a form field with the specified properties. It is able to render text fields (also with multiple lines), password inputs, file inputs, checkboxes, radio buttons, select boxes and date fields.
 *
 * *Example:*
 * {{{
 * &lt;% $arr_param['countries'] = Array("US" => "United States", "GB" => "United Kingdom", "DE" => "Germany"); %&gt;
 * &lt;% $arr_param['local']['country'] = "GB"; %&gt;
 * &lt;!-- render a select box with a label and three countries, whereas the country "GB" is pre-selected --&gt;
 * &lt;c:input type="select" values="countries" value="#local.country" label="Shipping Country:"/&gt;
 * }}}
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
	}
	
	public function compile($html, $allowedDepth = 0) {
		$this->html = $html;
		$this->match($html);

		foreach ($this->tagMatch as $tag=>$arr_tag) {
			foreach ($arr_tag as $entry) {
				if ($entry["depth"] > $allowedDepth) continue;
				$rc = new TagLib();
				if (strlen($entry["body"]) > 0) {
					$entry["body"] = $rc->compile($entry["body"], $allowedDepth);
				}
				$content = $this->loadTagLib($tag, $entry);
				$html = str_replace($entry["match"], $content, $html);
			}
		}
		
		$html = $this->makeAllVars($html);
		$html = str_replace(Array('<%', '%>', "<@", "@>"), Array("<?", "?>", "<?", "?>"), $html);
		
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
		$content = str_replace(Array("@>", "<@", "%>", "<%"), Array("?>", "<?", "?>", "<?"), $content);
		return $content;
	}
	
	private function makeAllVars($buffer) {
        preg_match_all("/(#|@)([a-zA-Z_0-9]+[.][.A-Za-z0-9_]*[a-zA-Z0-9]*)(\[([a-zA-Z0-9]+)\](\[([^\]]+)\])?)?/", $buffer, $arr_matches);
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
                    $str_param = "(" . $arr_matches[4][$key] . ")\$arr_param";
                }
            } else {
                $str_param = "\$arr_param";
            }
            foreach ($parts as $part) {
            	if (is_numeric($part)) {
            		$str_param .= "[" . $part . "]";	
            	} else {
                	$str_param .= "[\"" . $part . "\"]";
            	}
            }
            if ($toClose) $str_param .= ")";
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
