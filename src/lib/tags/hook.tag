<@ $arr_result = HookCore::notify("<?=$tag['attributes']['name']?>", $arr_param); @>
<@ if (is_array($arr_result)) { @>
	<@=implode("\n", $arr_result)@>
<@ } @>