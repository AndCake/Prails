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
                if (!@copy($src . '/' . $file, $dst . '/' . $file)) {
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
                if (@copy("backup.".md5($entry[1]), $entry[1])) {
                    @unlink("backup.".md5($entry[1]));
                }
            } else if (is_dir($entry)) {
                // if we created a directory during copy, remove it
                @unlink($entry);
            }
        }
    }
        
    closedir($dir); 
    
    return !$rollback;
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
    $oldConf = file_get_contents("../conf/configuration.php");	
	if (!copy("../conf/configuration.php", "backup.configuration.php") || !file_exists("backup.configuration.php")) {
	   die("Error creating backup for configuration.");
	}
	
	// backup .htaccess
	if (!copy("../.htaccess", "backup.htaccess") || !file_exists("backup.htaccess")) {
	   die("Error creating backup for .htaccess .");
	}
	copy("../.groups", "backup.groups");
    copy("../.users", "backup.users");
	copy("../favicon.ico", "backup.favicon.ico");

	// this should copy all files to the current installation directory
	if (!recurse_copy("prails", "..")) {
	   die("Error installing new prails version.");
	}
    @unlink($file);
    exec("rm -rf prails");	
	
	// copy back the .groups and .users file
	if (copy("backup.groups", "../.groups")) unlink("backup.groups"); else $warnings .= "Unable to restore groups. Backup stored in ".$dir."/backup.groups .<br/>";
    if (copy("backup.users", "../.users")) unlink("backup.users"); else $warnings .= "Unable to restore users. Backup stored in ".$dir."/backups.users .<br/>";
   	copy("backup.favicon.ico", "../favicon.ico"); 
    $users = file("../.users");
    $adminFound = false;
    foreach ($users as &$user) {
    	list($name, $pwd) = explode(":", $user);
		if ($name == "admin") {
			$adminFound = true;
		}
    	if (strlen(trim($pwd)) != 32 || !preg_match('/^[a-f0-9]+$/mi', trim($pwd))) {
    		$pwd = md5(trim($pwd));
    		$user = $name.":".$pwd;
    	}
	$user = trim($user);
    }
    if (!$adminFound) {
    	$pwd = substr(md5(time()), 0, 6);
    	$users[] = "admin:".md5($pwd);
    	$groups = file("../.groups");
    	$adminFound = false;
    	foreach ($groups as &$group) {
    		list($name, $users) = explode("=", $group);
    		if ($name == "admin") {
    			$users .= ",admin";
    			$adminFound = true;
    			$group = $name."=".$users;
    		}
		$group = trim($group);
    	}
    	if (!$adminFound) $groups[] = "admin=admin";
    	@file_put_contents("../.groups", implode("\n", $groups)) or $warnings .= "Unable to add new admin user to the admin group.<br/>";
    	$warnings .= "An admin account has been added to your users to let you manage them. It's password is: ".$pwd."<br/>";
    }
    @file_put_contents("../.users", implode("\n", $users)) or $warnings .= "Unable to add new admin to list of users.<br/>";
    
    // merge .htaccess
    $oldHt = file_get_contents("backup.htaccess");
    $newHt = file_get_contents("../.htaccess");
	$startMarker = "#--START_CUSTOM--#";
	$endMarker = "#--END_CUSTOM--#";
	$start = strpos($oldHt, $startMarker) + strlen($startMarker);
	$len = (strpos($oldHt, $endMarker, $start) - 1) - $start;
	$area = substr($oldHt, $start, $len);

	$start = strpos($newHt, $startMarker) + strlen($startMarker);
	$len = (strpos($newHt, $endMarker, $start) - 1) - $start;
	$file = substr($newHt, 0, $start)."\n".$area."\n".substr($newHt, $start+$len);
	if (!@file_put_contents("../.htaccess", $file)) {
	   $warnings .= "Unable to re-integrate custom .htaccess rules into the newer file. Backup stored in ".$dir."/backup.htaccess .<br/>";
	} else {
	   unlink("backup.htaccess");
	}

    // merge configuration
    $newConf = $newBackupConf = file_get_contents("../conf/configuration.php");
    
    preg_match_all('@/\*<KEEP-([0-9]+)>\*/(([^/]|/[^*]|/\*[^<]|/\*<[^/]|/\*</[^K]|/\*</K[^E]|/\*</KE[^E]|/\*</KEE[^P])*)/\*</KEEP-\1>\*/@', $oldConf, $matches);
    $conf = Array();
    foreach ($matches[0] as $key=>$match) {
        $conf[$matches[1][$key]] = $matches[2][$key];
    }

    preg_match_all('@/\*<KEEP-([0-9]+)>\*/(([^/]|/[^*]|/\*[^<]|/\*<[^/]|/\*</[^K]|/\*</K[^E]|/\*</KE[^E]|/\*</KEE[^P])*)/\*</KEEP-\1>\*/@', $newConf, $matches);       
    foreach ($matches[0] as $key => $match) {
        $value = $conf[$matches[1][$key]];
        // make sure that the DB is re-deployed the first time after upgrade
        if (strpos($value, "FIRST_RUN") !== false) {
	        $lines = explode("\n", $value);
	        foreach ($lines as &$line) {
				if (preg_match('/\s*"FIRST_RUN"\s*=>\s*[a-zA-Z]+\s*,/', $line)) {
					$line = '"FIRST_RUN" => true,';
				}
	        }
	        $value = implode("\n", $lines);
        }

       	$newConf = str_replace($matches[2][$key], $value, $newConf);
    }
    
    file_put_contents("../conf/configuration.php", $newConf);
    exec("php -l ../conf/configuration.php", $error, $code);
    if ($code != 0) {
        // we have a syntax error in the code
        file_put_contents("../conf/configuration.php", $newBackupConf);
        $warnings .= "Error while restoring configuration options. ";
        $warnings .= "Configuration has been reset to installation default. Original configuration has been saved in ".$dir."/backup.configuration.php .<br/>";
    } else {
        if (!@unlink("backup.configuration.php")) {
        	$warnings .= "Unable to remove backup configuration file.<br/>";
        }
    }   
    
    $oldHandler = file_get_contents("backup.".md5("../modules/main/main_handler.php"));
    if (strlen($oldHandler) < 0) {
    	$warnings .= "Unable to find global handler backup.";
    } else {
    	$newHandler = file_get_contents("../modules/main/main_handler.php");
    	$startPos = strpos($newHandler, "/** BEGIN_CODE **/") + strlen("/** BEGIN_CODE **/");
    	if ($startPos - strlen("/** BEGIN_CODE **/") <= 0) {
    		die("Error finding global home handler start!");
    	}
    	$endPos = strpos($newHandler, "/** END_CODE **/");
    	if ($endPos === false) {
    		die("Error finding global home handler end!");
    	}
    	$newPre = substr($newHandler, 0, $startPos);
    	$newPost = substr($newHandler, $endPos);
    	$oStartPos = strpos($oldHandler, "/** BEGIN_CODE **/") + strlen("/** BEGIN_CODE **/");
    	$oEndPos = strpos($oldHandler, "/** END_CODE **/");
    	$oldContent = substr($oldHandler, $oStartPos, $oEndPos - $oStartPos);
    	$mergedHandler = $newPre . $oldContent . $newPost;
    	if (!@file_put_contents("../modules/main/main_handler.php", $mergedHandler)) {
    		$warnings .= "Unable to update global handler code<br/>";
    	} else {
    		exec("php -l ../modules/main/main_handler.php", $error, $code);
    		if ($code != 0) {
    			@file_put_contents("../modules/main/main_handler.php", $newHandler);
    			$warnings .= "Error while merging global home handler. ";
    			$warnings .= "It has been reset to installation default. Original handler code is saved in ".$dir."/backup.".md5("../modules/main/main_handler.php")."<br/>";
    		}
    	}    	
    }

	die("success\n?event=builder:updateSystem&empty=cache&warnings=".urlencode($warnings)."\nRe-deploying database and cleaning caches...");
}

?>