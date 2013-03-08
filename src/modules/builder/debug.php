<pre>
<?php

if (strlen($_GET["cmd"]) > 0) {
	file_put_contents("../../cache/debugger.do", $_GET["cmd"]);
}

$json = json_decode(@file_get_contents("../../cache/debugger.state"), true);
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
?>
<style type="text/css">
	.line-number { 
		width: 30px;
		display: inline-block;
		text-align: right;
		background-color: #EFEFEF;
		padding-left: 2px;
		padding-right: 5px;
		color: #666;
	}
	#selected .line-number {
		color: red;
		background-color: #ffc;
	}
	@font-face {
	    font-family: 'MesloLGMDZRegular';
	    src: url('../../templates/builder/css/meslolgm-dz-regular-webfont.eot');
	    src: url('../../templates/builder/css/meslolgm-dz-regular-webfont.eot?#iefix') format('embedded-opentype'),
	         url('../../templates/builder/css/meslolgm-dz-regular-webfont.woff') format('woff'),
	         url('../../templates/builder/css/meslolgm-dz-regular-webfont.ttf') format('truetype'),
	         url('../../templates/builder/css/meslolgm-dz-regular-webfont.svg#MesloLGMDZRegular') format('svg');
	    font-weight: normal;
	    font-style: normal;
	}

	pre {
		font-family: MesloLGMDZRegular, monospace;
		font-size: 11px;	
	}
	div table {
		border: 0px;
		border-color: #CCC;
		border-collapse: collapse;
	}
	div table tr td pre {
		max-height: 1.25em;
		overflow: hidden;
	}
	div table tr td:first-child {
		padding-left: 18px;
		background: transparent url(../../templates/builder/images/icon_maximize.gif) 2px 5px no-repeat;
		cursor: pointer;
		font-size: 11px;
	}
	div table tr.open td:first-child {
		background-image: url(../../templates/builder/images/icon_minimize.gif);
	}
	div table tr.open td pre {
		max-height: inherit;
		overflow: inherit;
	}
</style>
<div style='float: right; height:99%;max-height:99%;overflow: auto;width: 49%;'><b>Variables:</b><br/><table border='1' cellspacing='0' style='border-color: #ccc;' cellpadding='5'>
<?
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
	$lines[$i] = str_replace(Array("Debugger::breakpoint();", "Debugger::wait(get_defined_vars(), __LINE__);", "Debugger::wait();", "/*[END ACTUAL]*/"), "", $lines[$i]);
	$lines[$i] = preg_replace('/(\s*)\$this->_callPrinter\s*\("[^"]+"\s*,\s*(.*)\)/i', '\1out(\2)', $lines[$i]);
	$lines[$i] = preg_replace('/(\s*)\$this->obj_data->/i', '\1$data->', $lines[$i]);
	if ($i == $json["line"]) {
		echo "<span id='selected' style='color:red;background-color: #ffc;'><span class='line-number'>".($i - $start + 1)."</span> ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."</span>";
	} else {
		echo "<span class='line-number'>".($i - $start + 1)."</span> ".str_replace(Array("<?", "&lt;?", "?>", "?&gt;", "<br>", "\n"), "", highlight_string("<?".$lines[$i]."?>", true))."";
	}
}
echo "</div></div>\n";

?>
</pre>
<script type="text/javascript">
//<![CDATA[
	setTimeout(function() {
		var sel = document.getElementById("selected");
		if (sel) {
			sel.parentNode.scrollTop = sel.offsetTop - 60;
		}
		var tds = document.getElementsByTagName("td");
		for (var i = 0, len = tds.length; i < len; i++) {
			tds[i].onclick = function() {
				if (this.parentNode.className.indexOf("open") >= 0) {
					this.parentNode.className = "";
				} else {
					this.parentNode.className = "open";
				}
			};
		}
	}, 10);
	opener && opener.focus();
	self && self.focus();
//]]>
</script>
