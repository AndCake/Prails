<?php
/**
 * Hooks can be used to enhance functionality already implemented without the need to change it
 * For example: a handler can tell he wants to be used by a hook provider and will then be called
 * 
 * in a template a provider would write: 
 * <c:hook name="my-new-hook"/>
 * 
 * in a handler a provider would write:
 * HookCore::notify("my-new-hook", $arr_param);
 * 
 * a consumer would be a handler which produces some output - so it needs to define it wants to attach to a hook...
 * 
 * @author RoQ
 */
class HookCore {
	static $hookList = Array();
	
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
		foreach ($hooks as $hook) {
			HookCore::hook($hook["hook"], $hook["name"].":".$hook["event"]);
		}
	}
} 
?>