<?php
/** Class HookCore
 *
 * Hooks can be used to enhance functionality already implemented without the need to change it 
 * For example: a handler can tell he wants to be notified by a hook sink provider. So as soon as 
 * the hook sink provider is executed, it will then be called.
 *
 * The definition of a hook sink, where other functionality can hook into, happens through this 
 * class. For this to work, you need to define a unique hook name so that other functionality
 * won't accidentally be affected. The hooks that use the hook sink defined, will use that hook name
 * to bind to the sink. 
 * 
 * You can define a hook at nearly every position in your code: be it within a template, within 
 * handler code or even from within libraries. If you're providing a hook sink from within a template
 * you can use the `[Tags]hook` tag for this. In a handler you would provide a hook sink using the 
 * `[HookCore]notify` method.
 **/
class HookCore {
	static $hookList = Array();

	/**
         * notify($hookName[, $context]) -> Array
         * - $hookName (String) - the hook sink's name to provide
         * - $context (Array) - the context that should be present within the notified hooks.
         *
         * Notifies any hooks that are interested in the given hook sink and optionally passes
         * the given context on to them. The result will be an array of all the results the 
         * different attached hooks produced.
         *
         * *Example:*
         * {{{
         * $arr_results = HookCore::notify('login-success', $arr_user);
         * foreach ($arr_results as $result) {
         *     if ($result == "forbidden") {
         *        jumpTo('static/target-forbidden.html', true);
         *     }
         * }
         * }}}
         **/	
	static function notify($hookName, $context = Array()) {
		$arr_results = Array();
		if (is_array(HookCore::$hookList[$hookName])) {
			foreach (HookCore::$hookList[$hookName] as $hook) {
				array_push($arr_results, invoke($hook, $context));
			}
		}
			
		return $arr_results;
	}
	
	static function hook($hookName, $event) {
		if (!is_array(HookCore::$hookList[$hookName])) {
			HookCore::$hookList[$hookName] = Array();
		}
		array_push(HookCore::$hookList[$hookName], $event);
	}
	
	static function init() {
		// fetch all hooks and put them into the hookList
		$data = new Database("tbl_prailsbase_");
		$hooks = $data->SqlQuery("SELECT * FROM tbl_prailsbase_module AS a, tbl_prailsbase_handler AS b WHERE hook<>'' AND b.fk_module_id=a.module_id");
		if (is_array($hooks)) foreach ($hooks as $hook) {
			HookCore::hook($hook["hook"], $hook["name"].":".$hook["event"]);
		}
	}
} 
?>
