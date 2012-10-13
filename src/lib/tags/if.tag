<?
/** Section Tags
 * <c:if cond="<php-condition>">...</c:if>
 * 
 * An if condition tag, that will take a complete PHP condition and if it returns true, renders the tag's 
 * body content. 
 * _Note:_ you can use the `[Tags]else` tag to render something if the PHP condition returns `false`. 
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
 **/
?><@ if (<?=$this->makeAllVars(preg_replace('/#(\\w+\\.\\w+)/m', '#!\\1', $tag["attributes"]["cond"]))?>) { @><?=$tag["body"]?><@ } @>
