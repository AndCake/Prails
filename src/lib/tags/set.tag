<?
/** Section Tags
 * <c:set name="<var>" value="<value>"[ scope="<scope>"] />
 * - `name` (String) - variable's name
 * - `value` (Expression) - should evaluate to new variable's value
 * - `scope` (String) - the target scope of the variable. The default scope is `local`. 
 * 
 * Defines the value of a variable that can later be accessed. 
 * 
 * *Example:*
 * {{{
 * &lt;c:set name="test" value="123"/&gt;
 * #local.test  ==> will output 123
 *
 * &lt;c:set name="test" value="strlen(#local.test)" scope="local.test"/&gt;
 * #local.test.test  ==> will output 3
 * 
 * &lt;c:set name="test" value="'Hello World!'"/&gt;
 * #local.test  ==> will output Hello, World!
 * }}}
 **/
?><@ $arr_param["<?=$this->makeVar(if_set($tag['attributes']['scope'], 'local'))?>"]["<?=$tag['attributes']['name']?>"] = <? $code = $this->makeAllVars(preg_replace('/#(\\w+\\.\\w+)/m', '#!\\1', $tag["attributes"]["value"])); if (SNOW_MODE === true) { $sc = new SnowCompiler($code."\n"); echo rtrim($sc->compile(), ";\r\n"); } else { echo $code; } ?>; @>
