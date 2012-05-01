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
		die("Click <a href='javascript:(function(){parent.reloadDebugger();}());'>here</a> to restart debugging.");
	} else {
		die("Call the event you want to debug.");
	}
}
$lines = file($json["file"]);
$start = 0;
$end = count($lines) - 1;
if (preg_match('/([a-zA-Z_]+)[0-9]+(Handler|Data)/', $json['class'], $match)) {
	$json['class'] = $match[2]." ".$match[1].":".$json['function'];
}
$lookFor = false;
foreach ($lines as $i=>$line) {
	if ($lookFor && preg_match('/\/\*\[END POST-'.$lookFor[0].'\]\*\//i', $line)) {
		if ($json['line'] - 1 < $i && $json['line'] - 1 >= $lookFor[1]) {
			$start = $lookFor[1] + 2;
			$end = $i - 2;
			$json['class'] .= ", Endpoint ".$lookFor[0];
			break;
		} else {
			$start = $i + 1;
		}
		$lookFor = false;
	}
	if ($start > 0 && (strpos($line, "function ") !== false || strpos($line, "/*</EVENT-HANDLERS>*/") !== false || strpos($line, "/*</DB-METHODS>*/") !== false)) {
		$end = $i - 2;
		break;
	}
	if (strpos($line, "function ".$json["function"]."(") !== false) {
		$start = $i + ($match[2] == "Handler" ? 3 : 1);
	}
	if ($i >= $start && $i <= $end) {
		if (preg_match('/\/\*\[BEGIN POST-(\w+)\]\*\//i', $line, $post)) {
			$lookFor = Array($post[1], $i);
		} 
	} else { $lookFor = false; }
}
echo "<div style='float: right; height:99%;max-height:99%;overflow: auto;width: 49%;'><b>Variables:</b><br/><table border='1' cellspacing='0' style='border-color: #ccc;' cellpadding='5'>";
if (is_array($json["variables"])) {
	foreach ($json["variables"] as $key => $val) {
		echo "<tr><td valign='top'>".$key."</td><td valign='top'><pre>";
		var_dump($val);
		echo "</pre></td></tr>";	
	}
}
echo "</table></div>";
echo "<div style='float:left;width: 49%;height:99%;'><b>".$json["class"]." </b><br/><div style='border: 1px solid #ccc;height:100%;overflow: auto;white-space:nowrap;'>\n";
for ($i = $start; $i < $end; $i++) {
	$lines[$i] = str_replace(Array("Debugger::breakpoint();", "Debugger::wait(get_defined_vars());", "Debugger::wait();"), "", $lines[$i]);
	$lines[$i] = preg_replace('/(\s*)\$this->_callPrinter\s*\("[^"]+"\s*,\s*(.*)\)/i', '\1out(\2)', $lines[$i]);
	$lines[$i] = preg_replace('/(\s*)\$this->obj_data->/i', '\1$data->', $lines[$i]);
	if ($i == $json["line"]-1) {
		echo "<span id='selected' style='color:red;background-color: #ffc;'>".sprintf("%03s", $i - $start + 1).": ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."</span>";
	} else {
		echo sprintf("%03s", $i - $start + 1).": ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."";
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
