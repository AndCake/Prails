<?php
/**
    Prails Web Framework
    Copyright (C) 2011  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Pushes an error onto the error stack (which is shown after the page is rendered)
 * @param STRING $str_error  the error message to be shown.
 */
function pushError ($str_error) {
	global $log;
	$log->error($str_error, true);
}

function run($cmd, $arr_args = Array(), $bol_inBackground = false) {
    $args = "";
    foreach ($arr_args as $arg) {
        if (strlen($arg) > 0) {
            $args .= escapeshellarg($arg)." ";
        }
    }
    if ($bol_inBackground) {
        exec("nice ".$cmd." ".$args." > log/runlog.txt 2>&1 &");
    } else {
        $arr_return = Array();
        exec($cmd." ".$args, $arr_return);
        return $arr_return;
    }
}

/**
 * Checkes whether all named items of an array have a value (especially useful to check for
 * valid user input after form submission)
 *
 * Example:
 * <code>
 *  $bol_valid = checkFields( Array(
 * 		"first_name"=>"",
 * 		"last_name"=>"Name",
 * 		"email"=>"test@test.com"
 * 	), Array(
 * 		"last_name", "email"
 *  ));
 * </code>
 * In this example the "last_name" and the "email" fields of the array are checked whether they are
 * populated. So in this case the return value of checkFields would be TRUE. If we would include
 * "first_name" in the second parameter, checkFields would return FALSE, because the first_name has
 * no data in it.
 *
 * @param ARRAY $arr_toCheck array that contains the data to be checked
 * @param ARRAY $arr_keys keys in the array whose values should be checked
 *
 * @return BOOLEAN true, if all specified fields have a value, else false
 */
function checkFields ($arr_toCheck, $arr_keys) {
    $bol_check = true;

    foreach ($arr_keys as $str_key) {
        $bol_check = $bol_check && (strlen($arr_toCheck[$str_key]) > 0);
    }

    return $bol_check;
}

/**
 * Scales a flash embed to a specified maximum size
 *
 * @param STRING $embed the complete embed code
 * @param INT $width the maximum width
 * @param INT $height the maximum height
 *
 * @return STRING the embed code with scaled down width/height
 */
function scaleEmbed($embed, $width, $height) {
    if (preg_match('/\s+src=["\']http:\/\/www\.msnbc\.msn\.com\/id\/([0-9]+)\/vp\/([0-9]+)[^"\']*["\']\s+/i', $embed, $arr_matches)) {
        $videoId = $arr_matches[2];
        $playerId = $arr_matches[1];
        $embed = '<embed height="'.$height.'" align="top" width="'.$width.'" '.
                 'pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" '.
                 'type="application/x-shockwave-flash" allowscriptaccess="always" seamlesstabbing="true" '.
                 'allowfullscreen="true" wmode="window" devicefont="false" menu="false" id="fl20172813" name="fl20172813" '.
                 'mayscript="" salign="tl" scale="noborder" quality="Best" bgcolor="#000000" loop="true" play="true" '.
                 'swliveconnect="TRUE" src="http://msnbcmedia.msn.com/i/MSNBC/Components/Video/_Player/swfs/embedPlayer/ep20090325a.swf?'.
                 'domain=www.msnbc.msn.com&amp;settings=22425448&amp;useProxy=true&amp;wbDomain=www.msnbc.msn.com&amp;'.
                 'launch='.$videoId.'&amp;sw=640&amp;sh=480&amp;EID=oVPEFC&amp;playerid='.$playerId.'"/>';
    }
    $embed = preg_replace("/height=\"[0-9]+\"/", "height=\"".$height."\"", preg_replace("/width=\"[0-9]+\"/", "width=\"".$width."\"", $embed));
    if (strpos($embed, "wmode=") !== false) {
        $embed = preg_replace("/wmode=\"(window|transparent|opaque)\"/", "wmode=\"opaque\"", $embed);
    } else {
        $embed = str_replace("<embed ", "<embed wmode=\"opaque\" ", $embed);
    }

    return $embed;
}

/**
 * checkes whether a string contains HTML code
 *
 * @param STRING embed content to check
 *
 * @return BOOLEAN true if it contains HTML code, else false
 */
function isEmbed($embed) {
    return (preg_match("/(<\/?)(\w+)([^>]*>)/e", $embed) > 0);
}

/**
 * checks whether a supplied URL is an external URL or not
 * @param STRING string	url to check
 * @return BOOLEAN true, if it is an external URL, else false 
 */
function isExternalURL($string) {
    return (preg_match("/http:\\/\\/(.*)/e", $string) > 0);
}

/**
 * A kind of COALESCE function for PHP. But it checks not only for NULL/not NULL but for real content.
 *
 * Example:
 * <code>
 * 	$_SESSION["user"] = if_set($_GET["user"], $_SESSION["user"]);
 * </code>
 * In this example it is checked whether the "user" parameter has been set via GET. If so, it is saved
 * in the session. If not, the session stays untouched.
 *
 * @param STRING $a
 * @param STRING $b
 *
 * @return STRING $a if it contains anything else than nothing, else $b
 */
function if_set ($a,$b) {
    return (strlen($a) > 0 ? $a : $b);
}

/**
 * Set the first parameter only if the second one is defined.
 *
 * Example:
 * <code>
 * 	set_var($_SESSION["user"], $_GET["user"]);
 * </code>
 * In this example it is checked whether the "user" parameter has been set via GET. If so, it is saved
 * in the session. If not, the session stays untouched.
 *
 * Note: this does (unlike if_set) also work with arrays and objects.
 *
 * @param MIXED $mix_a the variable that will probably be set
 * @param MIXED $mix_b the new content for $mix_a
 *
 */
function set_var (&$mix_a, &$mix_b) {
    if (isset($mix_b))
    $mix_a = $mix_b;
}

/**
 * returns the language settings preferred by the user
 * 
 * @param ARRAY allowedLanguages - list of RFC4646 compliant language identifiers (like "en-us" or "de-de")
 * @param STRING defaultLanguage - default language entry, also RFC4646 compliant
 * 
 * @return RFC4646 compliant language identifier
 */
