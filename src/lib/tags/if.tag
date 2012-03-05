<@ if (<?=$this->makeAllVars(preg_replace('/#(\\w+\\.\\w+)/m', '@\\1', $tag["attributes"]["cond"]))?>) { @>
	<?=$tag["body"]?>
<@ } @>
