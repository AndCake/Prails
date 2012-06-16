<?
/** Section Tags
 * <c:print value="<inline-var>" />
 * - `value` (String) - the variable name whose value to print
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
 **/
?><? $var = $this->makeVar($tag["attributes"]["value"]); ?>
<@=htmlspecialchars($arr_param["<?=$var?>"])@>