function getUserLanguage($arr_allowedLanguages, $defaultLanguage) {
    $lang = "";
    if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
        $lang = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
    } else {
        $tlDomain = substr($_SERVER["HTTP_HOST"], strrpos(".", $_SERVER["HTTP_HOST"])+1);
        if (in_array($tlDomain, Array("com", "net", "org", "info", "biz", "eu", "edu", "gov"))) return $defaultLanguage;
        return $tlDomain;
    }
    $accepted_languages = preg_split('/,\s*/', $lang);
    $current_lang = $defaultLanguage;
    $current_q = 0;
    foreach ($accepted_languages as $accepted_language) {
        $res = preg_match ('/^([a-z]{1,8}(?:-[a-z]{1,8})*)(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?$/i', $accepted_language, $matches);
        if (!$res) continue;
        $lang_code = explode ('-', $matches[1]);
        if (isset($matches[2])) {
            $lang_quality = (float)$matches[2];
        } else {
            $lang_quality = 1.0;
        }
        while (count ($lang_code)) {
            if (in_array (strtolower (join ('-', $lang_code)), $arr_allowedLanguages)) {
                if ($lang_quality > $current_q) {
                    $current_lang = strtolower (join ('-', $lang_code));
                    $current_q = $lang_quality;
                    break;
                }
            }
            array_pop ($lang_code);
        }
    }
    return $current_lang;
}

/**
 * function to cut text after $limit characters
 *
 * Example:
 * <code>
 * 	wordCut($my_text, 200, '... read more');
 * </code>
 *
 * @param STRING $text text to cut
 * @param INT $limit maximum characters in text
 * @param STRING $msg text to append after text has been shortened
 *
 * @return STRING short version of text
 */
function wordCut($text, $limit, $msg){
    if (strlen($text) > $limit){
        $txt1 = wordwrap($text, $limit, '[cut]');
        $txt2 = explode('[cut]', $txt1);
        $ourTxt = $txt2[0];
        $finalTxt = $ourTxt.$msg;
    } else {
        $finalTxt = $text;
    }
    return $finalTxt;
}

/**
 * removes a directory recursively
 *
 * @param STRING $dir
 * @param BOOLEAN $DeleteMe
 */
function removeDir($dir, $DeleteMe) {
	if (!is_dir($dir)) return;
    if(!$dh = @opendir($dir)) return;
    while (false !== ($obj = readdir($dh))) {
        if($obj=='.' || $obj=='..') continue;
        if (!@unlink($dir.'/'.$obj)) removeDir($dir.'/'.$obj, true);
    }

    closedir($dh);
    if ($DeleteMe){
        @rmdir($dir);
    }
}

/**
 * Replaces in-text URLs by real hyperlinks.
 *
 * @param STRING $text text to scan
 *
 * @return STRING text with all URLs replaced by real links.
 */
function hyperlink($text, $bol_complete = false) {
    // match protocol://address/path/file.extension?some=variable&another=asf%
    $text = preg_replace("/(([a-zA-Z]+:\/\/)([a-z][a-z0-9_,\..-]*[a-z]{2,6})([a-zA-Z0-9\/*-?,_&%]*))/i", ($bol_complete ? "<a href=\"$1\">$1</a>" : "<a href=\"$1\">$3</a>"), $text);

    // match www.something.domain/path/file.extension?some=variable&another=asf%
    $text = preg_replace("/(\s)(www\.([a-z][a-z0-9_,\..-]*[a-z]{2,6})([a-zA-Z0-9,\/*-?&%]*))/i", ($bol_complete ? "$1<a href=\"http://$2\">$2</a>" : "$1<a href=\"http://$2\">$3</a>"), $text);

    return $text;
}

/**
 * convert text to an image (and directly output it to the browser)
 *
 * @param STRING $text text to convert
 * @param STRING $font font's file name (OPTIONAL)
 * @param INT $W width of the resulting image (OPTIONAL)
 * @param INT $H height of the resulting image (OPTIONAL)
 * @param INT $X x position of the string to be printed on the image (OPTIONAL)
 * @param INT $Y y position of the string to be printed on the image (OPTIONAL)
 * @param STRING $fsize font size to be used (OPTIONAL)
 * @param ARRAY $color Array consisting of the entries for R, G and B values for the text color
 * @param ARRAY $bgcolor Array consisting of the entries for R, G and B values for the image's background color
 */
function text2Image($text, $font="Alix2.ttf", $W=200, $H=20, $X=0, $Y=0, $fsize=18, $color=array(0x0,0x0,0x0), $bgcolor=array(0xFF,0xFF,0xFF)) {

    $im = @imagecreate($W, $H) or die("Cannot Initialize new GD image stream");

    $background_color = imagecolorallocate($im, $bgcolor[0], $bgcolor[1], $bgcolor[2]);        //RGB color background.
    $text_color = imagecolorallocate($im, $color[0], $color[1], $color[2]);            //RGB color text.

    imagettftext($im, $fsize, $X, $Y, $fsize, $text_color, $font, $text);

    header("Content-type: image/gif");
    return imagegif($im);
}

/**
 * function to display age instead of a time
 *
 * Options include
 * <code>
 *   to = time(); date to compute the range to
 *   parts = 1; number of parts to display max
 *	  precision = 7; lowest part to compute to (7 = second)
 *   distance = TRUE; include the 'ago' or 'away' bit
 *   separator = ', '; separates the parts
 *   period_names = Array(0=>Array("decade", "decades"), 1=>Array("year", "years"), 2=>Array("month","months"), 3=>Array("week", "weeks"), 4=>Array("day", "days"), 5=>Array("hour", "hours"), 6=>Array("minute", "minutes"), 7=>Array("second", "seconds"))
 *   less_than_1 = 'less than 1'
 *   distance_names = Array("ago", "away")
 *   distance_post = FALSE; 	should the distance added after or before all parts
 * </code>
 *
 * @param INT $time time in seconds from 1/1/1970
 * @param ARRAY $opt Options array (OPTIONAL)
 *
 * @return STRING (e.g. "2 days, 4 hours ago")
 */
