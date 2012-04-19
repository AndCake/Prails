<?
/** Section Tags
 * <c:else [cond="<php-condition>"]/>
 *
 * Used together with `[Tags]if` or `[Tags]foreach` to render something in an alternative branch of 
 * execution. Needs to be written within the respective if/loop tag's body. In case the condition 
 * attribute is given, it will check if that is `true` and only if so, will render what comes after 
 * the `[Tags]else` tag.
 * 
 * *Example:*
 * {{{
 * &lt;c:if cond="#local.value &gt; 123"&gt;
 *    It's larger than 123!
 * &lt;c:else cond="#local.value &lt; 120"&gt;
 *    It's smaller than 120!
 * &lt;c:else/&gt;
 *    It's between 120 and 123!
 * &lt;/c:if&gt;
 * }}}
 * Depending on the value in variable `value`, it will print out different messages.
 **/
?><? if ($tag["attributes"]["cond"]) { ?>
	<@ } else if (<?=$this->makeAllVars(preg_replace('/#(\\w+\\.\\w+)/m', '#!\\1', $tag["attributes"]["cond"]))?>) { @>
<? } else { ?>
	<@ } else { @>
<? } ?>
