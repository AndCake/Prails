<?
// some testing functions
function assertTrue($a, $message = null) {
	if ($a !== true) {
		throw new Exception(empty($message) ? "Assertion failed!" : $message);
	}
}
function assertEqual($a, $b, $message = null) {
	assertTrue($a == $b, empty($message) ? "Assertion failed: `".$a."` isn't `".$b."`" : $message);
}
function assertFalse($a, $message = null) {
	assertTrue($a === false, empty($message) ? "Assertion failed: ".$a." isn't false." : $message);
}

function describe($entity, $callback) {
	try {
		$callback();
		return true;
	} catch(Exception $ex) {
		throw new Exception("$entity ".$ex->getMessage());
	}
}
function it($entity, $callback) { return describe($entity, $callback); }
function should($entity, $callback) { return describe($entity, $callback); }

?>