function timeDiff($time, $opt = array()) {
    // The default values
    $defOptions = array(
        'to' => 0,
        'parts' => 1,
        'precision' => 7,
        'distance' => TRUE,
		  'distance_post'=>TRUE,
		  'distance_names'=>Array("ago", "away"),
        'separator' => ', ',
		  'less_than_1'=>'less than 1',
        'period_names'=>Array(0=>Array("decade", "decades"), 1=>Array("year", "years"), 2=>Array("month","months"), 3=>Array("week", "weeks"), 4=>Array("day", "days"), 5=>Array("hour", "hours"), 6=>Array("minute", "minutes"), 7=>Array("second", "seconds")),
    );
    $opt = array_merge($defOptions, $opt);
    // Default to current time if no to point is given
    (!$opt['to']) && ($opt['to'] = time());
    // Init an empty string
    $str = '';
    // To or From computation
    $diff = ($opt['to'] > $time) ? $opt['to']-$time : $time-$opt['to'];
    // An array of label => periods of seconds;
    $periods = array(
    	$opt["period_names"][0][0] => 315569260,
    	$opt["period_names"][1][0] => 31556926,
    	$opt["period_names"][2][0] => 2629744,
    	$opt["period_names"][3][0] => 604800,
    	$opt["period_names"][4][0] => 86400,
    	$opt["period_names"][5][0] => 3600,
    	$opt["period_names"][6][0] => 60,
    	$opt["period_names"][7][0] => 1
    );
    // Round to precision
    if ($opt['precision'] != 7)
    $diff = round(($diff/$periods[$opt["period_names"][$opt['precision']][0]])) * $periods[$opt["period_names"][$opt['precision']][0]];
    // Report the value is 'less than 1 ' precision period away
    (0 == $diff) && ($str = $opt["less_than_1"].' '.$opt["period_names"][$opt['precision']][0]);
    // Loop over each period
    foreach ($periods as $label => $value) {
        // Stitch together the time difference string
        (($x=floor($diff/$value))&&$opt['parts']--) && $str.=($str?$opt['separator']:'').($x.' '.$label.($x>1?'s':''));
        // Stop processing if no more parts are going to be reported.
        if ($opt['parts'] == 0 || $label == $opt['precision']) break;
        // Get ready for the next pass
        $diff -= $x*$value;
    }
    $opt['distance'] && ($opt["distance_post"]?$str.=($str&&$opt['to']>$time)?" ".$opt["distance_names"][0]:" ".$opt["distance_names"][1]:$str = (($str&&$opt["to"]>$time)?$opt["distance_names"][0]." ":$opt["distance_names"][1]." ").$str);
    return $str;
}

/**
 * convert a MIXED variable to a string (formatted for output)
 *
 * @param MIXED $mix variable to be converted to a string
 *
 * @return STRING formatted string representing the variable
 */
function tostring ($mix,$i=0) {
    if (is_array($mix))
    {
        $str.="\n".str_repeat("  ",$i)."Array(";
        $k = 0;
        foreach ($mix as $key=>$val)
        {
            $str.="\"".$key."\"=>".tostring($val, $i + 1).($k < count($mix) - 1 ? "," : "");
            $k++;
        }
        $str.=")";
    } else
    return "".$mix."";
    return $str;
}

/**
 * this method first checks whether a string is in UTF-8 and if not
 * converts it to UTF-8
 *
 * @param STRING $str String to be converted
 * @return STRING in UTF-8
 */
function toUTF8 ($str) {
    // check if it is in UTF-8
    if (!preg_match('%^(?:' .
			'[\x09\x0A\x0D\x20-\x7E]|' .
			'[\xC2-\xDF][\x80-\xBF]|' .
			'\xE0[\xA0-\xBF][\x80-\xBF]|' .
			'[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|' .
			'\xED[\x80-\x9F][\x80-\xBF]|' .
			'\xF0[\x90-\xBF][\x80-\xBF]{2}|' .
			'[\xF1-\xF3][\x80-\xBF]{3}|' .
			'\xF4[\x80-\x8F][\x80-\xBF]{2}' .
			')*$%xs', $str)) {
    // return converted string
    return utf8_encode($str);
			} else {		// if not already UTF-8
			    return $str;
			}
}

/**
 * Generate a tag cloud
 *
 * Example:
 * <code>
 * $tags = array('weddings' => array("num"=>32, "link"=>"index.php?action=...&tag_id=..."),
 * 				  'birthdays' => array("num"=>41, "link"=>"index.php?action=...&tag_id=..."),
 * 				  'landscapes' => array("num"=>76, "link"=>"index.php?action=...&tag_id=...")
 * 				 );
 * $result = printTagCloud($tags);
 * </code>
 *
 * @param ARRAY $tags array of tags containing the number of how often it is used and the link to be used when clicked at this tag
 * @param INT $maxTags defines how many tags should be shown at maximum (OPTIONAL)
 * @param BOOLEAN $bol_setSize change the font size for the tags depending on usage number
 *
 * @return STRING the HTML code for the tag cloud
 */
function printTagCloud($tags, $maxTags=-1, $bol_setSize=true) {
    // $tags is the array

    $max_size = 32; // max font size in pixels
    $min_size = 12; // min font size in pixels

    // largest and smallest array values
    $max_qty = 0;
    $min_qty = 100000000;
    foreach ($tags as $arr_tag) {
        if ($max_qty < $arr_tag["num"]) $max_qty = $arr_tag["num"];
        if ($min_qty > $arr_tag["num"]) $min_qty = $arr_tag["num"];
    }

    // find the range of values
    $spread = $max_qty - $min_qty;
    if ($spread == 0) { // we don't want to divide by zero
        $spread = 1;
    }

    // set the font-size increment
    $step = ($max_size - $min_size) / ($spread);

    // loop through the tag array
    $content = "";
    $count = 0;
    foreach ($tags as $key => $value) {
        if ($maxTags > 0 && $count > $maxTags) break;
        // calculate font-size
        // find the $value in excess of $min_qty
        // multiply by the font-size increment ($size)
        // and add the $min_size set above
        $size = round($min_size + (($value["num"] - $min_qty) * $step));

        $content .= '<a href="'.$value["link"].'" ';
        if ($bol_setSize)
        {
            $content .= 'style="font-size: ' . $size . 'px"';
        }
        $content .= ' title="' . $value["num"] . ' things tagged with ' . $key . '">' . $key . '</a> ';
        $count++;
    }
    return $content;
}

