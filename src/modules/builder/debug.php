<pre>
<?php

if (strlen($_GET["cmd"]) > 0) {
	file_put_contents("../../cache/debugger.do", $_GET["cmd"]);
}

$json = json_decode(file_get_contents("../../cache/debugger.state"), true);
$do = file_get_contents("../../cache/debugger.do");
if (!$json || strpos($json["file"], "/tools.php") !== false || ($do == "run" || $do == "")) {
	?>
	<script type="text/javascript">
	//<![CDATA[
		setTimeout(function() {
			location.reload();
		}, 2000);
	//]]>
	</script>
	<?php 
	if ($do == "run") {
		die("Re-open debug view to debug again.");
	} else {
		die("Call the event you want to debug.");
	}
}

$lines = file($json["file"]);
$start = 0;
$end = 0;
foreach ($lines as $i=>$line) {
	if ($start > 0 && strpos($line, "function ") !== false) {
		$end = $i - 1;
		break;
	}
	if (strpos($line, "function ".$json["function"]."(") !== false) {
		$start = $i;
	}
}
echo "<div style='float: right; width: 49%;'><b>Variables:</b><br/><table border='1' cellspacing='0' style='border-color: #ccc;overflow: auto;' cellpadding='5'>";
if (is_array($json["variables"])) {
	foreach ($json["variables"] as $key => $val) {
		echo "<tr><td valign='top'>".$key."</td><td valign='top'><pre>";
		var_dump($val);
		echo "</pre></td></tr>";	
	}
}
echo "</table></div>";
echo "<div style='float:left;width: 49%;height:99%;'><b>".$json["class"].": </b><br/><div style='border: 1px solid #ccc;height:100%;overflow: auto;white-space:nowrap;'>\n";
for ($i = $start; $i < $end; $i++) {
	$lines[$i] = str_replace(Array("Debugger::breakpoint();", "Debugger::wait(get_defined_vars());", "Debugger::wait();"), "", $lines[$i]);
	if ($i == $json["line"]-1) {
		echo "<span id='selected' style='color:red;background-color: #ffc;'>".sprintf("%03s", $i - $start).": ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."</span>";
	} else {
		echo sprintf("%03s", $i - $start).": ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."";
	}
}
echo "</div></div>\n";

?>
</pre>
<script type="text/javascript">
//<![CDATA[
	setTimeout(function() {
		var sel = document.getElementById("selected");
		sel.parentNode.scrollTop = sel.offsetTop - 60;
	}, 10);
	opener && opener.focus();
	self && self.focus();
//]]>
</script>