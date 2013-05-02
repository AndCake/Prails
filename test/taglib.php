<?php
echo ".";
include("lib/taglib.php");

function taglibTest() {
	
	describe("TagLib", function() {
		assertTrue(class_exists("TagLib"), "Unable to load TagLib library.");
		
		$tl = new TagLib();
		describe("#compile", function() use ($tl) {
			it("should convert body tag to comment.", function() use ($tl) {
				assertEqual($tl->compile("<c:body/>"), "<!--[content]-->");
				assertEqual($tl->compile("<c:body>test</c:body>"), "<!--[content]-->"); 
				assertEqual($tl->compile("-.-<c:body/>.-."), "-.-<!--[content]-->.-.");
			});

			it("should show error if tag not closed correctly.", function() use ($tl) {
				try {
					$content = $tl->compile("-.-<c:body>.-.");
					$thrown = false;
				} catch(Exception $ex) { $thrown = true;}
				if (!$thrown) throw new Exception("Unclosed tag not detected for `-.-<c:body>.-.`");
				try {
					$content = $tl->compile("-.-<test>.<c:if></test>");
					$thrown = false;
				} catch(Exception $ex) { $thrown = true;};
				if (!$thrown) throw new Exception("Unclosed tag not detected for `-.-<test>.<c:if></test>`");
				assertEqual($tl->compile("-.-<test>.<c:if cond=\"true\"></test></c:if>"), "-.-<test>.<? if (true) { ?></test><? } ?>");
			});

			it("should handle attributes correctly.", function() use ($tl) {
				assertEqual($tl->compile("<c:set name='name\"/>"), "<c:set name='name\"/>");
				assertEqual($tl->compile("<c:set name=\"name'>"), "<c:set name=\"name'>");
				assertEqual($tl->compile("<c:if cond=\"true\"></c:if>"), "<? if (true) {  } ?>");
				assertEqual($tl->compile("<c:if cond='false'></c:if>"), "<? if (false) {  } ?>");
			});

			it("should allow nesting", function() use ($tl) {
				assertEqual($tl->compile("<c:if cond='true'><c:body/></c:if>"), "<? if (true) { ?><!--[content]--><? } ?>");
				assertEqual($tl->compile("<c:body><c:body/></c:body>"), "<!--[content]-->");
				assertEqual($tl->compile("<c:foreach var=\"name\" name=\"res\"><c:if cond='true'><c:body/></c:if></c:foreach>"), "<? if (is_array(\$arr_param[\"name\"]) && count(\$arr_param[\"name\"]) > 0) foreach (\$arr_param[\"name\"] as \$res) {  \$arr_param[\"res\"] = \$arr_param[\"local\"][\"res\"] = \$res; ?><? if (true) { ?><!--[content]--><? }  } ?>");
				assertEqual($tl->compile("<c:if><c:if><c:if><c:if></c:if></c:if></c:if></c:if>"), "<? if () {  if () {  if () {  if () {  } ?><? }  }  } ?>");
				assertEqual($tl->compile("<c:foreach var='name'>x<c:foreach var='n2'>x</c:foreach></c:foreach>"), "<? if (is_array(\$arr_param[\"name\"]) && count(\$arr_param[\"name\"]) > 0) foreach (\$arr_param[\"name\"] as \$) {  \$arr_param[\"\"] = \$arr_param[\"local\"][\"\"] = $; ?>x<? if (is_array(\$arr_param[\"n2\"]) && count(\$arr_param[\"n2\"]) > 0) foreach (\$arr_param[\"n2\"] as \$) {  \$arr_param[\"\"] = \$arr_param[\"local\"][\"\"] = $; ?>x<? }  } ?>");
			});

			it("should convert variable expressions", function() use ($tl) {
				assertEqual($tl->compile("#test.me"), "<?=\$arr_param[\"test\"][\"me\"]?>");
				assertEqual($tl->compile("xxx #test.me yyy"), "xxx <?=\$arr_param[\"test\"][\"me\"]?> yyy");
				assertEqual($tl->compile("zzz #test.me[int] mmm"), "zzz <?=(int)\$arr_param[\"test\"][\"me\"]?> mmm");
				assertEqual($tl->compile("#amount.of[count]"), "<?=count(\$arr_param[\"amount\"][\"of\"])?>");
				assertEqual($tl->compile("<c:if cond='empty(#test.me)'>#another.test</c:if>"), "<? if (empty(\$arr_param[\"test\"][\"me\"])) { ?><?=\$arr_param[\"another\"][\"test\"]?><? } ?>");
			});
		});
	});
}

taglibTest();
?>