/**
 * outputs a javascript alert. By default it is just printed if debugging mode is enabled. But it can be manually set to always
 * output the alert.
 *
 * @param MIXED $mix variable to be alerted
 * @param BOOLEAN $always should the alert be shown eventhough debugging mode is disabled? (OPTIONAL)
 */
function alert ($mix, $always = false) {
    if (DEBUG_ERRORS == 1 || $always)
    echo "<script language='JavaScript'>alert(\"".tostring($mix)."\");\n</script>";
}

/**
 * Redirect the user to another site.
 *
 * @param STRING $url the URL to redirect the user to.
 */
function jumpTo ($url = "?") {
    global $SERVER;
	global $log;
	$log->info("Redirecting user to ".$url);
    if (($arr_url=@parse_url($url)) && $arr_url["host"]) {
        header ("Location: ".$url);
    } else {
        header ("Location: " . $SERVER.$url);
    }
    session_write_close();
    die();
}

/**
 * prints some debugging information to check if a certain class is loaded
 * 
 * @param STRING $name class's name to check
 * @param STRING $desc class's normal-language description
 */
function testClass ($name, $desc) {
    if (DEBUG_LEVEL == 2) {
        if (class_exists($name))
        echo $desc." loaded.<br>";
        else
        echo "<i>".$desc." NOT loaded!</i><br>";
    }
}

if (!function_exists("html_entity_decode")) {
    function html_entity_decode($string) {
        // replacing of numerical entities
        $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
        $string = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $string);
        // replacing of named entities
        $trans_tbl = get_html_translation_table(HTML_ENTITIES);
        $trans_tbl = array_flip($trans_tbl);
        return strtr($string, $trans_tbl);
    }
}

if (!function_exists("mime_content_type")) {
    function mime_content_type ($f) {
        return trim(exec('file -bi '.escapeshellarg($f))) ;
    }
}

function getMIMEType($filename) {
    preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix);

    switch(strtolower($fileSuffix[1]))
    {
        case "js" : return "application/x-javascript";
        case "json" : return "application/json";
        case "jpg" : case "jpeg" : case "jpe" : return "image/jpg";
        case "png" :case "gif" : case "bmp" : case "tiff" :return "image/".strtolower($fileSuffix[1]);
        case "css" : return "text/css";
        case "xml" : return "application/xml";
        case "doc" : case "docx" : return "application/msword";
        case "xls" : case "xlt" : case "xlm" : case "xld" : case "xla" : case "xlc" : case "xlw" : case "xll" : return "application/vnd.ms-excel";
        case "ppt" : case "pps" : return "application/vnd.ms-powerpoint";
        case "rtf" : return "application/rtf";
        case "pdf" : return "application/pdf";
        case "html" : case "htm" : case "php" : return "text/html";
        case "txt" : return "text/plain";
        case "mpeg" : case "mpg" : case "mpe" : return "video/mpeg";
        case "mp3" : return "audio/mpeg3";
        case "wav" : return "audio/wav";
        case "aiff" : case "aif" : return "audio/aiff";
        case "avi" : return "video/msvideo";
        case "wmv" : return "video/x-ms-wmv";
        case "mov" : return "video/quicktime";
        case "zip" : return "application/zip";
        case "tar" : return "application/x-tar";
        case "swf" : return "application/x-shockwave-flash";
        default : if(function_exists("mime_content_type")) {
            $fileSuffix = mime_content_type($filename);
        }
        return "unknown/" . trim($fileSuffix[0], ".");
    }
}

if (!function_exists("imagecreatefromjpeg")) {
    function imagecreatefromjpeg($file) {
        $r = null;
        system("jpegtopnm ".$file." > ".$file.".pnm", $r);
        if ($r != 0) { echo "invalid jpeg."; return false; }
        system("pnmtopng ".$file.".pnm > ".$file.".png");
        @unlink($file.".pnm");
        $im = imagecreatefrompng($file.".png");
        return $im;
    }
}

if (!function_exists("imagejpeg")) {
    function imagejpeg($res, $dest = null, $quality = 75) {
        if ($dest == null) {
            imagepng($res, $dest, ($quality / 100.0) * 9);
        } else {
            imagepng($res, $dest.".png", ($quality / 100.0) * 9);
            system("pngtopnm ".$dest.".png > ".$dest.".pnm");
            system("pnmtojpeg ".$dest.".pnm > ".$dest."");
            @unlink($dest.".png");
            @unlink($dest.".pnm");
        }

    }
}

/**
 * generates a thumbnail out of a image file
 *
 * @param STRING $src source image to be scaled down as thumbnail
 * @param STRING $dst destination image file name
 * @param INT $maxWidth maximum width for the resulting thumbnail (OPTIONAL)
 * @param INT $maxHeight maximum height for the resulting thumbnail (OPTIONAL)
 * @param INT $quality compression quality to be used
 *
 * @return BOOLEAN true, if generation of the thumbnail was successful, else false
 */
