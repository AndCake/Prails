<? 
/** Section Tags
 * <c:input [type="<type>"] [name="<name>"] [value="<value>"] [values="<values>"] [class="<css classes>"] [label="<label>"] [rel="<rel>"] [overlabel="<overlabel text>"] [error="<validation-error>"] [multiple="<size>"]/>
 * - `type` (String) - type of input; can be: `text`, `password`, `file`, `checkbox`, `radio`, `select`, `date`, `email`, `combo`
 * - `name` (String) - name of the input to be used for submission
 * - `value` (String) - single value (for text, password, date), selected value (for select, radio), selected values (for checkbox and select box multiple; values split by ";")
 * - `values` (Array) - all values (for radio, checkbox, select, combo) : Array(value : label)
 * - `class` (String) - CSS classes to add
 * - `disabled` (String) - if set, the input control will be disabled.
 * - `label` (String) - input field's label which will be placed in front of it
 * - `rel` (String) - custom regular expression validating for required inputs (for text, password and date)
 * - `overlabel` (String) - overlabel to use (for text, password, date). An overlabel is a placeholder text.
 * - `error` (String) - validation error message
 * - `multiple` (Integer) - size to show (for select), also enables selecting multiple entries at once; for text input's it will enable entering multiple lines of text
 *
 * Renders a form field with the specified properties. It is able to render text fields (also with multiple lines), password inputs, file inputs, checkboxes, radio buttons, select boxes and date fields.
 *
 * *Example:*
 * {{{
 * &lt;% $arr_param['countries'] = Array("US" => "United States", "GB" => "United Kingdom", "DE" => "Germany"); %&gt;
 * &lt;% $arr_param['local']['country'] = "GB"; %&gt;
 * &lt;!-- render a select box with a label and three countries, whereas the country "GB" is pre-selected --&gt;
 * &lt;c:input type="select" values="countries" value="#local.country" label="Shipping Country:"/&gt;
 * }}}
 *
 **/
