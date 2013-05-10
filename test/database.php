<?php
echo ".";
include("conf/configuration.php");
include("lib/cacheable.php");
include("lib/".strtolower(DB_TYPE).".php");
include("lib/database.php");

function databaseTest() {
	
	describe("Database", function() {
		assertTrue(class_exists("Database"), "Unable to load database abstraction layer.");
		
		$tl = new Database();
		describe("#_parseQuery", function() use ($tl) {
			it("should allow specifying query parameters", function() use ($tl) {
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t", 2)), "SELECT 2 AS t");
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t", 'x')), "SELECT 'x' AS t");
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t, %2 AS c", 'x', 3)), "SELECT 'x' AS t, 3 AS c");
			});

			it("should ignore strings", function() use ($tl) {
				assertEqual(
					$tl->_parseQuery(
						Array(
							"UPDATE test SET x='hallo %2 and test' and y=\"welt and %3 test\" WHERE 2=%1 and rand()=2", 
							'xxx'
						)
					), 
					"UPDATE test SET x='hallo %2 and test' and y=\"welt and %3 test\" WHERE 2='xxx' and rand()=2"
				);
				assertEqual(
					$tl->_parseQuery(
						Array(
							"UPDATE test SET x='hallo %2 and test' and y=\"welt and %3 test\" WHERE 2=%1 and rand()=%2", 
							'xxx', 
							99.3
						)
					), 
					"UPDATE test SET x='hallo %2 and test' and y=\"welt and %3 test\" WHERE 2='xxx' and rand()=99.3"
				);
			});
			it("should ignore escaped strings", function() use ($tl){
				assertEqual(
					$tl->_parseQuery(
						Array(
							"UPDATE test SET x='hallo\\'s and more %2 and test' and y=\"welt \\\" and %3 test\" WHERE 2=%1 and rand()=2", 
							'xxx'
						)
					), 
					"UPDATE test SET x='hallo\\'s and more %2 and test' and y=\"welt \\\" and %3 test\" WHERE 2='xxx' and rand()=2"
				);
			});
			it("should handle empty parameters", function() use ($tl){
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t, '%2' AS c", "")), "SELECT '' AS t, '%2' AS c");
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t, %2 AS c", "", null)), "SELECT '' AS t, NULL AS c");
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t, %2 AS c", 0, false)), "SELECT 0 AS t, 0 AS c");
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t")), "SELECT %1 AS t");
			});

			it("should escape parameter strings", function() use ($tl){
				assertEqual($tl->_parseQuery(Array("SELECT %1 AS t", "'x'x'x'x")), "SELECT '''x''x''x''x' AS t");
			});
			it("should ignore %0 and everything that might parse to it", function() use ($tl) {
				assertEqual($tl->_parseQuery(Array("SELECT %0 AS t, %1 AS c", 1)), "SELECT %0 AS t, 1 AS c");
				assertEqual($tl->_parseQuery(Array("SELECT % AS t, %1 AS c", 1)), "SELECT % AS t, 1 AS c");
			});
		});
	});
}

databaseTest();
?>