function createThumbnail($src, $dest, $MaxWidth = 500, $MaxHeight = 500, $Quality = 60)
{
    list($ImageWidth,$ImageHeight,$TypeCode)=getimagesize($src);
    $ImageType=($TypeCode==1?"gif":($TypeCode==2?"jpeg":
    ($TypeCode==3?"png":FALSE)));
    $CreateFunction="imagecreatefrom".$ImageType;
    $OutputFunction="image".$ImageType;
    if ($ImageType) {
        $Ratio=($ImageHeight/$ImageWidth);
        $ImageSource=$CreateFunction($src);
        if ($ImageWidth > $MaxWidth || $ImageHeight > $MaxHeight) {
            if ($ImageWidth > $MaxWidth) {
                $ResizedWidth=$MaxWidth;
                $ResizedHeight=$ResizedWidth*$Ratio;
            }
            else {
                $ResizedWidth=$ImageWidth;
                $ResizedHeight=$ImageHeight;
            }
            if ($ResizedHeight > $MaxHeight) {
                $ResizedHeight=$MaxHeight;
                $ResizedWidth=$ResizedHeight/$Ratio;
            }
            $ResizedImage=imagecreatetruecolor($ResizedWidth,$ResizedHeight);
            $originaltransparentcolor = imagecolortransparent( $ImageSource );
            if(
            $originaltransparentcolor >= 0 && $originaltransparentcolor < imagecolorstotal( $ImageSource )) {
                $transparentcolor = imagecolorsforindex( $ImageSource, $originaltransparentcolor );
                $newtransparentcolor = imagecolorallocate(
                    $ResizedImage,
                    $transparentcolor['red'],
                    $transparentcolor['green'],
                    $transparentcolor['blue']
                );
                // for true color image, we must fill the background manually
                imagefill( $ResizedImage, 0, 0, $newtransparentcolor );
                // assign the transparent color in the thumbnail image
                imagecolortransparent( $ResizedImage, $newtransparentcolor );
            }
            imagecopyresampled($ResizedImage,$ImageSource,0,0,0,0,$ResizedWidth, $ResizedHeight,$ImageWidth,$ImageHeight);
        } else {
            $ResizedWidth=$ImageWidth;
            $ResizedHeight=$ImageHeight;
            $ResizedImage=$ImageSource;
        }
        if ($TypeCode == 3) { $Quality = round($Quality / 10); }
        $OutputFunction($ResizedImage,$dest,$Quality);
        @unlink($src.".png");
        return true;
    } else
        return false;
}

/**
 * Tries to retrieve a thumbnail for a video embed.
 * @return MIXED - path to the thumbnail image, if nothing could be found: false 
 * @param object $embed embed code 
 */
function getThumbnailFromEmbed($embed)
{
    $sources = Array("/http:\\/\\/video\.google\.com\\/googleplayer\\.swf\\?docid=([^\"][a-zA-Z0-9-_]+)[&\"]/siU",
					 "/http:\\/\\/www\.youtube\.com\\/v\\/([^\"][a-zA-Z0-9-_]+)[&\"]/siU",
                     "/http:\\/\\/www\.msnbc\.msn\.com\\/id\\/[0-9]+\\/vp\\/([0-9]+)[#]/siU");
    $match = Array();
    if (preg_match($sources[0], $embed, $match) > 0) {
        // we have a google video
        $xml = doGet("http://video.google.com/videofeed?docid=".$match[1]);
        $match = Array();
        if (preg_match("/media:thumbnail url=\"([^\"].*)\"/siU", $xml, $match) > 0) {
            return html_entity_decode($match[1]);
        } else {
            return false;
        }
    } else if (preg_match($sources[1], $embed, $match) > 0) {
        return "http://img.youtube.com/vi/".$match[1]."/0.jpg";
    } else if (preg_match($sources[2], $embed, $match) > 0) {
        $html = doGet("http://www.msnbc.msn.com/id/".$match[1]."/");
        if (preg_match_all("/<img\s+src=[\"']([^\"']+)[\"'][^>]*>/i", $html, $match) > 0) {
            return $match[1][count($match[0]) - 1];
        }
    }
    return false;
}

function debugLog($message) {
	global $log;
	$log->debug($message);
}

function price($val)
{
    return str_replace(".", ",", sprintf("%.2f", $val))." EUR";
}

/**
 * sends an email. Any HTML code gets stripped off.
 *
 * @param STRING $to receipient's email address
 * @param STRING $body email's content
 * @param STRING $subject subject of the email to be sent
 * @param STRING $fromaddress sender's email address
 * @param STRING $fromname sender's name
 * @param Array $attachments file names to be attached (DEPRECATED)
 *
 * @return BOOLEAN true, if the mail was successfully sent, else false
 */
function send_mail($to, $body, $subject, $fromaddress, $fromname, $attachments=false)
{

    $eol="\r\n";

    # Common Headers
    $headers .= "From: ".$fromname."<".$fromaddress.">".$eol;
    $headers .= "Reply-To: ".$fromname."<".$fromaddress.">".$eol;
    $headers .= "Return-Path: ".$fromname."<".$fromaddress.">".$eol;    // these two to set reply address
    $headers .= "Message-ID: <".time()."-".$fromaddress.">".$eol;
    $headers .= "Content-Type: text/plain; charset=\"UTF-8\"".$eol;
    $headers .= "X-Mailer: Prails Mailer v".phpversion().$eol;          // These two to help avoid spam-filters

    # Text Version
    $msg = strip_tags(str_ireplace("<br>", "\n", str_ireplace("<br/>", "\n", $body))).$eol.$eol;
    # SEND THE EMAIL
    ini_set(sendmail_from,$fromaddress);  // the INI lines are to force the From Address to be used !
    $mail_sent = fmail($to, $subject, $msg, $headers);

    ini_restore(sendmail_from);

    return $mail_sent;
}

