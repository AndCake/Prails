<?
/** Section Tags
 * <c:foreach var="(inline-var)" name="(loop-var-name)"[ key="(key-name)"]></c:foreach>
 *
 * A foreach tag, which repeats rendering it's body content for every item in the `inline-var` array. 
 * The single entries can be accessed by using the `loop-var-name` variable name. Optionally an 
 * additional key can be provided to use the current position. 
 * _Note:_ if the array given is empty (or not even an array), you can use the `&lt;c:else/&gt;` tag to render something in 
 * that case. 
 *
 * *Example:*
 * {{{
 * &lt;ol class="user-list"&gt;
 *    &lt;c:foreach var="users" name="user"&gt;
 *       &lt;li&gt;#user.name&lt;/li&gt;
 *    &lt;c:else/&gt;
 *       &lt;li&gt;No user there&lt;/li&gt;
 *    &lt;/c:foreach&gt;
 * &lt;/ol&gt;
 * }}}
 * 
 * *Example 2:*
 * {{{
 * &lt;select name="entry"&gt;
 *   &lt;c:foreach var="itemMap" name="myvalue" key="mykey"&gt;
 *     &lt;option value="#local.mykey"&gt;#local.myvalue&lt;/option&gt;
 *     &lt;!-- alternatively you could also use &lt;?=$myvalue?&gt; and &lt;?=$mykey?&gt; --&gt;
 *   &lt;/c:foreach>
 * &lt;/select>
 * }}}
 **/
?><? $var = $this->makeVar($tag["attributes"]["var"]); ?>
<@ if (is_array($arr_param["<?=$var?>"]) && count($arr_param["<?=$var?>"]) > 0) foreach ($arr_param["<?=$var?>"] as <?=($tag["attributes"]["key"] ? "$".$tag["attributes"]["key"]." => " : "")?>$<?=$tag["attributes"]["name"]?>) { @>
<? if ($tag["attributes"]["key"]) { ?>
	<@ $arr_param["local"]["<?=$tag["attributes"]["key"]?>"] = $<?=$tag["attributes"]["key"]?>; @>
<? } ?>
	<@ $arr_param["<?=$tag["attributes"]["name"]?>"] = $arr_param["local"]["<?=$tag['attributes']['name']?>"] = $<?=$tag["attributes"]["name"]?>; @>
	<?=$tag["body"]?>
<@ } @>
