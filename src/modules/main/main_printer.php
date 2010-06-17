<?php

class MainPrinter
{
    var $obj_lang;
    var $str_base;

    function MainPrinter($str_lang)
    {
        $this->obj_lang = new LangData($str_lang);
        $obj_gen = Generator::getInstance();
        $obj_gen->addStyleSheet("templates/main/css/main.css");
	$obj_gen->addStyleSheet("templates/main/css/global.css");

        $obj_gen->addJavaScript("templates/main/js/main.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/lib/prototype.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/src/scriptaculous.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/builder.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/effects.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/controls.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/dragdrop.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/sound.js");
	$obj_gen->addJavaScript("templates/main/js/scriptaculous/src/slider.js");
        $obj_gen->addJavaScript("templates/main/js/scriptaculous/lib/control.modal.js");
	$obj_gen->addJavaScript("templates/main/js/fileselector.js");
	$obj_gen->addJavaScript("templates/main/js/evalfields.js");
	$obj_gen->addJavaScript("templates/main/js/overlabels.js");
	$obj_gen->addJavaScript("templates/main/js/base64.js");
	$obj_gen->addJavaScript("templates/main/js/browserdetect.js");
	$obj_gen->addJavaScript("templates/main/js/cookie.js");
        $obj_gen->addJavaScript("templates/main/js/control.date.js");
	$obj_gen->addJavaScript("templates/main/js/global.js");
		
        $obj_gen->setTitle("Prails Home");
        $obj_gen->setDescription("To develop web applications at extreme speed");
        $obj_gen->setKeywords(explode(",", "web development,php,framework"));

        $this->str_base = "?";
    }

    function home($arr_param)
    {
        $str_content = Generator::getInstance()->includeTemplate("templates/main/html/home.html", $arr_param);
        return $str_content;
    }
}
?>