function sendMail($to, $subject, $content, $fromname, $fromaddress, $attachments = false) {
    $eol = "\r\n";
    $random_hash = md5(date('r', time()));
 
    $from = $fromname." <".$fromaddress.">";
    //define the headers we want passed. Note that they are separated with \r\n
    $headers = "From: ".$from.$eol."Reply-To: ".$from.$eol;
    //add boundary string and mime type specification
    $headers .= "Return-Path: ".$fromname."<".$fromaddress.">".$eol;    // these two to set reply address
    $headers .= "Message-ID: <".time()."-".$fromaddress.">".$eol;
    $headers .= "Content-Type: multipart/mixed; boundary=\"PHP-mixed-".$random_hash."\"".$eol;

    $message = "--PHP-mixed-".$random_hash.$eol;
    $message .= "Content-Type: multipart/alternative; boundary=\"PHP-alt-".$random_hash."\"".$eol.$eol;
    $message .= "--PHP-alt-".$random_hash."".$eol;
    $message .= "Content-Type: text/plain; charset=\"utf-8\"".$eol;
    $message .= "Content-Transfer-Encoding: 7bit".$eol.$eol;
    $message .= strip_tags(preg_replace("/<br\s*\\/?>/i", "\r\n", $content)).$eol.$eol;
    $message .= "--PHP-alt-".$random_hash."".$eol;
    $message .= "Content-Type: text/html; charset=\"utf-8\"".$eol;
    $message .= "Content-Transfer-Encoding: 7bit".$eol.$eol;
    $message .= $content.$eol.$eol;
    $message .= "--PHP-alt-".$random_hash."--".$eol.$eol;
    if ($attachments != false && !is_array($attachments)) {
        $message .= $attachments;
    } else if (is_array($attachments)) {
        for ($i = 0; $i < count($attachments["file"]); $i++) {
            if (file_exists($attachments["file"][$i])) {
                // File for Attachment
                $file_name = basename($attachments["name"][$i]);
                $f_contents = chunk_split(base64_encode(file_get_contents($attachments["file"][$i])));
                $f_type=getMIMEType($attachments["name"][$i]);

                $message .= "--PHP-mixed-".$random_hash."".$eol;
                $message .= "Content-Type: ".$f_type."; name=\"".$file_name."\"".$eol;
                $message .= "Content-Transfer-Encoding: base64".$eol;
                $message .= "Content-Description: ".$file_name.$eol;
                $message .= "Content-Disposition: attachment; filename=\"".$file_name."\"".$eol.$eol;
                $message .= $f_contents.$eol.$eol;
            }
        }
    }
    $message .= "--PHP-mixed-".$random_hash."--".$eol.$eol;
    //send the email
    ini_set(sendmail_from,$fromaddress);  // the INI lines are to force the From Address to be used !
    $mail_sent = fmail( $to, $subject, $message, $headers );
    ini_restore(sendmail_from);
    
    return $mail_sent;
}

/**
 * do a server-side get request to another server/site. HTTPS is supported.
 *
 * @param STRING $url URL to fetch data from
 *
 * @return STRING data fetched from the (other) server
 */
function doGet($url, $includeHeaders = false)
{
    if (!$url_info = parse_url($url))
    {
        pushError("could not post data to url '".$url."'");
        return false;
    }
    switch ($url_info["scheme"])
    {
        case "https":
            $scheme = "ssl://";
            $port = 443;
            break;
        case "http":
        default:
            $scheme = "";
            $port = 80;
            break;
    }
    $da = fsockopen($scheme . $url_info["host"], $port, $errno, $errstr, 30);
    if (!$da)
    {
        pushError($errstr." (".$errno.")");
        return false;
    }
    $path = $url_info["path"];
    if (strlen($url_info["query"]) > 0) $path .= "?".$url_info["query"];
    $content .= "GET ".$path." HTTP/1.0\r\n";
    $content .= "Host: ".$url_info["host"]."\r\n";
    $content .= "User-Agent: Klatcher Service\r\n";
    $content .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $content .= "Content-Length: ".strlen($postdata)."\r\n";
    $content .= "Connection: close\r\n\r\n";
    fwrite ($da, $content);
    $response = "";
    $content = "";
    $header = "";
    while (!feof($da))
    {
        $response .= @fgets($da, 128);
    }
    $response = split("\r\n\r\n", $response);
    $header = $response[0];
    $content = $response[1];
    if (!(strpos($header, "Transfer-Encoding: chunked") === false))
    {
        $aux = split("\r\n", $content);
        for ($i = 0; $i < count($aux); $i++)
        {
            if ($i == 0 || ($i % 2 == 0))
            {
                $aux[$i] = "";
            }
        }
        $content = implode("", $aux);
    }
	$result = chop($content);
	if ($includeHeaders) {
		$result = Array("body" => $result);
		$headerList = explode("\r\n", $header);
		foreach ($headerList as $entry) {
			$param = explode(":", $entry);
			$result["header"][trim($param[0])] = trim($param[1]);
		}
	}
    return $result;
}

/**
 * do a server-side post request to another server/site. HTTPS is supported.
 *
 * Example:
 * <code>
 * 	$reply = doPost("http://www.example.org/cgi-bin/process.cgi", Array(
 * 		"first_name"=>"Test",
 * 		"last_name"=>"User",
 * 		"age"=>"27",
 *  ));
 * </code>
 *
 * @param STRING $url URL to POST to
 * @param ARRAY $arr_post data to be posted
 *
 * @return STRING content of the server's reply
 */
function doPost($url, $arr_post = Array(), $user = false, $pass = false)
{
    if (!$url_info = parse_url($url))
    {
        pushError("could not post data to url '".$url."'");
        return false;
    }
    switch ($url_info["scheme"])
    {
        case "https":
            $scheme = "ssl://";
            $port = 443;
            break;
        case "http":
        default:
            $scheme = "";
            $port = 80;
            break;
    }
    $da = fsockopen($scheme . $url_info["host"], $port, $errno, $errstr, 30);
    if (!$da)
    {
        pushError($errstr." (".$errno.")");
        return false;
    }
    $postdata = "";
    foreach ($arr_post as $key=>$value)
    {
        if (strlen($postdata) > 0) $postdata .= "&";
        $postdata .= $key."=".urlencode($value);
    }
    $content .= "POST ".$url_info["path"].(strlen($url_info["query"])>0?"?".$url_info["query"]:"")." HTTP/1.1\r\n";
    $content .= "Host: ".$url_info["host"]."\r\n";
	if ($user !== false && $pass !== false) {
		$content .= "Authorization: Basic ".base64_encode($user.":".$pass)."\r\n";
	}
    $content .= "User-Agent: Klatcher Service\r\n";
    $content .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $content .= "Content-Length: ".strlen($postdata)."\r\n";
    $content .= "Connection: close\r\n\r\n";
    $content .= $postdata;
    fwrite ($da, $content);
    $response = "";
    $content = "";
    $header = "";
    while (!feof($da))
    {
        $response .= @fgets($da, 128);
    }
    $response = split("\r\n\r\n", $response);
    $header = $response[0];
    $content = $response[1];
    if (!(strpos($header, "Transfer-Encoding: chunked") === false))
    {
        $aux = split("\r\n", $content);
        for ($i = 0; $i < count($aux); $i++)
        {
            if ($i == 0 || ($i % 2 == 0))
            {
                $aux[$i] = "";
            }
        }
        $content = implode("", $aux);
    }
    return chop($content);
}

