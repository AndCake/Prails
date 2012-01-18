<? 
/**
 * INPUT GENERATOR
 * 
 * Parameters:
 * type     - type of input; can be: text, password, file, checkbox, radio, select, date
 * name	    - name of the input to be used for submission
 * value    - single value (for text, password, date), selected value (for select, radio), selected values (for checkbox and select box multiple; values split by ";")
 * values   - all values (for radio, checkbox, select) : Array(value : label)
 * class    - classes to add
 * label    - input field's label
 * rel	    - custom expression for required inputs (for text, password and date)
 * overlabel - overlabel to use (for text, password, date)
 * error	- validation error message
 * multiple - size to show (for select), also enables selecting multiple entries at once; for text input's it will enable entering multiple lines of text
 */
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
		<textarea<?=strlen($tag['attributes']['error'])>0 ? ' error="'.$tag['attributes']['error'].'"' : ''?> rows="<?=$tag['attributes']['multiple']?>" name="<?=$tag['attributes']['name']?>" class="<?=$tag['attributes']['class']?><?=(strlen($tag['attributes']['overlabel'])>0 ? ' overlabel' : '')?>" label="<?=$tag['attributes']['overlabel']?>" rel="<?=$tag['attributes']['rel']?>"><?=$tag['attributes']['value']?></textarea>
	<? } else { ?>
	        <input<?=strlen($tag['attributes']['error'])>0 ? ' error="'.$tag['attributes']['error'].'"' : ''?> type="<?=$type?>" name="<?=$tag['attributes']['name']?>" value="<?=$tag['attributes']['value']?>" class="<?=$tag['attributes']['class']?><?=(strlen($tag['attributes']['overlabel']) > 0 ? ' overlabel' : '')?>" label="<?=$tag['attributes']['overlabel']?>" rel="<?=$tag['attributes']['rel']?>" />
	<? } ?>
    <? } else { ?>
        <% $var = $arr_param["<?=$this->makeVar($tag["attributes"]["values"])?>"]; $val = $arr_param["<?=$this->makeVar(preg_replace('/^#/', '', $tag['attributes']['value']))?>"]; %>
        <% $i = 0; if (is_array($var)) foreach ($var as $value => $label) { %>
        	<div class='radio'>
	            <input type="<?=$type?>" name="<?=($type == 'radio' ? $tag['attributes']['name'] : $tag['attributes']['name'].'[]')?>" <%=(in_array($value, (is_array($val) ? $val : explode(";", $val))) ? 'checked="checked"':'')%> value="<%=$value%>" id="<?=$tag['attributes']['name']?>_<%=$i%>" />
	            <label for="<?=$tag['attributes']['name']?>_<%=$i%>"><%=$label%></label>
            </div>
        <% $i++; } %>
    <? } ?>
<? } else { ?>
    <select name="<?=$tag['attributes']['name']?>" <?=strlen($tag['attributes']['multiple'])>0 ? 'size="'.$tag['attributes']['multiple'].'" multiple="multiple"':'size="1"'?>>
        <% $var = $arr_param["<?=$this->makeVar($tag["attributes"]["values"])?>"]; $val = $arr_param["<?=$this->makeVar(preg_replace('/^#/', '', $tag['attributes']['value']))?>"]; %>
        <% $i = 0; if (is_array($var)) foreach ($var as $value => $label) { %>
            <option value="<%=$value%>"<%=(in_array($value, (is_array($val) ? $val : explode(';', $val))) ? ' selected="selected"':'')%>><%=$label%>
        <% } %>
    </select>
<? } ?>
</div>
</div>