?>
<div class="form-entry <?=preg_replace('/[^a-zA-Z0-9]+/', '', $tag['attributes']['name'])?> <?=if_set($tag['attributes']['addclass'], '')?>">
<? 
    if (strlen($tag["attributes"]["type"]) <= 0) $type = "text"; else $type = $tag["attributes"]["type"];  
    if (strlen($tag["attributes"]["label"]) > 0) { ?>
    
    <div class="label">
        <?=$tag["attributes"]["label"]?><? if (strpos($tag['attributes']['class'], "required") !== false) { ?><span class="required-indicator">*</span><? } ?>
    </div>
<? } ?>
<div class="value">
<? if ($type != "select") { 
    if ($type != "radio" && $type != "checkbox") { ?>
	<? if ($type == "text" && $tag["attributes"]["multiple"] > 0) { ?>
		<textarea<?=strlen($tag['attributes']['error'])>0 ? ' error="'.$tag['attributes']['error'].'"' : ''?> rows="<?=$tag['attributes']['multiple']?>" name="<?=$tag['attributes']['name']?>"<?=isset($tag['attributes']['disabled']) ? ' disabled="disabled"' : ''?> class="<?=$tag['attributes']['class']?><?=(strlen($tag['attributes']['overlabel'])>0 ? ' overlabel' : '')?>" label="<?=$tag['attributes']['overlabel']?>" rel="<?=$tag['attributes']['rel']?>"><?=$tag['attributes']['value']?></textarea>
	<? } else if ($type == 'combo') { ?>
		<% $var = $arr_param["<?=$this->makeVar($tag["attributes"]["values"])?>"]; $val = $arr_param["<?=$this->makeVar(preg_replace('/^#/', '', $tag['attributes']['value']))?>"]; %>
		<input type="text" id="<?=$tag['attributes']['name']?>_id" value="<%=$val%>" <?=strlen($tag['attributes']['error'])>0 ? 'error="'.$tag['attributes']['error'].'"' : ''?><?=isset($tag['attributes']['disabled']) ? ' disabled="disabled"' : ''?> class="<?=$tag['attributes']['class']?><?=(strlen($tag['attributes']['overlabel']) > 0 ? ' overlabel' : '')?>" label="<?=$tag['attributes']['overlabel']?>" rel="<?=$tag['attributes']['rel']?>" />
		<input type="hidden" id="<?=$tag['attributes']['name']?>_h" name="<?=$tag['attributes']['name']?>" value="<%=$val%>"/>
		<script type="text/javascript">
			addLoadEvent(function() {
				_($("<?=$tag['attributes']['name']?>_id")).autocomplete({
					source: <%=(is_array($var) ? json_encode(array_values(array_filter($var))) : (!empty($var) ? '"'.$var.'"' : '"<?=$tag['attributes']['values']?>"'))%>,
					minLength: <?=if_set($tag['attributes']['minlength'], 1)?>,
					change: function(event, ui) {
						<% if (is_array($var)) { %>
                                                        var values = <%=json_encode(array_flip($var))%>;
                                                        if (ui.item) {
                                                                $("<?=$tag['attributes']['name']?>_h").value = values[ui.item.value || this.value];
                                                        } else {
                                                                $("<?=$tag['attributes']['name']?>_h").value = values[this.value] || "";
                                                        }
                                                <% } else { %>
                                                        $("<?=$tag['attributes']['name']?>_h").value = (ui.item && ui.item.value) || this.value || "";
                                                <% } %>
					}
				});
			});
		</script>
	<? } else { ?>
	        <input<?=strlen($tag['attributes']['error'])>0 ? ' error="'.$tag['attributes']['error'].'"' : ''?> type="<?=$type?>" name="<?=$tag['attributes']['name']?>"<?=isset($tag['attributes']['disabled']) ? ' disabled="disabled"' : ''?> value="<?=$tag['attributes']['value']?>" class="<?=$tag['attributes']['class']?><?=(strlen($tag['attributes']['overlabel']) > 0 ? ' overlabel' : '')?>" label="<?=$tag['attributes']['overlabel']?>" rel="<?=$tag['attributes']['rel']?>" />
	<? } ?>
    <? } else { ?>
        <% $var = $arr_param["<?=$this->makeVar($tag["attributes"]["values"])?>"]; $val = $arr_param["<?=$this->makeVar(preg_replace('/^#/', '', $tag['attributes']['value']))?>"]; %>
        <% $i = 0; if (is_array($var)) foreach ($var as $value => $label) { %>
        	<div class='radio'>
	            <input type="<?=$type?>" name="<?=($type == 'radio' ? $tag['attributes']['name'] : $tag['attributes']['name'].'[]')?>"<?=isset($tag['attributes']['disabled']) ? ' disabled="disabled"' : ''?> <%=(in_array($value, (is_array($val) ? $val : explode(";", $val))) ? 'checked="checked"':'')%> value="<%=$value%>" id="<?=$tag['attributes']['name']?>_<%=$i%>" />
	            <label for="<?=$tag['attributes']['name']?>_<%=$i%>"><%=$label%></label>
            </div>
        <% $i++; } %>
    <? } ?>
<? } else { ?>
    <select name="<?=$tag['attributes']['name']?>" <?=strlen($tag['attributes']['multiple'])>0 ? 'size="'.$tag['attributes']['multiple'].'" multiple="multiple"':'size="1"'?><?=isset($tag['attributes']['disabled']) ? ' disabled="disabled"' : ''?>>
        <% $var = $arr_param["<?=$this->makeVar($tag["attributes"]["values"])?>"]; $val = $arr_param["<?=$this->makeVar(preg_replace('/^#/', '', $tag['attributes']['value']))?>"]; %>
        <% $i = 0; if (is_array($var)) foreach ($var as $value => $label) { %>
            <option value="<%=$value%>"<%=(in_array($value, (is_array($val) ? $val : explode(';', $val))) ? ' selected="selected"':'')%>><%=$label%>
        <% } %>
    </select>
<? } ?>
</div>
</div>
