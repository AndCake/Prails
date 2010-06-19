<? if ($tag["attributes"]["cond"]) { ?>
	<@ } else if (<?=$tag["attributes"]["cond"]?>) { @>
<? } else { ?>
	<@ } else { @>
<? } ?>