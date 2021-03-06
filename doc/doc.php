#!/usr/bin/env php -q
<?php
$currentContext = null;
$file = "";
$path = "doc/html/";
$bulletCreated = false;
$sections = Array();
$inCodeBlock = false;
$fileHistory = Array();

if (!file_exists($path)) {
	mkdir($path, 0755, true);
}

function findFiles($path, $allowedSuffixes) {
	if (is_dir($path)) {
		$dp = opendir($path);
		while (($file = readdir($dp)) !== false) {
			if ($file[0] != "." && !$fileHistory[$file]) {
				$fileHistory[$file] = true;
				$suffix = array_pop(explode(".", $file));
				if (is_dir($path."/".$file)) {
					findFiles($path.'/'.$file, $allowedSuffixes);
				} else if (strpos($file, ".") !== false && in_array($suffix, $allowedSuffixes)) {
					handleFile($path."/".$file);
				}
			}
		}
	}
}

function handleFile($afile) {
	global $file;
	$file = "";
	$content = file_get_contents($afile);
	if (strlen($content) > 0) {
		$end = 0;
		$matches = Array();
		while (($start = strpos($content, "/**", $end)) !== false) {
			$end = strpos($content, "*/", $start);
			if ($content[$end - 1] == "*" && strpos(substr($content, $start + 3, ($end - ($start + 3))), "\n") !== false) {
				array_push($matches, substr($content, $start + 3, $end - ($start + 3)));
			}
		}
		foreach ($matches as $match) {
			$lines = explode("\n", $match);
			$cleaned = Array();
			foreach ($lines as $line) {
				array_push($cleaned, preg_replace('/^\\s*\\*\\s?/', '', $line));
			}
			handleCommentBlock($cleaned);
		}
	}
}

function handleCommentBlock($items) {
	global $file, $bulletCreated, $inCodeBlock;
	$currentMethod = "";
	$bulletCreated = false;
	$class = '/^\\s*([Cc]lass|[sS]ection)\\s+(\\w+)/';
	$method = '/(\\w+)\\s*\\(([^\\)]*)\\)\\s*->\\s*([a-zA-Z0-9\\.]+(\\|[a-zA-Z0-9\\.]+)*)/';
	$constructor = '/new\s+(\\w+)\\s*\\(([^\\)]*)\\)/';
	$tag = '/<c:(\\w+)(.*)$/';
	$param = '/\\s*-\\s*(\\$\\w+|`[^`]+`)\\s*\\(([a-zA-Z0-9\\.]+(\\|[a-zA-Z0-9\\.]+)*)\\)\\s*-\\s*(.*)/';
	foreach ($items as $item) {
		if (!$inCodeBlock && preg_match($class, $item, $matches)) {
			writeHeader($matches[1], $matches[2]);
		} else if (!$inCodeBlock && preg_match($method, $item, $matches)) {
			writeMethod($matches);
			$currentMethod = $matches[1];
		} else if (!$inCodeBlock && preg_match($constructor, $item, $matches)) {
			writeMethod($matches, "Constructor");
			$currentMethod = $matches[1];
		} else if (!$inCodeBlock && preg_match($tag, $item, $matches)) {
			writeMethod($matches, "Tag");
			$currentMethod = $matches[1];
		} else if (!$inCodeBlock && preg_match($param, $item, $matches)) {
			writeParam($matches, $currentMethod);
		} else {
			writeDescription($item);
		}
	}
}

function writeHeader($type, $title) {
	global $file, $path, $bulletCreated, $sections, $currentContext;
	$wasEmpty = empty($file);
	$bulletCreated = false;
	$file = preg_replace('/[^a-zA-Z0-9\\-]/', '', $title).".html";
	$css = "styles.css";
	$type = strtolower($type);
	$secFound = false;
	foreach ($sections as $key => $sec) {
		if ($sec["file"] == $file) {
			$currentContext = $key;
			$secFound = true;
			break;
		}
	}
	if (!$secFound) { @unlink($path.$file); }
	switch ($type) {
		case "class": $title = "Class ".$title; break;
		case "section": break;
	} 	
	if (!file_exists($path.$file)) {
		$content = <<<ENDL
<html>
	<head>
		<title>{$title}</title>
		<link rel="stylesheet" type="text/css" href="{$css}"/>
	</head>
	<body><div class="page">
		<h1>{$title}</h1>
ENDL;
		if (!$secFound) {
			array_push($sections, Array("file" => $file, "title" => $title, "methods" => Array()));
			$currentContext = count($sections) - 1;
		}
	} else {
		$content = file_get_contents($path.$file);
	}
	echo $path.$file."\n";
	file_put_contents($path.$file, $content);
}