function html_entity_decode_utf8($string)
{
    static $trans_tbl;

    // replace numeric entities
    $string = preg_replace('~&#x([0-9a-f]+);~ei', 'code2utf(hexdec("\\1"))', $string);
    $string = preg_replace('~&#([0-9]+);~e', 'code2utf(\\1)', $string);

    // replace literal entities
    if (!isset($trans_tbl))
    {
        $trans_tbl = array();

        foreach (get_html_translation_table(HTML_ENTITIES) as $val=>$key)
        $trans_tbl[$key] = utf8_encode($val);
    }

    return strtr($string, $trans_tbl);
}

/** Returns the utf string corresponding to the unicode value
 * 
 * @param object $num unicode code to be converted to utf8
 * @return STRING utf string 
 */
function code2utf($num)
{
    if ($num < 128) return chr($num);
    if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
    if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    return '';
}

/**
 * rotates every single character of a string by a specified number of characters
 *
 * @param STRING $string string to be rotated
 * @param INT $n number of characters to rotate (positive: rotate up, negative: rotate down)
 *
 * @return STRING rotated string
 */
function rotate_string($string, $n) {
     
    $length = strlen($string);
    $result = '';

    $u_lower =  65; $u_upper =  90;
    $l_lower =  97; $l_upper = 122;
     
    $char_count = ($u_upper - $u_lower) +1;

    $sign = 1;
    if ($n < 0)
    {
        $sign = -1;
        $n = abs($n);
    }

    while( $n > $char_count ){
        $n -= $char_count;
    }

    $n = $n * $sign;
     
    for($i = 0; $i < $length; $i++) {
        $ascii = ord($string{$i});
         
        $rotated = $ascii;
         
        if ($ascii > 64 && $ascii < 91) {
            $rotated += $n;
            $rotated > 90 && $rotated += -90 + 64;
            $rotated < 65 && $rotated += -64 + 90;
        } elseif ($ascii > 96 && $ascii < 123) {
            $rotated += $n;
            $rotated > 122 && $rotated += -122 + 96;
            $rotated < 97 && $rotated += -96 + 122;
        }
         
        $result .= chr($rotated);
    }
     
    return $result;
}

/**
 * computes a 1-byte checksum for a string
 * @param $string
 * @return unknown_type
 */
function simpleCheckSum($string) {
    $int = 0;
    for ($i = 0; $i < strlen($string); $i++) {
        $int += ord($string[$i]);
    }
    return $int % 256;
}

/**
 * Removes HTML any variation of the <BR> tag in a given string
 * @param $string
 * @return $string
 */
function removeHtmlBreak($strText){
    return preg_replace("%<br\s*/{0,1}>%im", "", $strText);
}

/**
 * Returns the UNIX Timestamp for a Given Date
 * @param $string date which is in format yyyy-mm-dd
 * @return $int unix timestamp
 */
function get_timestamp($date) {
    list($y, $m, $d) = explode('-', $date);
    return mktime(0, 0, 0, $m, $d, $y);
}

/**
 * invokes an event and returns it's result
 *
 * @param STRING $str_event name of the event to be invoked
 * @param ARRAY $arr_param additional parameters that should be transmitted (OPTIONAL)
 *
 * @return MIXED if successful it will return the event's result. If an error occured (like event not found) it will return false
 */
function invoke($str_event, $arr_param = null, $keepCacheSettings = false)
{
	global $log, $profiler;
    
	$cacheFile = "cache/handler_".$str_event.md5($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"].serialize($arr_param)).".".Generator::getInstance()->obj_lang->language_id;
	if (file_exists($cacheFile)) {
		if (filemtime($cacheFile) >= $_SERVER["REQUEST_TIME"] - 3600) {
			if ($profiler) $profiler->logEvent("event_cache_hit#".$str_event);
			$log->trace("Fetching handler from cache ".$str_event."( ".$arr_param." )");
			$data = json_decode(file_get_contents($cacheFile.".state"), true);
			Generator::getInstance()->str_title = $data["title"];
			Generator::getInstance()->str_description = $data["description"];			
			Generator::getInstance()->arr_styles = $data["styles"];
	    	Generator::getInstance()->arr_noCacheStyles = $data["ncstyles"];
	    	Generator::getInstance()->arr_js = $data["js"];
	    	Generator::getInstance()->arr_noCacheJS = $data["ncjs"];
       		Generator::getInstance()->bol_isCachable = $data["cache"];	    	
       		
			return file_get_contents($cacheFile);
		} else {
			@unlink($cacheFile);
			@unlink($cacheFile.".state");
		}
	}
    
    global $__handlerCache;
	if (!is_array($__handlerCache)) $__handlerCache = Array();
	
	$oldCache = Generator::getInstance()->bol_isCachable;
	if (!$keepCacheSettings) Generator::getInstance()->bol_isCachable = false;
	
	list($module, $event) = explode(":", $str_event);
	$log->trace("Invoking ".$str_event."( ".$arr_param." )");
	if ($profiler) $profiler->logEvent("event_no_cache_hit#".$str_event);
	
	$module = strtolower($module);
    if (file_exists("modules/".$module) && file_exists("modules/".$module."/".$module.".php")) {
        if ($__handlerCache[$module] != null) {
            $obj_module = $__handlerCache[$module];
        } else {
            require_once 'modules/'.$module."/".$module.".php";
            $handlerClass = strtoupper($module[0]).substr($module, 1)."Handler";
            $obj_module = new $handlerClass();
            $__handlerCache[$module] = $obj_module;
        }
        if (method_exists($obj_module, $event) && ($result = $obj_module->$event($arr_param)) !== false) {
        	if (Generator::getInstance()->bol_isCachable) {
        		file_put_contents($cacheFile.".state", json_encode(Array(
        			"styles" => Generator::getInstance()->arr_styles,
        			"ncstyles" => Generator::getInstance()->arr_noCacheStyles,
        			"js" => Generator::getInstance()->arr_js,
        			"ncjs" => Generator::getInstance()->arr_noCacheJS,
       				"cache" => Generator::getInstance()->bol_isCachable,
        			"description" => Generator::getInstance()->str_description,
       				"title" => Generator::getInstance()->str_title      		
        		)));
        		file_put_contents($cacheFile, $result);
        	}
       		if (!$keepCacheSettings) Generator::getInstance()->bol_isCachable = $oldCache;
            return $result;
        } else if (!method_exists($obj_module, $event)) {
       		if (!$keepCacheSettings) Generator::getInstance()->bol_isCachable = $oldCache;
        	return invoke("main:pageNotFound", $arr_param);
        } else {
            pushError("Error generating event result. Maybe the handler for this event does not exist.");
        }
    } else {
        // we got a builder!
        if (file_exists("modules/builder") && file_exists("modules/builder/builder.php")) {
		    if ($module == "templates") {
				$_GET["mod"] = $event;
				$_GET["resource"] = substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], "/images/") + strlen("/images/"));
				$result = invoke("builder:createResource", $arr_param);
		    } else {
			    // use it!
	            $_GET["builder"]["event"] = $str_event;
	            $result = invoke("builder:run", $arr_param);
		    }
        } else {
        	pushError("could not find module for event ".$str_event);
        	$result = invoke("main:pageNotFound", $arr_param);
        }
        
       	if (Generator::getInstance()->bol_isCachable) {
       		file_put_contents($cacheFile.".state", json_encode(Array(
       			"styles" => Generator::getInstance()->arr_styles,
       			"ncstyles" => Generator::getInstance()->arr_noCacheStyles,
       			"js" => Generator::getInstance()->arr_js,
       			"ncjs" => Generator::getInstance()->arr_noCacheJS,
       			"cache" => Generator::getInstance()->bol_isCachable,
   				"description" => Generator::getInstance()->str_description,
   				"title" => Generator::getInstance()->str_title      		
       		)));
       		file_put_contents($cacheFile, $result);
       	}
       	if (!$keepCacheSettings) Generator::getInstance()->bol_isCachable = $oldCache;
        return $result;
    }

    return false;
}


