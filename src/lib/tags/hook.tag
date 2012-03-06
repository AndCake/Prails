<?
/** Section Tags
 * <c:hook name="<name>"/>
 * - `name` (String) - the hook sink name to be notified
 *
 * Notifies all hooks that have attached to the given hook sink. 
 * They will automatically inherit the current context and can change it accordingly.
 **/
?><@ $arr_result = HookCore::notify("<?=$tag['attributes']['name']?>", $arr_param); @>
<@ if (is_array($arr_result)) { @>
	<@=implode("\n", $arr_result)@>
<@ } @>