function writeMethod($details, $type = "Method") {
	global $file, $path, $bulletCreated, $currentContext, $sections;
	if (empty($file)) {
		throw new Exception("Section or class not specified.");
		die();
	}
	$bulletCreated = false;
	$content = file_get_contents($path.$file);
	$parts = explode("|", $details[3]);
	foreach ($parts as &$part) {
		if (file_exists($path.$part.".html")) {
			$part = "<a href='".$part.".html'>$part</a>";
		}
	}
	$added = false;
	if ($currentContext != null) {
		$name = $details[1];	
		if ($type == "Constructor") {
			$name = "Constructor";
		}
		if (!in_array($name, $sections[$currentContext]["methods"])) {
			$sections[$currentContext]["methods"][] = $name;
			$added = true;
		}
	}
	$details[3] = implode("|", $parts);
	$types = str_replace('|', "</span> | <span class='type'>", $details[3]);
	$name = preg_replace('/[^a-zA-Z0-9_]/', '_', $details[1]);
	if ($added) {
		$content .= "<div class='method-type'>".$type."</div><a name='".$name."' class='method-title'>".$details[1]."</a>";
	}
	if ($type == "Tag") {
		$content .= "<div class='method'>&lt;c:<span class='name'>{$details[1]}</span> ".str_replace(Array('<', '>'), Array('&lt;', '&gt;'), $details[2])."</div>\n";
	} else if ($type == "Constructor") {
		$content .= "<div class='method'><span class='name'>new {$details[1]}</span>(<span class='parameters'>{$details[2]}</span>)</div>\n";
	} else {
		$content .= "<div class='method'><span class='name'>{$details[1]}</span>(<span class='parameters'>{$details[2]}</span>) &rarr; <span class='type'>{$types}</span></div>\n";
	}
	
	file_put_contents($path.$file, $content);
} 

function writeParam($details) {
	global $file, $path, $bulletCreated;
	if (empty($file)) {
		throw new Exception("Section or class not specified.");
		die();
	}
	$content = file_get_contents($path.$file);
	$parts = explode("|", $details[2]);
	foreach ($parts as &$part) {
		if (file_exists($path.$part.".html")) {
			$part = "<a href='".$part.".html'>$part</a>";
		}
	}
	$details[2] = implode("|", $parts);
	$types = str_replace('|', "</span> | <span class='type'>", $details[2]);
	if (!$bulletCreated) {
		$content .= "<ul>";
		$bulletCreated = true;
	}
	$content .= "<li class='param'><code>".preg_replace('/`([^`]+)`/', '\\1', $details[1])."</code> (<span class='type'>".$types."</span>)<span class='divider'> - </span>".parseWikiCode($details[4], true)."</li>\n";
	file_put_contents($path.$file, $content);
}

function writeDescription($desc) {
	global $file, $path, $bulletCreated;
	if (empty($file)) {
		throw new Exception("Section or class not specified.");
		die();
	}
	$content = file_get_contents($path.$file);
	$desc = parseWikiCode($desc);
	if (strlen(preg_replace('/\\s*/', '', $desc)) <= 0) {
		if (!$bulletCreated) { $desc .= "<br/><br/>"; }
	}
	if ($bulletCreated) {
		$content .= "</ul>\n";
		$bulletCreated = false;
	}
	$content .= $desc."\n";
	file_put_contents($path.$file, $content);
}

function parseWikiCode($desc, $ignoreBullets = false) {
	global $inCodeBlock, $path;
	if ($inCodeBlock) {
		$desc = str_replace(Array('<', '>'), Array('&lt;', '&gt;'), $desc);
	}
	$desc = preg_replace('/\\[([^\\]]+)\\]((\\w+[\\-\\.]\\w+)|\\w+)/', '<a href="\\1.html#\\2">\\2</a>', preg_replace('/`([^`]+)`/', '<code>\\1</code>', $desc));
	$desc = preg_replace('/\\*([^*]+)\\*/', '<strong>\\1</strong>', $desc);
	$desc = preg_replace('/\\s*_([^_]+)_\\s/', ' <u>\\1</u> ', $desc);
	$desc = preg_replace('/\\!\\[([^\\]]+)\\]\\(([^\\)]+)\\)/', '<a href="\\2" class="dialog image" title="\\1"><img src="\\2" alt="\\1" border="0"/><span class="caption">\\1</span></a>', $desc);
	$desc = preg_replace('/!\\(([^\\) ]+)(?: ([^\\)]*))?\\)/', '<a href="\\1" target="_blank">\\2</a>', $desc);
	$desc = preg_replace('/####\\s+(.*)$/m', '<h4>\\1</h4>', $desc);
	$desc = preg_replace('/###\\s+(.*)$/m', '<h3>\\1</h3>', $desc);
	$desc = preg_replace('/##\\s+(.*)$/m', '<h2>\\1</h2>', $desc);
	$desc = preg_replace('/#\\s+(.*)$/m', '<h1>\\1</h1>', $desc);
	if (!$ignoreBullets) {
		$desc = preg_replace('/^\\s*-\\s+(.+)$/', '<li>\\1</li>', $desc);
	}
	if (preg_match('/\\{\\{\\{/', $desc)) {
		$inCodeBlock = true;
	} else if (preg_match('/\\}\\}\\}/', $desc)) {
		$inCodeBlock = false;
	}
	$desc = preg_replace('/\\{\\{\\{/', '<pre>', $desc);
	$desc = preg_replace('/\\}\\}\\}/', '</pre>', $desc);

	return $desc;
}

findFiles(".", Array("php", "tag", "js"));
$menu = "<ul class='navigation'>";
$finishedList = Array();
foreach ($sections as $sec) {
	if (in_array($sec["file"], $finishedList)) continue;
	array_push($finishedList, $sec["file"]);
	$menu .= "<li><a href='".$sec["file"]."'>".$sec["title"]."</a>";
	if (is_array($sec["methods"]) && count($sec["methods"]) > 0) {
		$menu .= "<ul>";
		foreach ($sec["methods"] as $meth) {
			$menu .= "<li><a href='".$sec['file']."#".preg_replace('/[^a-zA-Z0-9_]/', '_', $meth)."'>".$meth."</a></li>";
		}
		$menu .= "</ul>";
	}
	$menu .= "</li>";
}
$menu .= "</ul>";
file_put_contents($path."index.html", $menu);
?>
