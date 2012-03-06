<?
/** Section Tags
 * <c:else/>
 *
 * Used together with `[Tags]if` or `[Tags]foreach` to render something in an alternative branch of 
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
 **/
?><? if ($tag["attributes"]["cond"]) { ?>
	<@ } else if (<?=$tag["attributes"]["cond"]?>) { @>
<? } else { ?>
	<@ } else { @>
<? } ?>
