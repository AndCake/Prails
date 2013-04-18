<?php
class SnowCompiler {
	const VERSION = '0.0.3';

	protected $ebnf = '
{
	"T_NULL": "null\\\\b",
	"T_INDENT": "(\\\\s{4}|\\\\t)+",
	"T_COMMENT": {"|": ["<T_MULTILINE_COMMENT>", "<T_SINGLELINE_COMMENT>"]},
	"T_CLASS": ["class\\\\s+", "<T_CLASS_IDENTIFIER>", {"*": ["\\\\s+(extends|implements)\\\\s+", "<T_CLASS_IDENTIFIER>", {"*": [",\\\\s*", "<T_CLASS_IDENTIFIER>"]}]}, "<T_CLASS_BODY>"],
	"T_MULTILINE_COMMENT": "###([^#]|#[^#]|##[^#])+###",
	"T_SINGLELINE_COMMENT": "#([^\\n]*)",
	"T_INCDEC": ["<T_IDENTIFIER>", "\\\\s*(\\\\+\\\\+)|(--)"],
	"T_INDENTED_EXPRESSIONS": {"+": ["<T_NEWLINE>", "<T_INDENT>", "<T_EXPRESSION>"]},
	"T_CLASS_BODY": {"+": ["[ ]*[\\r\\n]+", "<T_INDENT>", {"|":["<T_FN_DEF>", "<T_COMMENT>"]}]},
	"T_EXPRESSIONS": {"+": ["<T_EXPRESSION>", "<T_NEWLINE>"]},
	"T_EXPRESSION": {"|": ["<T_FN_DEF>", "<T_IF>", "<T_LOOP>", "<T_CLASS>", "<T_COMMENT>", "<T_LOOP_CONTROL>", "<T_TRY_CATCH>", "<T_SIMPLE_EXPRESSION>"]},
	"T_LOOP_CONTROL": "continue|break",
	"T_TRY_CATCH": ["try", "<T_INDENTED_EXPRESSIONS>", "<T_NEWLINE>", "catch[ ]+", "<T_IDENTIFIER>", "<T_INDENTED_EXPRESSIONS>", {"?": ["<T_NEWLINE>", "finally", "<T_INDENTED_EXPRESSIONS>"]}],
	"T_FN_DEF": ["fn\\\\s+", {"?": "<T_FNNAME>"}, {"?": ["\\\\s*\\\\(\\\\s*", "<T_PARAMETERS>", "\\\\s*\\\\)"]}, {"|": ["<T_INDENTED_EXPRESSIONS>", "<T_RETURN>"]}], 
	"T_SIMPLE_EXPRESSION": {"|": ["<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_PCONDITION>", "<T_FNCALL>", "<T_FNCSCALL>", "<T_RETURN>", "<T_IDENTIFIER>", "<T_LITERAL>", "<T_CONST_DEF>", "<T_CONST>"]},
	"T_CONDITION_EXPRESSION": {"|": ["<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_FNCALL>", "<T_LITERAL>", "<T_IDENTIFIER>", "<T_CONST>"]},
	"T_CHAIN_EXPRESSION": {"|": ["<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_PCONDITION>", "<T_FNCALL>", "<T_LITERAL>", "<T_IDENTIFIER>", "<T_CONST>"]},
	"T_ASSIGNMENT": ["<T_IDENTIFIER>", "\\\\s*[\\\\+\\\\-\\\\*/\\\\%]?=\\\\s*", "<T_SIMPLE_EXPRESSION>"],
	"T_RETURN": ["[ ]*<-\\\\s*", "<T_SIMPLE_EXPRESSION>"],
	"T_FNCALL": {"|": ["<T_FNDOCALL>", "<T_FNPLAINCALL>", "<T_FN_CHAINCALL>"]},
	"T_FNDOCALL": ["do\\\\s+", "<T_FNNAME>"],
	"T_FNCSCALL": ["<T_FNNAME>", "[ ]+", "<T_FN_PARAMETERS1>"],
	"T_FNPLAINCALL": ["(new\\\\s+)?", "<T_FNNAME>", "\\\\s*\\\\(\\\\s*", "<T_FN_PARAMETERS>", "\\\\s*\\\\)"],
	"T_FN_CHAINCALL": ["<T_CHAIN_EXPRESSION>", {"+": ["->", "<T_FNNAME>", "\\\\s*\\\\(\\\\s*", "<T_FN_PARAMETERS>", "\\\\s*\\\\)"]}],
	"T_FN_PARAMETERS1": ["<T_SIMPLE_EXPRESSION>", {"*": ["\\\\s*,\\\\s*", "<T_SIMPLE_EXPRESSION>"]}],
	"T_FN_PARAMETERS": {"?": ["<T_SIMPLE_EXPRESSION>", {"*": ["\\\\s*,\\\\s*", "<T_SIMPLE_EXPRESSION>"]}]},
	"T_CONST_DEF": ["<T_CONST>", "\\\\s*=\\\\s*", "<T_SIMPLE_EXPRESSION>"],
	"T_CONST": ["!", "<T_UPPERCASE_IDENTIFIER>"],
	"T_LOOP": {"|": ["<T_FOR_LOOP>", "<T_FOR_COUNT_UP_LOOP>", "<T_FOR_COUNT_DOWN_LOOP>", "<T_WHILE>"]},
	"T_FOR_COUNT_UP_LOOP": ["for\\\\s+", "<T_IDENTIFIER>", "\\\\s+in\\\\s+", "<T_CONDITION_EXPRESSION>", "\\\\s*to\\\\s+", "<T_CONDITION_EXPRESSION>", {"?": ["\\\\s+step\\\\s+", "<T_NUMBER_LITERAL>"]}, "<T_INDENTED_EXPRESSIONS>"],
	"T_FOR_COUNT_DOWN_LOOP": ["for\\\\s+", "<T_IDENTIFIER>", "\\\\s+in\\\\s+", "<T_CONDITION_EXPRESSION>", "\\\\s*downto\\\\s+", "<T_CONDITION_EXPRESSION>", {"?": ["\\\\s+step\\\\s+", "<T_NUMBER_LITERAL>"]}, "<T_INDENTED_EXPRESSIONS>"],
	"T_FOR_LOOP": ["for\\\\s+", "<T_IDENTIFIER>", {"?": [", ", "<T_IDENTIFIER>"]}, "\\\\s+in\\\\s+", "<T_IDENTIFIER>", "<T_INDENTED_EXPRESSIONS>"],
	"T_FNNAME": "(?!fn\\\\b|for\\\\b|if\\\\b|try\\\\b|catch\\\\b|finally\\\\b|class\\\\b|null\\\\b|true\\\\b|false\\\\b|do\\\\b|else\\\\b|elif\\\\b|while\\\\b|downto\\\\b)(@?)_*[A-Za-z][_a-zA-Z0-9.]*",
	"T_IF": ["if\\\\s+", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>", {"*": ["<T_ELIF>"]}, {"?": ["<T_ELSE>"]}],
	"T_ELSE": ["\\\\s*else[ ]*", "<T_INDENTED_EXPRESSIONS>"],
	"T_ELIF": ["\\\\s+elif\\\\s+", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>"],
	"T_IF_THEN": ["if\\\\s+", "<T_PCONDITION>", "\\\\s+then\\\\s+", "<T_SIMPLE_EXPRESSION>", {"?": ["\\\\s+else\\\\s+", "<T_SIMPLE_EXPRESSION>"]}],
	"T_PCONDITION": {"|": [["\\\\s*\\\\(\\\\s*", "<T_CONDITION>", "\\\\s*\\\\)"], "<T_CONDITION>"]},
	"T_CONDITION": ["<T_CONDITION_PART>", {"*": ["<T_BOOL_OP>", "<T_CONDITION_PART>"]}],
	"T_CONDITION_PART": {"|": ["<T_PCOMPARISON>", [{"?": ["<T_BOOL_NEGATION>"]}, {"|": ["<T_EMPTY>", "<T_EXISTS>", "<T_SIMPLE_EXPRESSION>"]}]]},
	"T_EMPTY": [{"|": ["<T_IDENTIFIER>", "<T_CONST>"]}, "\\\\?\\\\?"],
	"T_EXISTS": [{"|": ["<T_IDENTIFIER>", "<T_CONST>"]}, "\\\\?"],
	"T_WHILE": ["while\\\\s+", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>"],
	"T_PCOMPARISON": {"|": [["\\\\s*\\\\(\\\\s*", "<T_COMPARISON>", "\\\\s*\\\\)"], "<T_COMPARISON>"]},
	"T_COMPARISON": {"|": ["<T_EQUALS_COMPARISON>", "<T_NEQUALS_COMPARISON>", "<T_GT_COMPARISON>", "<T_LT_COMPARISON>"]},
	"T_EQUALS_COMPARISON": ["<T_CONDITION_EXPRESSION>", "\\\\s+(is|==)\\\\s+", "<T_CONDITION_EXPRESSION>"],
	"T_NEQUALS_COMPARISON": ["<T_CONDITION_EXPRESSION>", "\\\\s+(isnt|!=)\\\\s+", "<T_CONDITION_EXPRESSION>"],
	"T_GT_COMPARISON": ["<T_CONDITION_EXPRESSION>", {"|": ["<T_GT>", "<T_GTE>"]}, "<T_CONDITION_EXPRESSION>"],
	"T_LT_COMPARISON": ["<T_CONDITION_EXPRESSION>", {"|": ["<T_LTE>", "<T_LT>"]}, "<T_CONDITION_EXPRESSION>"],
	"T_PARAMETERS": ["<T_PARAMETER>", {"*": ["\\\\s*,\\\\s*", "<T_PARAMETER>"]}],
	"T_PARAMETER": ["<T_IDENTIFIER>", {"?": ["\\\\s*=\\\\s*", "<T_LITERAL>"]}],
	"T_LITERAL": {"|": ["<T_REGEXP_LITERAL>", "<T_ARRAY_LITERAL>", "<T_BOOLEAN_LITERAL>", "<T_NULL>", "<T_STRING_LITERAL>", "<T_NUMBER_LITERAL>"]},
	"T_ARRAY_LITERAL": ["\\\\s*\\\\[\\\\s*", {"*": [{"|": ["<T_KEYVALUE_PAIR>", "<T_CONDITION_EXPRESSION>"]}, "\\\\s*[,]?\\\\s*"]}, "\\\\s*\\\\]"],
	"T_KEYVALUE_PAIR": ["<T_LITERAL>", "\\\\s*:\\\\s*", "<T_CONDITION_EXPRESSION>"],
	"T_STRING_LITERAL": {"|": ["<T_STRING_LITERAL_UQUOTE>", "<T_STRING_LITERAL_TQUOTE>", "<T_STRING_LITERAL_DQUOTE>"]},
	"T_IDENTIFIER": ["(?!fn\\\\b|for\\\\b|if\\\\b|try\\\\b|catch\\\\b|finally\\\\b|class\\\\b|null\\\\b|true\\\\b|false\\\\b|do\\\\b|else\\\\b|elif\\\\b|while\\\\b|downto\\\\b)(@?)_*[a-zA-Z]([_a-zA-Z0-9.]*)", {"*": ["\\\\[", "<T_CONDITION_EXPRESSION>", "\\\\]"]}],
	"T_UPPERCASE_IDENTIFIER": "_*[A-Z_]+",
	"T_CLASS_IDENTIFIER": "_*[A-Z][a-zA-Z0-9]*",
	"T_OPERATION": {"|": ["<T_COMPLEX_OPERATION>", "<T_COMPLEX_STRING_OPERATION>", "<T_INCDEC>"]},
	"T_COMPLEX_OPERATION": [{"|": ["<T_FNCALL>", "<T_NUMBER_LITERAL>", "<T_IDENTIFIER>"]}, {"+": ["<T_OPERATOR>", {"|": ["<T_FNCALL>", "<T_NUMBER_LITERAL>", "<T_IDENTIFIER>"]}]}],
	"T_COMPLEX_STRING_OPERATION": [{"|": ["<T_FNCALL>", "<T_STRING_LITERAL>", "<T_IDENTIFIER>"]}, {"+": ["\\\\s*[\\\\+%&]\\\\s*", {"|": ["<T_FNCALL>", "<T_STRING_LITERAL>", "<T_IDENTIFIER>"]}]}],
	"T_OPERATOR": "\\\\s*[\\\\-\\\\+\\\\*/]\\\\s*",
	"T_STRING_LITERAL_UQUOTE": "\'([^\']*)\'",
	"T_STRING_LITERAL_DQUOTE": "\\"([^\\"]*)\\"",
	"T_STRING_LITERAL_TQUOTE": ["\\"\\"\\"", "([^\\"]|\\"[^\\"]|\\"\\"[^\\"])+", "\\"\\"\\""],
	"T_BOOL_OP": {"|": ["<T_BOOL_AND>", "<T_BOOL_OR>"]},
	"T_BOOL_AND": "\\\\s+and\\\\s+",
	"T_BOOL_OR": "\\\\s+or\\\\s+",
	"T_BOOL_NEGATION": "\\\\s*not\\\\s+",
	"T_BOOLEAN_LITERAL": "true|false",
	"T_NUMBER_LITERAL": [{"|": ["<T_HEX_NUMBER>", "<T_OCT_NUMBER>", "<T_FLOAT_NUMBER>", "<T_DEC_NUMBER>"]}],
	"T_HEX_NUMBER": "(0x[0-9A-Fa-f]+)",
	"T_OCT_NUMBER": "(0[0-7]+[1L]?)",
	"T_FLOAT_NUMBER": "(-?[0-9]*\\\\.[0-9]+)",
	"T_DEC_NUMBER": "(-?[0-9]+)",
	"T_REGEXP_LITERAL": "/([^/]+)/[imsxADSUXJu]*",
	"T_NEWLINE": "[ \\t]*[\\r\\n]+|\\\\s*$",
	"T_GTE": "\\\\s*>=\\\\s*",
	"T_LTE": "\\\\s*<=\\\\s*",
	"T_GT": "\\\\s*>\\\\s*",
	"T_LT": "\\\\s*<\\\\s*"
}';
	protected $mapRules = '';
# ${c} - special command: create recursive chain
# \x.y - look in tree at position x and in x look at position y
# ${\x?a/b} - if \x is not empty, replace this expression with a, else with b (either one can be left empty)
# ${R\x/a/b} - replace a in \x with b
# ${E\x/a/b} - evaluate/compile everything from \x that is in the first match group of a and replace it with b
	protected $language = null;
	protected $mapping = null;
	protected $code = null;
	protected $stack = null;
	protected $successStack = null;
	protected $indentationLevel = 0;

	function __construct($code) {
		$this->mapRules = '{
	"T_IF": "if (\\\\2) {\\\\3;\\n}\\\\4\\\\5\\n",
	"T_NEWLINE": ";\\n",
	"T_TRY_CATCH": "try {\\\\2;\\n} catch (Exception \\\\5) {'.(PHP_VERSION_ID >= 50500 ? '' : '\\n\\t$catchGuard = true\\\\7.3').'\\\\6;\\n}'.(PHP_VERSION_ID >= 50500 ? '${\\\\7.3? finally {\\\\7.3;\\n}/}' : '\\nif(!isset($catchGuard)) {\\\\7.3;\\n} else {\\n\\tunset($catchGuard);\\n}\\n').'",
	"T_CLASS": "class \\\\2\\\\3 {\\\\4\\n}",
	"T_MULTILINE_COMMENT": "/*${R\\\\1/#/}*/",
	"T_SINGLELINE_COMMENT": "//${R\\\\1/#/}",
	"T_BOOL_AND": "&&",
	"T_BOOL_OR": "||",
	"T_BOOL_NEGATION": "!",
	"T_INCDEC": "\\\\1\\\\2",
	"T_LOOP_CONTROL": "\\\\1",
	"T_COMPLEX_OPERATION": "\\\\1\\\\2",
	"T_COMPLEX_STRING_OPERATION": "\\\\1 . \\\\2.2",
	"T_EXISTS": "${\\\\1.2?defined(\'\\\\1\')/isset(\\\\1)}",
	"T_EMPTY": "(${\\\\1.2?defined(\'\\\\1\') && strlen(\\\\1) > 0/isset(\\\\1) && !empty(\\\\1)})",
	"T_FN_DEF": "function \\\\2(\\\\3.2) {\\\\4;}",
	"T_EQUALS_COMPARISON": "\\\\1 === \\\\3",
	"T_NEQUALS_COMPARISON": "\\\\1 !== \\\\3",
	"T_GT_COMPARISON": "(gettype($_tmp1 = \\\\1) === gettype($_tmp2 = \\\\3) && ($_tmp1 \\\\2 $_tmp2 && (($_tmp1 = $_tmp2 = null) || true)) || ($_tmp1 = $_tmp2 = null))",
	"T_LT_COMPARISON": "(gettype($_tmp1 = \\\\1) === gettype($_tmp2 = \\\\3) && ($_tmp1 \\\\2 $_tmp2 && (($_tmp1 = $_tmp2 = null) || true)) || ($_tmp1 = $_tmp2 = null))",
	"T_IDENTIFIER": "${\\\\1~=(^(true|false|null)$)|\\\\.\\\\.?/$}${R\\\\1/\\\\.\\\\./::$}${R\\\\1/@(.+)/this->\\\\1}${R\\\\1/\\\\./->}\\\\2",
	"T_CONST_DEF": "define(\\"\\\\1\\", \\\\3)",
	"T_CONST": "\\\\2",
	"T_ELSE": " else {\\\\2;\\n}",
	"T_ELIF": " else if (\\\\2) {\\\\3;\\n}",
	"T_FOR_LOOP": "foreach (\\\\5 as ${\\\\3.2?\\\\3.2 => /}\\\\2) {\\\\6;\\n}\\nunset(\\\\2${\\\\3.2?, \\\\3.2/});\\n",
	"T_FOR_COUNT_UP_LOOP": "for (\\\\2 = \\\\4; \\\\2 <= \\\\6; \\\\2 += ${\\\\7.2?\\\\7.2/1}) {\\\\8;\\n}\\nunset(\\\\2);\\n",
	"T_FOR_COUNT_DOWN_LOOP": "for (\\\\2 = \\\\4; \\\\2 >= \\\\6; \\\\2 -= ${\\\\7.2?\\\\7.2/1}) {\\\\8;\\n}\\nunset(\\\\2);\\n",
	"T_IF_THEN": "(\\\\2 ? \\\\4 : ${\\\\5.2?\\\\5.2/null})",
	"T_ARRAY_LITERAL": "Array(\\\\2)",
	"T_KEYVALUE_PAIR": "\\\\1 => \\\\3",
	"T_NUMBER_LITERAL": "\\\\1",
	"T_BOOLEAN_LITERAL": "\\\\1",
	"T_REGEXP_LITERAL": "\'\\\\1\'",
	"T_WHILE": "while (\\\\2) {\\\\3;}\\n",
	"T_FNPLAINCALL": "\\\\1${R\\\\2/^@/$this->}${R\\\\2/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\2/^(\\\\w+)\\\\./$\\\\1->}(\\\\4)",
	"T_FNCSCALL": "${R\\\\1/^@/$this->}${R\\\\1/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\1/^(\\\\w+)\\\\./$\\\\1->}(\\\\3)",
	"T_FNDOCALL": "${R\\\\2/^@/$this->}${R\\\\2/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\2/^(\\\\w+)\\\\./$\\\\1->}();\\n",
	"T_ASSIGNMENT": "\\\\1 \\\\2 \\\\3",
	"T_RETURN": "return \\\\2;\\n",
	"T_STRING_LITERAL_UQUOTE": "\\\\1",
	"T_STRING_LITERAL_DQUOTE": "${E\\\\1/\\\\{([^}]+)\\\\}/\" . (\\\\1) . \"}",
	"T_STRING_LITERAL_TQUOTE": "<<<EOF\\n${E\\\\2/\\\\{([^}]+)\\\\}/\\\\1}\\nEOF",
	"T_FN_CHAINCALL": "${c}"
}';
		$this->language = json_decode($this->ebnf, true);
		$this->mapping = json_decode($this->mapRules, true);
		$this->code = $code;
		$this->stack = Array();
		$this->indentationLevel = 0;
		$this->successStack = Array();
	}

	function compile($debug = false) {
		$result = "";
		if ($tree = $this->checkRuleByName("T_EXPRESSIONS", 0, $debug)) {
			if ($tree["len"] < strlen($this->code)) {
				$lines = explode("\n", $this->code);
				$line = count(explode("\n", substr($this->code, $tree["len"])));
				throw new Exception("Error at line ".$line." while parsing input: \"".$lines[$line - 1]."\"");
			}
			$result = $this->doMapping($tree);
			unset($tree);
		} else {
			throw new Exception("Unable to parse input: given input is no T_EXPRESSIONS.");
		}

		return $result;
	}

	function buildChain($chain, $root, $template) {
		$slice = array_splice($chain, count($chain) - 5, 5);
		$template = $this->getValue($slice[1])."(";
		if (count($chain) > 0) {
			$template .= $this->buildChain($chain, $root, $template);
		} else {
			$template .= $root;
		}
		$params = $this->getValue($slice[3]);
		if (!empty($params)) {
			$template .= ", ".$params;
		}
		$template .= ")";
		return $template;
	}

	function parseMapping($template, $tree) {
		$replacements = Array();
		preg_match('/\\$\\{c\\}/m', $template, $chain);
		if (count($chain) > 0) {
			$template = $this->buildChain($tree[1], $this->getValue($tree[0]), $template);
			return $template;
		}

		preg_match_all('/\\\\([1-9][0-9.]*)/m', $template, $matches);
		foreach ($matches[1] as $key => $match) {
			$parts = explode(".", $match);
			$oldValue = $tree;
			for ($i = 0; $i < count($parts) - 1; $i++) {
				$tree = $tree[intval($parts[$i]) - 1];
			}
			$val = $this->getValue($tree, intval($parts[$i]) - 1);
			$replacements[0][$matches[0][$key]] = $val;
			$tree = $oldValue;
		}

		preg_match_all('/\\$\\{\\\\([1-9][0-9.]*)(~=[^\\?]+)?\\?([^\\/]*)\\/([^}]*)\\}/m', $template, $ifMatches);
		preg_match_all('/\\$\\{R\\\\([1-9][0-9]*)\\/([^\\/]*)\\/([^}]*)\\}/m', $template, $replaceMatches);
		preg_match_all('/\\$\\{E\\\\([1-9][0-9]*)\\/([^\\/]*)\\/([^}]*)\\}/m', $template, $evalMatches);
		$result = "";
		if (count($replacements) <= 0) $result = $template;
		foreach ($replacements as $repl) {
			$resultTemplate = $template;
			foreach ($ifMatches[1] as $key => $match) {
				$cond = !empty($repl["\\".$match]);
				if (!empty($ifMatches[2][$key])) {
					$ifMatches[2][$key] = str_replace('~=', '', $ifMatches[2][$key]);
					$cond = preg_match("/".$ifMatches[2][$key]."/", $repl['\\'.$match]);
				}
				if ($cond != false) {
					$resultTemplate = str_replace($ifMatches[0][$key], $ifMatches[3][$key], $resultTemplate);
				} else {
					$resultTemplate = str_replace($ifMatches[0][$key], $ifMatches[4][$key], $resultTemplate);
				}				
			}
			$replaced = Array();
			foreach ($replaceMatches[1] as $key => $match) {
				$repl["\\".$match] = preg_replace('/'.$replaceMatches[2][$key].'/m', $replaceMatches[3][$key], $repl["\\".$match]);
				if (!in_array($match, $replaced)) {
					$resultTemplate = str_replace($replaceMatches[0][$key], "\\".$match, $resultTemplate);
					$replaced[] = $match;
				} else {
					$resultTemplate = str_replace($replaceMatches[0][$key], "", $resultTemplate);
				}
			}
			foreach ($evalMatches[1] as $key => $match) {
				preg_match_all('/'.$evalMatches[2][$key].'/m', $repl["\\".$match], $evals);
				if (is_array($evals[1])) {
					foreach ($evals[1] as $k => $m) {
						$sn = new SnowCompiler($m);
						$repl["\\".$match] = str_replace($evals[0][$k], str_replace('\\1', $sn->compile(), $evalMatches[3][$key]), $repl['\\'.$match]);
					}
				}
				$resultTemplate = str_replace($evalMatches[0][$key], "\\".$match, $resultTemplate);
			}
			# need to somehow make it also take the recurse command into consideration...
			foreach ($repl as $key => $re) {
				# probably only needs to take the sub string into account that comes after the <Rx>
				$resultTemplate = str_replace($key, $re, $resultTemplate);
			}
			$result = $resultTemplate; 
		}
		return $result;
	}

	function doMapping($tree, $name = "") {
		$result = "";
		if ($this->mapping[$name]) {
			$result .= $this->parseMapping($this->mapping[$name], $tree);
		} else {
			foreach ($tree as $nodeName => $value) {
				if ($this->mapping[$nodeName]) {
					$result .= $this->parseMapping($this->mapping[$nodeName], $value);
				} else {
					if (is_array($value)) {
						$result .= $this->doMapping($value, $nodeName);
					}
				}
			}
		}
		return $result;
	}

	function getValue($ruleArray, $pos = -1) {
		# go down until I find a match
		$matches = "";
		if (isset($ruleArray["match"])) {
			$matches .= $ruleArray["match"];
		} else {
			if ($pos != -1) {
				$ruleArray = $ruleArray[$pos];
				if ($ruleArray["match"]) {
					$matches .= $ruleArray["match"];
					return $matches;
				}
			}
			if (is_array($ruleArray)) {
				foreach ($ruleArray as $name => $rule) {
					if ($this->mapping[$name]) {
						$matches .= $this->doMapping($rule, $name);
					} else {
						if ($rule["match"]) {
							$matches .= $rule["match"];
						} else {
							$matches .= $this->getValue($rule);
						}
					}
				}
			}
		}
		return $matches;
	}

	function checkRuleByName($ruleName, $pos = 0, $debug = false, $depth = 0) {
		if ($rule = $this->language[$ruleName]) {
			if (is_array($this->stack[$pos]) && in_array($ruleName, $this->stack[$pos])) {
				return false;
			}
			if ($this->successStack[$pos][0] == $ruleName) return $this->successStack[$pos][1];
			if ($debug) echo str_repeat("\t", $depth)."checking rule ".$ruleName." at pos ".$pos."\n";
			$this->stack[$pos][] = $ruleName;
#	 		if ($debug) var_dump($this->stack);
 			$result = $this->checkRule($rule, $pos, (gettype($debug) != "string" ? $debug : false) || ($ruleName == (gettype($debug) == "string" ? $debug : "")), $depth);
			if ($ruleName === 'T_INDENT') {
				$cline = substr($this->code, $pos, strpos($this->code, "\n", $pos) - $pos);
				preg_match_all("/(\\s{4}|\t)/", $cline, $matches);
				if ($debug) echo str_repeat("\t", $depth)."line: ".$cline.";indent matches: ".json_encode($matches)."; indent level: ".$this->indentationLevel."\n";
				$indentDepth = count($matches[1]);
				if ($indentDepth < $this->indentationLevel) {
					$this->stack[$pos] = array_diff($this->stack[$pos], Array($ruleName));
					$result = false;
				}
				$this->indentationLevel = $indentDepth;
			}
			if ($result != false) {
				$res = Array();
				if ($debug) echo str_repeat("\t", $depth)."Success for rule ".$ruleName." at pos ".$pos."\n";
				$res[$ruleName] = $result;
				$res["len"] = $result["len"];
				unset($result['len']);
				$this->successStack[$pos] = Array($ruleName, $res);
				$this->stack[$pos] = array_diff($this->stack[$pos], Array($ruleName));
			} else {
				if ($debug) echo str_repeat("\t", $depth)."Rule ".$ruleName." does not apply at pos ".$pos."\n";
				$res = false;
			}
			return $res;
		} else {
			throw new Exception("Unable to check rule ".$ruleName.": no such rule!");
		}
	}
	function checkRule($rule, $pos = 0, $debug = false, $depth = 0) {
		if (gettype($rule) == "string") {
			if (preg_match("`^<([\\w_]+)>\$`m", $rule, $matches)) {
				# found sub rule
				return $this->checkRuleByName($matches[1], $pos, $debug, $depth + 1);
			} else {
				# found base rule
				if ($debug) echo str_repeat("\t", $depth)."found base rule ".$rule."\n";
				if ($debug) echo str_repeat("\t", $depth)."/^(".$rule.")/ <==> ".substr($this->code, $pos, strpos($this->code, "\n", $pos) - $pos)."\n";
				if (preg_match("`^(".$rule.")`", substr($this->code, $pos), $matches)) {
					if ($debug) echo str_repeat("\t", $depth)."Success!\n";
					return Array("match" => $matches[1], "pos" => $pos, "len" => strlen($matches[1]));
				} else {
					if ($debug) echo str_repeat("\t", $depth)."Failed!\n";
					return false;
				}
			}
		} else {
			$matches = true;
			$resultTree = Array();
			$checkPos = $pos;
			foreach ($rule as $modifier => $subRule) {
				if (in_array($modifier, Array("+", "?", "*", "|"), true)) {
					# complex rule
					if ($modifier == "|") {
						$matches = false;
						if (gettype($subRule) == "string") {
							$result = $this->checkRule($subRule, $checkPos, $debug, $depth + 1);
							$matches = $matches || $result != false;
							if ($matches) {
								$resultTree[] = $result;
								$checkPos += $result["len"];
							} else {
								$resultTree[] = null;
							}
						} else {
							foreach ($subRule as $subsubRule) {
								if ($debug) {
									echo str_repeat("\t", $depth)."| multi rule: ".json_encode($subsubRule)."\n";
								}
								if ($checkPos >= strlen($this->code)) {
									if ($debug) echo str_repeat("\t", $depth)."Cancelled | multi rule!\n";
									$matches = false;
									break;
								}
								$result = $this->checkRule($subsubRule, $checkPos, $debug, $depth + 1);
								$matches = $matches || $result != false;
								if ($matches) {
									if ($debug) echo str_repeat("\t", $depth)."Success | multi rule\n";
									$resultTree[] = $result;
									$checkPos += $result["len"];
									break;
								} else {
									$resultTree[] = null;
								}
							}
						}
					} else {
						# quantity modifier
						$found = 0;
						$matches = true;
						$oldCheckPos = $checkPos;
						$oldResultTree = array_merge($resultTree, Array());
						while ($matches) {
							if ($checkPos >= strlen($this->code)) {
								if ($debug) echo str_repeat("\t", $depth)."Cancelled!\n";
								$matches = false;
								break;
							}
							if (gettype($subRule) == "string") {
								$result = $this->checkRule($subRule, $checkPos, $debug, $depth + 1);
								$matches = $matches && $result != false;
								if ($matches) {
									$checkPos += $result["len"];
									$resultTree[] = $result;
								} else {
									$resultTree[] = null;
								}
							} else {
								$oldCheckPos = $checkPos;
								$oldResultTree = $resultTree;
								foreach ($subRule as $subsubRule) {
									if ($debug) {
										echo str_repeat("\t", $depth)."Quantifier multi rule pos ".$checkPos.": ".json_encode($subRule)."\n";
									}
									if ($checkPos >= strlen($this->code)) {
										if (is_array($subsubRule) || $subsubRule != "<T_NEWLINE>") {
											$matches = false;
										}
										if ($debug) echo str_repeat("\t", $depth)."Cancelled quantifier multi rule!\n";
										break;
									}
									$result = $this->checkRule($subsubRule, $checkPos, $debug, $depth + 1);
									$matches = $matches && $result != false;
									if ($matches) {
										if ($debug) echo str_repeat("\t", $depth)."Success quantifier multi rule: ".json_encode($subsubRule)."\n";
										$checkPos += $result["len"];
										$resultTree[] = $result;
									} else {
										$resultTree[] = null;
										if ($debug) echo str_repeat("\t", $depth)."Failed quantifier multi rule: ".json_encode($subsubRule)."\n";
										break;
									}
								}
								if (!$matches) {
									# roll back
									$resultTree = $oldResultTree;
									$checkPos = $oldCheckPos;									
								}
							}
							if ($matches) $found++;
						}
						if ($debug) echo str_repeat("\t", $depth)."Modifier: ".$modifier." (".$found.")\n";
						if (($found >= 1 && $modifier == "+") || $modifier == "*" || ($found <= 1 && $modifier == "?")) {
							if ($debug) echo str_repeat("\t", $depth)."Matched modifier conditions.\n";
							$matches = true;
						} else {
							$resultTree = $oldResultTree;
							$checkPos = $oldCheckPos;
							$matches = false;
						}
						unset($found, $oldCheckPos, $oldResultTree);
					}
				} else {
					# simple rule
					if ($debug) echo str_repeat("\t", $depth)."Simple multi rule at pos ".$checkPos.": ".json_encode($subRule)."\n";
					$result = $this->checkRule($subRule, $checkPos, $debug, $depth + 1);
					$matches = true;
					if ($result == false) {
						if ($debug) echo str_repeat("\t", $depth)."Failed simple multi rule: ".json_encode($subRule)."\n";
						return false;
					}
					if ($debug) echo str_repeat("\t", $depth)."Success simple multi rule: ".json_encode($subRule)."\n";
					$resultTree[] = $result;
					$checkPos += $result["len"];
				}
			}
			$len = 0;
			foreach ($resultTree as &$rt) {
				$len += $rt["len"];
				unset($rt['len']);
			}
			$resultTree["len"] = $len;
			unset($len);
			return ($matches ? $resultTree : false);
		}
	}
}
?>