/**
 * This method recursively merges two arrays; if values differ, the 
 * value of the first array is overwritten by the second one.
 *  
 * @return merged array 
 * @param object $array1
 * @param object $array2
 */
function array_merge_recursive_distinct(&$array1, &$array2)
{
	$merged = $array1;
  	foreach($array2 as $key=>&$value)
  	{
    	if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]))
    	{
      		$merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
    	} else
    	{
      		$merged[$key] = $value;
    	}
  	}

  	return $merged;
}

/**
 * This method creates a unified diff of two strings or arrays
 * 
 * @return Array of changes (each entry has two keys: "d" (which contains the removed lines) and "i" (which contains the inserted lines)) 
 * @param mixed $old
 * @param mixed $new
 */
function diff($old, $new)
{
	// if the inputs are strings
	if (is_string($old) && is_string($new)) {
		// convert them to arrays by splitting them into lines
		$old = explode("\n", $old);
		$new = explode("\n", $new);
	}
	
	if (is_array($old)) {
	    foreach ($old as $oindex=>$ovalue)
	    {
	        $nkeys = array_keys($new, $ovalue);
	        foreach ($nkeys as $nindex)
	        {
	            $matrix[$oindex][$nindex] = isset($matrix[$oindex-1][$nindex-1]) ? $matrix[$oindex-1][$nindex-1]+1 : 1;
	            if ($matrix[$oindex][$nindex] > $maxlen)
	            {
	                $maxlen = $matrix[$oindex][$nindex];
	                $omax = $oindex+1-$maxlen;
	                $nmax = $nindex+1-$maxlen;
	            }
	        }
	    }
	}
    if ($maxlen == 0)
        return array ( array ('d'=>$old, 'i'=>$new));
		
    return array_merge(
    	diff(array_slice($old, 0, $omax), 
			 array_slice($new, 0, $nmax)
		), 
		array_slice($new, $nmax, $maxlen),
    	diff (array_slice($old, $omax+$maxlen), 
			  array_slice($new, $nmax+$maxlen))
		);
}

/**
 * this function returns the value of the first parameter which is set
 * @return mixed
 */
function one_of() {
	$num = count(func_get_args());
	
	for ($i = 0; $i < $num; $i++) {
		if (func_get_arg($i) != null && strlen("".func_get_arg($i)) > 0) {
			return func_get_arg($i);
		}
	}
}

/**
 * this function copies the received file to the specified directory
 * @param String $fileName - name of the file (as transmitted as GET parameter for qw:upload fields)
 * @param String $targetPath - directory to put the file into
 * @return mixed FALSE if no file received, else the name (including path) of the file after copy is completed
 */
function receiveFile($fileName, $targetPath) {
	global $log;
	// only allow file uploads via POST
	if (strtoupper($_SERVER["REQUEST_METHOD"]) != "POST") {
		$log->error("File uploads only allowed via POST!");
	    return false;
	}
	if (!is_dir($targetPath)) {
    	if (!@mkdir($targetPath, 0755, true)) {
    		$log->error("Failed to create directory ".$targetPath." in receiveFile.");
    		return false;
    	}	
	}
	if ($targetPath[strlen($targetPath) - 1] != '/') {
		$targetPath .= "/"; 
	}
	if (isset($_FILES['file'])) {
	    $ftmp = $_FILES['file']['tmp_name'];
	    $oname = basename($_FILES["file"]["name"]);
	    $oname = preg_replace('/[^a-zA-Z0-9._\-]/', '', $oname);
	    if (strlen($oname) <= 0) $oname = "_";
	    while (file_exists($targetPath.$i.$oname)) { $i++; }
	    move_uploaded_file($ftmp, $targetPath.$i.$oname);
	} else {
		$oname = $fileName;
	    $oname = preg_replace('/[^a-zA-Z0-9._\-]/', '', $oname);
	    if (strlen($oname) <= 0) $oname = "_";
	    while (file_exists($targetPath.$i.$oname)) { $i++; }
		file_put_contents($targetPath.$i.$oname, file_get_contents("php://input"));
	}	
	
	return $targetPath.$i.$oname;
}

?>
