<?
$errors = Array();

include("../test/libTest.php");
try {
	require_once("../test/taglib.php");
	require_once("../test/database.php");
	require_once("../test/httpclient.php");
} catch(Exception $e) {
	array_push($errors, $e->getMessage());
}

if (count($errors) <= 0) {
	echo "\nSuccess.\n";
} else {
	echo "\nError:\n".join("\n", $errors);
}
?>
