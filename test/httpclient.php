<?php
echo ".";
include("lib/tools.php");
include("lib/httpclient.php");

function HttpClientTest() {
	
	describe("HTTPClient", function() {
		assertTrue(class_exists("HTTPClient"), "Unable to load HTTPClient class.");

		describe("::doGet", function() {
			should("follow redirects when called with defaults", function() {
				$content = HTTPClient::doGet("http://google.com/", null, null, null, null, $response);
				assertEqual($response["status"], 200, "Unable to get page.");
				assertFalse(empty($content), "Page got not content");
			});

			should("allow no-following of redirects when specified in options.", function() {
				$content = HTTPClient::doGet("http://google.com/", Array(
					"follow" => false,
					"response" => &$response
				));
				assertEqual($response["status"], 301, "Unable to get page.");
				assertFalse(empty($content), "Page got not content");
			});
		});

		describe("::doPost", function() {
			
		});
	});
}

httpClientTest();
?>
