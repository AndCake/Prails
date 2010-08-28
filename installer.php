<?php

// this installation file should be automatically placed into a temporary directory
// in order to let it unpack the package contents and stuff
$dir = dirname(__FILE__);
$file = "prails.tar.bz2";

function recurse_copy($src, $dst) { 
    $dir = opendir($src); 
    $arr_rollback = Array();
    $rollback = false;
    
    if (!is_dir($dst)) {
        array_push($arr_rollback, $dst);
        if (!@mkdir($dst)) {
            echo "Error creating directory ".$dst;
            $rollback = true;
        } 
    }
    while (!$rollback && false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                if (!recurse_copy($src . '/' . $file, $dst . '/' . $file)) {
                    $rollback = true;
                }
            } else {
                array_push($arr_rollback, Array($src."/".$file, $dst."/".$file));
                // first create a backup of the original file, if existent
                if (file_exists($dst."/".$file)) {
                    if (!copy($dst."/".$file, "backup.".md5($dst."/".$file))) {
                        echo "Error creating backup for file ".$dst."/".$file;
                        $rollback = true;
                        break;
                    }
                }
                if (!copy($src . '/' . $file, $dst . '/' . $file)) {
                    echo "Error copying file ".$src."/".$file." to ".$dst."/".$file."";
                    $rollback = true;
                } 
            } 
        } 
    } 
    
    if ($rollback) {
        $arr_rollback = array_reverse($arr_rollback);
        foreach ($arr_rollback as $entry) {
            if (is_array($entry) && file_exists("backup.".md5($entry[1]))) {
                // if we have a backup file, restore it
                if (copy("backup.".md5($entry[1]), $entry[1])) {
                    @unlink("backup.".md5($entry[1]));
                }
            } else if (is_dir($entry)) {
                // if we created a directory during copy, remove it
                unlink($entry);
            }
        }
    }
        
    closedir($dir); 
    
    return $rollback;
} 

if ($_GET["version"]) {
	$version = $_GET["version"];
	$url = "http://prails.googlecode.com/files/prails-".$version.".tar.bz2";
	if (!($fileContent=file_get_contents($url))) {
		die("Error while downloading the Prails update.");
	}
	if (!file_put_contents($file, $fileContent)) {
		die("Error while downloading the Prails update. Please check permissions in ".$dir." .");
	}

	die("success\ncache/installer.php\nInstalling new version...");
} else {
    $warnings = "";
	// unpack everything
  	$disabled = explode(', ', ini_get('disable_functions'));
	if (in_array('exec', $disabled)) {
		die("Error while unpacking Prails update: cannot be unpacked due to disabled 'exec' function. Please check server configuration.");
	}
	if (!file_exists($file)) {
		die("Error while unpacking Prails update: package not found.");
	}
	
	exec("tar xvjf ".$file."");
	if (!file_exists("prails")) {
		die("Error while unpacking Prails update: unpacking failed.");
	}
	
	// run the actual installation

	// configuration file needs to be merged, so create a backup...
	if (!copy("../conf/configuration.php", "backup.configuration.php")) {
	   die("Error creating backup for configuration.");
	}

	// this should copy all files to the current installation directory
	if (!recurse_copy("prails", "..")) {
	   die();
	}
	
    $oldConf = file_get_contents("backup.configuration.php");
    $newConf = $newBackupConf = file_get_contents("../conf/configuration.php");
    
    preg_match_all('@/\*<KEEP-([0-9]+)>\*/(([^/]|/[^*]|/\*[^<]|/\*<[^/]|/\*</[^K]|/\*</K[^E]|/\*</KE[^E]|/\*</KEE[^P])*)/\*</KEEP-\1>\*/@', $oldConf, $matches);
    $conf = Array();
    foreach ($matches[0] as $key=>$match) {
        $conf[$matches[1][$key]] = $matches[2][$key];
    }

    preg_match_all('@/\*<KEEP-([0-9]+)>\*/(([^/]|/[^*]|/\*[^<]|/\*<[^/]|/\*</[^K]|/\*</K[^E]|/\*</KE[^E]|/\*</KEE[^P])*)/\*</KEEP-\1>\*/@', $newConf, $matches);       
    foreach ($matches[0] as $key => $match) {
        $newConf = str_replace($matches[2][$key], $conf[$matches[1][$key]], $newConf);
    }
    
    file_put_contents("../conf/configuration.php", $newConf);
    exec("php -l ../conf/configuration.php", $error, $code);
    if ($code != 0) {
        // we have a syntax error in the code
        file_put_contents("../conf/configuration.php", $newBackupConf);
        $warnings .= "Error while restoring configuration options. ";
        $warnings .= "Configuration has been reset to installation default. Original configuration has been saved in ".$dir."/backup.configuration.php .";
    }    
    
    die("success\n--\n".$warnings);
}

?>
