<?php
function typedCallback($arr) {
	echo json_encode($arr)."\n";
	flush();
}

function globalCallback($arr) {
	echo json_encode($arr)."\n";
	flush();
}

$name = (strlen($_GET["name"]) > 0 ? $_GET["name"] : "system");
@chdir("../../");
include 'lib/debug/profiler.php';
$profiler = new Profiler($name);
if (strlen($_GET["type"]) > 0) {
	$profiler->processEvents($_GET["type"], "typedCallback");
} else {
	$profiler->processAllEvents("globalCallback");
}
?>