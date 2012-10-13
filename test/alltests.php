<?
$errors = Array();

try {
	require_once("../test/taglib.php");
} catch(Exception $e) {
	array_push($errors, $e->getMessage());
}

if (count($errors) <= 0) {
	echo "\nSuccess.\n";
} else {
	echo "\nError:\n".join("\n", $errors);
}
?>
