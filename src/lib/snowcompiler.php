<?php
/**
	Copyright 2014 Robert Kunze

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

	    http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.
 */
error_reporting(E_ALL);

class SnowCompiler {
	# current compiler version
	static $version = '0.2.2';

	# the Snow language definition in a JSON-ENBF:
	protected $ebnf = <<<EOL
{
	"T_NULL": "null\\\\b",
	"T_INDENT": "([ ]{4}|\\t)+",
	"T_COMMENT": {"|": ["<T_MULTILINE_COMMENT>", "<T_SINGLELINE_COMMENT>"]},
	"T_CLASS": ["<T_KEY_CLASS>", "<T_CLASS_IDENTIFIER>", {"*": ["<T_KEY_CLASSTREE>", "<T_CLASS_IDENTIFIER>", {"*": ["<T_COMMA>", "<T_CLASS_IDENTIFIER>"]}]}, "<T_CLASS_BODY>"],
	"T_MULTILINE_COMMENT": "###([^#]|#[^#]|##[^#])+###",
	"T_SINGLELINE_COMMENT": "#([^\\n]*)",
	"T_INCDEC": ["<T_IDENTIFIER>", "<T_INCDEC_OPERATOR>"],
	"T_INDENTED_EXPRESSIONS": {"+": ["<T_NEWLINE>", "<T_INDENT>", "<T_EXPRESSION>"]},
	"T_CLASS_BODY": {"*": ["<T_NEWLINE>", "<T_INDENT>", {"|":["<T_CLASS_FN_DEF>", "<T_CLASS_VAR_DEF>", "<T_CLASS_CONST_DEF>", "<T_COMMENT>"]}]},
	"T_CLASS_FN_DEF": [{"?": "<T_KEY_ATTRVISIBILITY>"}, "<T_FN_DEF>"],
	"T_CLASS_VAR_DEF": [{"?": "<T_KEY_ATTRVISIBILITY>"}, "<T_PARAMETER>"],
	"T_CLASS_CONST_DEF": ["<T_CONST>", "<T_ASSIGN>", "<T_LITERAL>"],
	"T_EXPRESSIONS": {"+": ["<T_EXPRESSION>", "<T_NEWLINE>"]},
	"T_EXPRESSION": {"|": ["<T_FN_DEF>", "<T_IF>", "<T_LOOP>", "<T_CLASS>", "<T_COMMENT>", "<T_LOOP_CONTROL>", "<T_TRY_CATCH>", "<T_SIMPLE_EXPRESSION>"]},
	"T_LOOP_CONTROL": "continue|break",
	"T_TRY_CATCH": ["<T_KEY_TRY>", "<T_INDENTED_EXPRESSIONS>", "<T_NEWLINE>", {"?": "<T_INDENT>"}, "<T_KEY_CATCH>", "<T_IDENTIFIER>", "<T_INDENTED_EXPRESSIONS>", {"?": ["<T_NEWLINE>", {"?": "<T_INDENT>"}, "<T_KEY_FINALLY>", "<T_INDENTED_EXPRESSIONS>"]}],
	"T_FN_DEF": ["<T_KEY_FN>", {"?": "<T_IDENTIFIER_NAME>"}, {"?": ["<T_RBRACKET_OPEN>", {"?": "<T_PARAMETERS>"}, "<T_RBRACKET_CLOSE>"]}, {"|": ["<T_INDENTED_EXPRESSIONS>", "<T_RETURN>"]}], 
	"T_SIMPLE_EXPRESSION": {"|": ["<T_THROW>", "<T_FN_DEF>", "<T_ASSIGNMENT>", "<T_DESTRUCTURING_ASSIGNMENT>", "<T_OPERATION>", "<T_PCONDITION>", "<T_IF_THEN>", "<T_FNCALL>", "<T_FNCSCALL>", "<T_RETURN>", "<T_IDENTIFIER>", "<T_LITERAL>", "<T_CONST_DEF>", "<T_CONST>"]},
	"T_CONDITION_EXPRESSION": {"|": [["<T_RBRACKET_OPEN>", {"|": ["<T_FN_DEF>", "<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_FNCALL>", "<T_FNCSCALL>", "<T_LITERAL>", "<T_IDENTIFIER>", "<T_CONST>"]}, "<T_RBRACKET_CLOSE>"], {"|": ["<T_FN_DEF>", "<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_FNCALL>", "<T_FNCSCALL>", "<T_LITERAL>", "<T_IDENTIFIER>", "<T_CONST>"]}]},
	"T_CHAIN_EXPRESSION": {"|": ["<T_ASSIGNMENT>", "<T_OPERATION>", "<T_IF_THEN>", "<T_PCONDITION>", "<T_FNCALL>", "<T_LITERAL>", "<T_IDENTIFIER>", "<T_CONST>"]},
	"T_ASSIGNMENT": ["<T_IDENTIFIER>", "<T_OPERATOR_ASSIGN>", "<T_SIMPLE_EXPRESSION>"],
	"T_DESTRUCTURING_ASSIGNMENT": ["<T_ARRAY_LITERAL_IDENTIFIER_ONLY>", "<T_ASSIGN>", "<T_SIMPLE_EXPRESSION>"],
	"T_ARRAY_LITERAL_IDENTIFIER_ONLY": ["<T_ARRAY_START>", {"+": [{"?": [{"|": ["<T_IDENTIFIER>", "<T_ARRAY_LITERAL_IDENTIFIER_ONLY>"]}]}, "<T_COMMA>", {"?": [{"|": ["<T_IDENTIFIER>", "<T_ARRAY_LITERAL_IDENTIFIER_ONLY>"]}]}]}, "<T_ARRAY_END>"],
	"T_RETURN": ["<T_KEY_RETURN>", {"?": "<T_SIMPLE_EXPRESSION>"}],
	"T_FNCALL": [{"|": ["<T_FNDOCALL>", "<T_FNPLAINCALL>", "<T_FN_CHAINCALL>"]}, {"*": ["<T_FNBUBBLE_OPERATOR>", {"|": ["<T_FNPLAINCALL>", "<T_FNCSCALL>"]}]}],
	"T_FNDOCALL": ["<T_KEY_DO>", "<T_IDENTIFIER_NAME>"],
	"T_FNALLCALL": {"|": ["<T_FNCALL>", "<T_FNCSCALL>"]},
	"T_FNCSCALL": [{"?": "<T_KEY_ONEW>"}, "<T_IDENTIFIER_NAME>", "<T_WHITESPACE>", "<T_FN_PARAMETERS1>"],
	"T_FNPLAINCALL": [{"?": "<T_KEY_ONEW>"}, "<T_IDENTIFIER_NAME>", "<T_RBRACKET_OPEN>", "<T_FN_PARAMETERS>", "<T_RBRACKET_CLOSE>"],
	"T_FN_CHAINCALL": ["<T_CHAIN_EXPRESSION>", "<T_CHAIN_OPERATOR>", {"*": ["<T_IDENTIFIER_NAME>", "<T_RBRACKET_OPEN>", "<T_FN_PARAMETERS>", "<T_RBRACKET_CLOSE>", "<T_CHAIN_OPERATOR>"]}, {"|": ["<T_FNCSCALL>", "<T_FNPLAINCALL>"]}],
	"T_FN_PARAMETERS1": ["<T_CONDITION_EXPRESSION>", {"*": ["<T_COMMA>", "<T_CONDITION_EXPRESSION>"]}],
	"T_FN_PARAMETERS": {"?": ["<T_CONDITION_EXPRESSION>", {"*": ["<T_COMMA>", "<T_CONDITION_EXPRESSION>"]}]},
	"T_CONST_DEF": ["<T_CONST>", "<T_ASSIGN>", "<T_SIMPLE_EXPRESSION>"],
	"T_CONST": ["!", "<T_UPPERCASE_IDENTIFIER>"],
	"T_LOOP": {"|": ["<T_FOR_LOOP>", "<T_FOR_COUNT_UP_LOOP>", "<T_FOR_COUNT_DOWN_LOOP>", "<T_WHILE>"]},
	"T_FOR_COUNT_UP_LOOP": ["<T_KEY_FOR>", "<T_IDENTIFIER>", "<T_KEY_IN>", "<T_CONDITION_EXPRESSION>", "<T_KEY_TO>", "<T_CONDITION_EXPRESSION>", {"?": ["<T_KEY_STEP>", "<T_NUMBER_LITERAL>"]}, "<T_INDENTED_EXPRESSIONS>"],
	"T_FOR_COUNT_DOWN_LOOP": ["<T_KEY_FOR>", "<T_IDENTIFIER>", "<T_KEY_IN>", "<T_CONDITION_EXPRESSION>", "<T_KEY_DOWNTO>", "<T_CONDITION_EXPRESSION>", {"?": ["<T_KEY_STEP>", {"|": ["<T_NUMBER_LITERAL>", "<T_CONST>"]}]}, "<T_INDENTED_EXPRESSIONS>"],
	"T_FOR_LOOP": ["<T_KEY_FOR>", "<T_IDENTIFIER>", {"?": ["<T_COMMA>", "<T_IDENTIFIER>"]}, "<T_KEY_IN>", "<T_CONDITION_EXPRESSION>", "<T_INDENTED_EXPRESSIONS>"],
	"T_IF": ["<T_KEY_IF>", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>", {"*": ["<T_ELIF>"]}, {"?": ["<T_ELSE>"]}],
	"T_ELSE": ["<T_KEY_ELSE>", "<T_INDENTED_EXPRESSIONS>"],
	"T_ELIF": ["<T_KEY_ELSEIF>", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>"],
	"T_IF_THEN": ["<T_KEY_IF>", "<T_PCONDITION>", "<T_KEY_THEN>", "<T_SIMPLE_EXPRESSION>", {"?": ["<T_KEY_ELSE>", "<T_SIMPLE_EXPRESSION>"]}],
	"T_PCONDITION": {"|": ["<T_CONDITION>", ["<T_RBRACKET_OPEN>", "<T_CONDITION>", "<T_RBRACKET_CLOSE>"]]},
	"T_CONDITION": ["<T_CONDITION_PART>", {"*": ["<T_BOOL_OP>", "<T_CONDITION_PART>"]}],
	"T_CONDITION_PART": [{"?": "<T_BOOL_NEGATION>"}, {"|": ["<T_PCOMPARISON>", "<T_EMPTY>", "<T_EXISTS>", "<T_FNCALL>", "<T_SIMPLE_EXPRESSION>", "<T_CONDITION_PART>"]}],
	"T_EMPTY": [{"|": ["<T_CONST>", "<T_IDENTIFIER>"]}, "<T_EMPTY_OPERATOR>"],
	"T_EXISTS": [{"|": ["<T_IDENTIFIER>", "<T_CONST>"]}, "<T_EXISTS_OPERATOR>"],
	"T_WHILE": ["<T_KEY_WHILE>", "<T_PCONDITION>", "<T_INDENTED_EXPRESSIONS>"],
	"T_THROW": ["<T_KEY_THROW>", {"|": ["<T_FNALLCALL>", "<T_LITERAL>", "<T_IDENTIFIER>"]}],
	"T_KEY_CLASSTREE": "\\\\s+(extends|implements)\\\\s+",
	"T_KEY_CLASS": "class\\\\s+",
	"T_KEY_ATTRVISIBILITY": "(static|public|private|protected|final)\\\\s+",
	"T_KEY_TRY": "try[ ]*",
	"T_KEY_CATCH": "catch[ ]+",
	"T_KEY_FINALLY": "finally[ ]*",
	"T_KEY_FN": "fn\\\\s*",
	"T_KEY_RETURN": "[ ]*<-[ \\t]*",
	"T_KEY_DO": "do\\\\s+",
	"T_KEY_ONEW": "(new\\\\s+)",
	"T_KEY_STEP": "\\\\s+step\\\\s+",
	"T_KEY_TO": "\\\\s*to\\\\s+",
	"T_KEY_DOWNTO": "\\\\s*downto\\\\s+",
	"T_KEY_IN": "\\\\s+in\\\\s+",
	"T_KEY_FOR": "for\\\\s+",
	"T_KEY_WHILE": "while\\\\s+",
	"T_KEY_IF": "if\\\\s+",
	"T_KEY_THEN": "\\\\s+then\\\\s+",
	"T_KEY_ELSEIF": "\\\\s+el(?:se[ ]*)?if\\\\s+",
	"T_KEY_ELSE": "\\\\s+else[ ]*",
	"T_KEY_THROW": "throw[ ]+",
	"T_WHITESPACE": "[ ]+",
	"T_EMPTY_OPERATOR": "[ ]*\\\\?\\\\?",
	"T_EXISTS_OPERATOR": "[ ]*\\\\?",
	"T_CHAIN_OPERATOR": "\\\\s*->",
	"T_FNBUBBLE_OPERATOR": "\\\\s*\\\\.\\\\s*",
	"T_PCOMPARISON": {"|": [["<T_RBRACKET_OPEN>", "<T_COMPARISON>", "<T_RBRACKET_CLOSE>"], "<T_COMPARISON>"]},
	"T_COMPARISON": {"|": ["<T_EQUALS_COMPARISON>", "<T_NEQUALS_COMPARISON>", "<T_GT_COMPARISON>", "<T_LT_COMPARISON>", "<T_TYPE_COMPARISON>"]},
	"T_TYPE_COMPARISON": {"|": ["<T_BASIC_TYPE_COMPARISON>", "<T_OBJECT_TYPE_COMPARISON>"]},
	"T_BASIC_TYPE_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_ISA_OPERATOR>", "<T_BASIC_TYPE>"],
	"T_OBJECT_TYPE_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_ISA_OPERATOR>", "<T_CLASS_IDENTIFIER>"],
	"T_EQUALS_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_EQ_OPERATOR>", "<T_CONDITION_EXPRESSION>"],
	"T_NEQUALS_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_NEQ_OPERATOR>", "<T_CONDITION_EXPRESSION>"],
	"T_GT_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_GTE_OPERATOR>", "<T_CONDITION_EXPRESSION>"],
	"T_LT_COMPARISON": ["<T_CONDITION_EXPRESSION>", "<T_LTE_OPERATOR>", "<T_CONDITION_EXPRESSION>"],
	"T_BASIC_TYPE": "(number|string|float|double|boolean|bool|array|int|integer|function|long|null|resource|scalar|object)",
	"T_EQ_OPERATOR": "\\\\s+(is|==)\\\\s+",
	"T_NEQ_OPERATOR": "\\\\s+(isnt|!=)\\\\s+",
	"T_GTE_OPERATOR": "\\\\s*>(=?)\\\\s*",
	"T_LTE_OPERATOR": "\\\\s*<(=?)\\\\s*",
	"T_ISA_OPERATOR": "\\\\s+isa\\\\s+",
	"T_INCDEC_OPERATOR": "\\\\s*(\\\\+\\\\+)|(--)",
	"T_PARAMETERS": ["<T_PARAMETER>", {"*": ["<T_COMMA>", "<T_PARAMETER>"]}],
	"T_PARAMETER": ["<T_IDENTIFIER>", {"?": ["<T_ASSIGN>", "<T_LITERAL>"]}],
	"T_LITERAL": {"|": ["<T_REGEXP_LITERAL>", "<T_ARRAY_LITERAL>", "<T_BOOLEAN_LITERAL>", "<T_NULL>", "<T_STRING_LITERAL>", "<T_NUMBER_LITERAL>"]},
	"T_ARRAY_LITERAL": ["<T_ARRAY_START>", {"?": [{"|": ["<T_KEYVALUE_PAIR>", "<T_CONDITION_EXPRESSION>"]}, {"*": ["<T_COMMA>", {"|": ["<T_KEYVALUE_PAIR>", "<T_CONDITION_EXPRESSION>"]}]}]}, {"?": "<T_COMMA>"}, "<T_ARRAY_END>"],
	"T_KEYVALUE_PAIR": ["<T_LITERAL>", "<T_COLON>", "<T_CONDITION_EXPRESSION>"],
	"T_STRING_LITERAL": {"|": ["<T_STRING_LITERAL_UQUOTE>", "<T_STRING_LITERAL_TQUOTE>", "<T_STRING_LITERAL_DQUOTE>"]},
	"T_IDENTIFIER": [{"|": ["<T_UPPERCASE_IDENTIFIER>", "<T_IDENTIFIER_NAME>"]}, {"*": ["<T_ARRAY_START>", "<T_CONDITION_EXPRESSION>", {"?": ["<T_ARRAY_RANGE>", "<T_CONDITION_EXPRESSION>"]}, "<T_ARRAY_END>"]}],
	"T_ARRAY_START": "[ \\t]*\\\\[\\\\s*",
	"T_OPERATOR_ASSIGN": "\\\\s*[\\\\+\\\\-\\\\*/\\\\%&|]?=\\\\s*",
	"T_ASSIGN": "\\\\s*=\\\\s*",
	"T_ARRAY_END": "\\\\s*\\\\]",
	"T_COLON": "\\\\s*:\\\\s*",
	"T_COMMA": "\\\\s*,\\\\s*",
	"T_ARRAY_RANGE": "\\\\s*\\\\.\\\\.\\\\.\\\\s*",
	"T_IDENTIFIER_NAME": "(?!fn\\\\b|continue\\\\b|break\\\\b|isnt\\\\b|is\\\\b|isa\\\\b|or\\\\b|and\\\\b|xor\\\\b|mod\\\\b|then\\\\b|for\\\\b|if\\\\b|try\\\\b|catch\\\\b|finally\\\\b|class\\\\b|null\\\\b|true\\\\b|false\\\\b|do\\\\b|else\\\\b|elif\\\\b|while\\\\b|throw\\\\b|downto\\\\b)(@?)_*[a-zA-Z_]([_a-zA-Z0-9]*(\\\\.{1,2}[_a-zA-Z]+[_a-zA-Z0-9]*)*)",
	"T_UPPERCASE_IDENTIFIER": "(?!_POST|_GET|_FILES|_SESSION|_ENV|_REQUEST|_SERVER|_COOKIE|HTTP_RAW_POST_DATA|GLOBALS)_*[A-Z_][A-Z_0-9]*\\\\b",
	"T_CLASS_IDENTIFIER": "_*[A-Z][a-zA-Z0-9_]*",
	"T_RBRACKET_OPEN": "[ ]*\\\\(\\\\s*",
	"T_RBRACKET_CLOSE": "\\\\s*\\\\)",
	"T_OPERATION": {"|": ["<T_COMPLEX_OPERATION>", "<T_COMPLEX_STRING_OPERATION>", "<T_INCDEC>"]},
	"T_COMPLEX_OPERATION": ["<T_COMPLEX_OPERAND>", {"+": "<T_COMPLEX_OPERATION_ADD>"}],
	"T_COMPLEX_OPERAND": {"|": ["<T_FNCALL>", "<T_NUMBER_LITERAL>", "<T_IDENTIFIER>", "<T_COMPLEX_POPERAND>"]},
	"T_COMPLEX_POPERAND": ["<T_RBRACKET_OPEN>", {"|": ["<T_COMPLEX_OPERATION>", "<T_COMPLEX_OPERAND>"]}, "<T_RBRACKET_CLOSE>"],
	"T_COMPLEX_OPERATION_ADD": [{"|": ["<T_OPERATOR>", "<T_MODULO>"]}, {"|": ["<T_COMPLEX_OPERATION>", "<T_COMPLEX_OPERAND>"]}],
	"T_COMPLEX_STRING_OPERATION": [{"|": ["<T_FNCALL>", "<T_STRING_LITERAL>", "<T_CONST>", "<T_IDENTIFIER>"]}, {"+": "<T_COMPLEX_STRING_OPERATION_ADD>"}],
	"T_COMPLEX_STRING_OPERATION_ADD": ["<T_STRING_CONCAT>", {"|": ["<T_FNCALL>", "<T_STRING_LITERAL>", "<T_CONST>", "<T_IDENTIFIER>"]}],
	"T_MODULO": "\\\\s+mod\\\\s+",
	"T_STRING_CONCAT": "\\\\s*[\\\\+%&]\\\\s*",
	"T_OPERATOR": "[ \\t]*(?:[\\\\-\\\\+\\\\*/&|]|xor)[ \\t]*",
	"T_STRING_LITERAL_UQUOTE": "'([^']*)'",
	"T_STRING_LITERAL_DQUOTE": "\"([^\"]*)\"",
	"T_STRING_LITERAL_TQUOTE": ["\"\"\"\\\\s*", "([^\"]|\"[^\"]|\"\"[^\"])+", "\"\"\""],
	"T_BOOL_OP": {"|": ["<T_BOOL_AND>", "<T_BOOL_OR>", "<T_BOOL_XOR>"]},
	"T_BOOL_AND": "\\\\s+and\\\\s+",
	"T_BOOL_OR": "\\\\s+or\\\\s+",
	"T_BOOL_XOR": "\\\\s+xor\\\\s+",
	"T_BOOL_NEGATION": "\\\\s*not\\\\s+",
	"T_BOOLEAN_LITERAL": "true|false",
	"T_NUMBER_LITERAL": [{"|": ["<T_HEX_NUMBER>", "<T_OCT_NUMBER>", "<T_FLOAT_NUMBER>", "<T_DEC_NUMBER>"]}],
	"T_HEX_NUMBER": "(0x[0-9A-Fa-f]+)",
	"T_OCT_NUMBER": "(0[0-7]+[1L]?)",
	"T_FLOAT_NUMBER": "(-?[0-9]*\\\\.[0-9]+)",
	"T_DEC_NUMBER": "(-?[0-9]+)",
	"T_REGEXP_LITERAL": "/(\\\\\\\\/|[^/])*/[imsxADSUXJu]*",
	"T_NEWLINE": "((?:[ \\t]*;)?[ \\t]*[\\r\\n])+|\\\\s*$"
}
EOL;
	protected $mapRules = '';
# ${c} - special command: create recursive chain
# \x.y - look in tree at position x and in x look at position y
# ${\x?a/b} - if \x is not empty, replace this expression with a, else with b (either one can be left empty)
# ${R\x/a/b} - replace a in \x with b
# ${E\x/a/b} - evaluate/compile everything from \x that is in the first match group of a and replace it with b
# ${I} - add current indentation here
# ${I-1} - add previous indentation here
# ${T\x/TOKEN/output} - if TOKEN is in \x, then write output at the current position (whereas $('some-string':1) refers to how the matches should be concatenated)
	protected $language = null;
	public $mapping = null;
	protected $code = null;
	protected $stack = null;
	protected $successStack = null;
	protected $indentationLevel = 0;
	protected $maxMatch = null;
	protected $lastPos = 0;

	function __construct($code, $complete = true, $debug = false) {
		# compilation rules (might need optimization so we can actually override it in a
		# Snow -> CoffeeScript/JS/Java/whatever compiler, for example
		$this->mapRules = '{
	"T_NEWLINE": "\\n",
	"T_IF": "if (\\\\2) {\\\\3\\n${I-1}}\\\\4\\\\5\\n",
	"T_EXPRESSION": "'.($debug ? 'breakpoint(get_defined_vars());' : '').'\\\\1\\\\2\\\\3\\\\4\\\\5\\\\6\\\\7\\\\8\\\\9;",
	"T_TRY_CATCH": "try {\\\\2\\n${I-1}} catch (Exception \\\\6) {'.(PHP_VERSION_ID >= 50500 ? '' : '\\n${I}$catchGuard = true;').'\\\\7\\n${I-1}}'.(PHP_VERSION_ID >= 50500 ? '${\\\\8.4? finally {\\\\8.4\\n${I-1}}/}' : '\\nif (!isset($catchGuard)) {\\\\8.4\\n${I-1}} else {\\n${I}unset($catchGuard);\\n${I-1}}\\n').'",
	"T_CLASS": "class \\\\2\\\\3 {\\\\4\\n${I-2}}",
	"T_CLASS_FN_DEF": "\\\\1\\\\2",
	"T_CLASS_VAR_DEF": "\\\\1\\\\2;",
	"T_CLASS_CONST_DEF": "const \\\\1\\\\2\\\\3;",
	"T_MULTILINE_COMMENT": "/*${R\\\\1/#/}*/",
	"T_SINGLELINE_COMMENT": "//${R\\\\1/#/}",
	"T_BOOL_AND": " && ",
	"T_MODULO": " % ",
	"T_BOOL_OR": " || ",
	"T_BOOL_NEGATION": "!",
	"T_INCDEC": "\\\\1\\\\2",
	"T_LOOP_CONTROL": "\\\\1",
	"T_FNBUBBLE_OPERATOR": "->",
	"T_BASIC_TYPE_COMPARISON": "is_${R\\\\3/number/numeric}${R\\\\3/function/callable}${R\\\\3/boolean/bool}(\\\\1)",
	"T_OBJECT_TYPE_COMPARISON": "\\\\1 instanceof \\\\3",
	"T_COMPLEX_OPERATION": "\\\\1\\\\2",
	"T_COMPLEX_OPERATION_ADD": "\\\\1\\\\2",
	"T_COMPLEX_STRING_OPERATION": "\\\\1\\\\2",
	"T_COMPLEX_STRING_OPERATION_ADD": " . \\\\2",
	"T_EXISTS": "${\\\\1.2?defined(\'\\\\1\')/isset(\\\\1)}",
	"T_EMPTY": "(${\\\\1.1?defined(\'\\\\1\') && strlen(\\\\1) > 0/!empty(\\\\1)})",
	"T_FN_DEF": "function \\\\2(\\\\3.2) {${T\\\\4/T_UPPERCASE_IDENTIFIER/global $$(\', $\':1);\\n}\\\\4\\n${I-1}}",
	"T_EQUALS_COMPARISON": "\\\\1 === \\\\3",
	"T_NEQUALS_COMPARISON": "\\\\1 !== \\\\3",
	"T_GT_COMPARISON": "(gettype($_tmp1 = \\\\1) === gettype($_tmp2 = \\\\3) && ($_tmp1 \\\\2 $_tmp2 && (($_tmp1 = $_tmp2 = null) || true)) || ($_tmp1 = $_tmp2 = null))",
	"T_LT_COMPARISON": "(gettype($_tmp1 = \\\\1) === gettype($_tmp2 = \\\\3) && ($_tmp1 \\\\2 $_tmp2 && (($_tmp1 = $_tmp2 = null) || true)) || ($_tmp1 = $_tmp2 = null))",
	"T_IDENTIFIER": "${\\\\2.3?array_slice(/}${\\\\1~=(^(true|false|null)$)|\\\\.\\\\.?/$}${R\\\\1/\\\\.\\\\./::$}${R\\\\1/@(.+)/this->\\\\1}${R\\\\1/\\\\./->}${\\\\2.3?, $_tmp3 = (\\\\2.2), (\\\\2.3.2) - $_tmp3 + 1)/\\\\2}",
	"T_CONST_DEF": "define(\\"\\\\1\\", \\\\3)",
	"T_CONST": "\\\\2",
	"T_ELSE": " else {\\\\2\\n${I-1}}",
	"T_ELIF": " else if (\\\\2) {\\\\3\\n${I-1}}",
	"T_FOR_LOOP": "foreach (\\\\5 as ${\\\\3.2?\\\\3.2 => /}\\\\2) {\\\\6\\n${I-1}}\\n${I-1}unset(\\\\2${\\\\3.2?, \\\\3.2/});\\n",
	"T_FOR_COUNT_UP_LOOP": "for (\\\\2 = \\\\4; \\\\2 <= \\\\6; \\\\2 += ${\\\\7.2?\\\\7.2/1}) {\\\\8;\\n${I-1}}\\n${I-1}unset(\\\\2);\\n",
	"T_FOR_COUNT_DOWN_LOOP": "for (\\\\2 = \\\\4; \\\\2 >= \\\\6; \\\\2 -= ${\\\\7.2?\\\\7.2/1}) {\\\\8;\\n${I-1}}\\n${I-1}unset(\\\\2);\\n",
	"T_IF_THEN": "(\\\\2 ? \\\\4 : ${\\\\5.2?\\\\5.2/null})",
	"T_ARRAY_LITERAL": "Array(\\\\2)",
	"T_KEYVALUE_PAIR": "\\\\1 => \\\\3",
	"T_NUMBER_LITERAL": "\\\\1",
	"T_BOOLEAN_LITERAL": "\\\\1",
	"T_REGEXP_LITERAL": "\'${R\\\\1/\'/\\\\\'}\'",
	"T_WHILE": "while (\\\\2) {\\\\3;\\n${I-1}}\\n",
	"T_FNPLAINCALL": "\\\\1${R\\\\2/^@/$this->}${R\\\\2/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\2/^(\\\\w+)\\\\./$\\\\1->}${R\\\\2/(\\\\w+)\\\\./\\\\1->}(\\\\4)",
	"T_FNCSCALL": "\\\\1${R\\\\2/^@/$this->}${R\\\\2/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\2/^(\\\\w+)\\\\./$\\\\1->}${R\\\\2/(\\\\w+)\\\\./\\\\1->}(\\\\4)",
	"T_FNDOCALL": "${R\\\\2/^@/$this->}${R\\\\2/^(\\\\w+)\\\\.\\\\./\\\\1::}${R\\\\2/^(\\\\w+)\\\\./$\\\\1->}${R\\\\2/(\\\\w+)\\\\./\\\\1->}();\\n",
	"T_ASSIGNMENT": "\\\\1 \\\\2 \\\\3",
	"T_RETURN": "'.($debug ? 'breakpoint(get_defined_vars());' : '').'return \\\\2;\\n",
	"T_STRING_LITERAL_UQUOTE": "\\\\1",
	"T_STRING_LITERAL_DQUOTE": "${E\\\\1/\\\\{([^}]+)\\\\}/\" . (\\\\1) . \"}",
	"T_STRING_LITERAL_TQUOTE": "<<<EOF\\n${E\\\\2/\\\\{([^}]+)\\\\}/\\\\1}\\nEOF\\n${I-1}",
	"T_FN_CHAINCALL": "${c}",
	"T_DESTRUCTURING_ASSIGNMENT": "\\\\1 = \\\\3",
	"T_ARRAY_LITERAL_IDENTIFIER_ONLY": "list(\\\\2)",
	"T_THROW": "throw ${\\\\2.1?\\\\2.1/new Exception(\\\\2)}",
	"SETUP": ""
	}';
		$this->language = json_decode($this->ebnf, true);
		$this->mapping = json_decode($this->mapRules, true);
		$this->mapping["SETUP"] = preg_replace(Array('/^\\s*(#|\\/\\/|<\\?).*$/m', '/\s+/m'), Array('', ' '), file_get_contents(dirname(__FILE__) . "/setup.php"))."\n";
		$this->code = trim($code) . ($complete ? "\nnull" : "");
		$this->startWith = ($complete ? "T_EXPRESSIONS" : "T_SIMPLE_EXPRESSION");
		$this->stack = Array();
		$this->indentationLevel = 0;
		$this->successStack = Array();
		$this->lineOffset = Array();
		$this->maxMatch = Array(0, null);

		$this->prepareCode();
	}

	# this method pre-parses the code in order to analyze it's structure
	function prepareCode() {
		$lines = explode("\n", $this->code);
		$prevDepth = 0;
		$inNoCompile = false;
		foreach ($lines as $id => $line) {
			$hasIndent = preg_match('/^' . $this->language["T_INDENT"] . '/', $line, $indent);

			// ignore indentation changes in multiline strings and comments
			$types = Array('"""', "'", '"', '###');
			$continue = false;
			foreach ($types as $type) {
				preg_match_all('/' . $type . '/', $line, $matches);
				if (count($matches[0]) % 2 == 1) {
					if (!$inNoCompile) {
						$inNoCompile = $type;
					} else if ($inNoCompile == $type) {
						$inNoCompile = false;
						$continue = true;
						break;
					}
				}
			}
			// if empty line
			if (preg_match('/^\\s*$/', $line) || $inNoCompile || $continue) {
				continue;
			}
			$depth = 0;
			if (count($indent) > 1 && $indent[1] == "\t") {
				$depth = strlen($indent[0]);
			} else if (count($indent) > 0) {
				$depth = strlen($indent[0]) / 4;
			}
			if ($depth < $prevDepth - 1) {
				// do a look-ahead and check if the next non-empty line starts with "else"
				$c = 1;
				$ignore = false;
				while (1) {
					if (preg_match('/^\s*else\b/', isset($lines[$id + $c]) ? $lines[$id + $c] : "") !== false) {
						$ignore = true;
						break;
					}
					if (preg_match('/^\\s*$/', isset($lines[$id + $c]) ? $lines[$id + $c] : "") === false) {
						break;
					}
					$c++;
				}
				$this->lineOffset[$id + ($prevDepth - $depth) + 1] = 0;
				for ($i = 1; $i <= $prevDepth - $depth; $i++) {
					if (!$ignore || $i != ($prevDepth - $depth)) {
						$this->lineOffset[$id + ($prevDepth - $depth) + 1]++;
						$lines[$id - 1] .= "\n" . str_repeat("\t", $prevDepth - $i) . "# end block";
					}
				}
			}
			$prevDepth = $depth;
		}
		$this->code = implode("\n", $lines);
	}

	# this method triggers the compilation procedures...
	function compile($debug = false, $setup = true) {
		$result = "";
		if (empty($this->code) || $this->code === "\nnull") return "";
		if ($tree = $this->checkRuleByName($this->startWith, 0, $debug)) {
			if ($tree["len"] < strlen($this->code)) {
				$lines = explode("\n", $this->code);
				$line = count(explode("\n", substr($this->code, 0, $this->lastPos)));
				if ($this->maxMatch[2] > 0) {
					$lines = explode("\n", substr($this->code, 0, $this->maxMatch['error']));
					$line = count($lines);
					$pre = array_pop($lines);
					$exploded = explode("\n", substr($this->code, $this->maxMatch['error']));
					$post = array_shift($exploded);
					$nl = str_repeat("-", strlen($pre)) . "^";
					throw new Exception("Unexpected character while trying to parse ".$this->maxMatch[1]." at line ".($line - (isset($this->lineOffset[$line]) ? intval($this->lineOffset[$line]) : 0)).": \n" . $pre . $post . "\n" . $nl);
				}
				throw new Exception("Error at line ".($line - (isset($this->lineOffset[$line]) ? intval($this->lineOffset[$line]) : 0))." while parsing input: \"".(isset($lines[$line - 1]) ? $lines[$line - 1] : '')."\"");
			}
			$result = $this->doMapping($tree);
			if ($this->startWith === "T_EXPRESSIONS" && $setup) {
				$result = $this->mapping["SETUP"] . $result;
			}
			unset($tree);
		} else {
			throw new Exception("Unable to parse input: given input is no T_EXPRESSIONS:\n".$this->code);
		}

		return $result;
	}

	function buildChain($chain, $last, $root, $template) {
		$offset1 = $offset2 = 1;
		if (!empty($last)) {
			if (count($last) > 1) {
				$slice = array_pop($last[1]);
			} else {
				$slice = array_pop($last[0]);
			}
		} else {
			$c = count($chain) - 5;
			$slice = array_splice($chain, $c < 0 ? 0 : $c, 5);

			if ($slice[0]["T_IDENTIFIER_NAME"]) {
				$offset1 = $offset2 = 0;
			}
		}
		$template = $this->getValue($slice[$offset1])."(";

		if (count($chain) > 0) {
			$template .= $this->buildChain($chain, null, $root, $template);
		} else {
			$template .= $root;
		}
		$params = $this->getValue($slice[2 + $offset2]);
		if (!empty($params)) {
			$template .= ", ".$params;
		}
		$template .= ")";
		return $template;
	}

	# converts the parsed and tokenized code tree into the actual output code
	function parseMapping($template, $tree) {
		$replacements = Array();
		preg_match('/\\$\\{c\\}/m', $template, $chain);
		if (count($chain) > 0) {
			$template = $this->buildChain($tree[2], $tree[3], $this->getValue($tree[0]), $template);
			return $template;
		}

		preg_match_all('/\\$\\{T\\\\([1-9][0-9.]*)\\/([^\\/]*)\\/([^}]*)\\}/m', $template, $tokenMatches);
		foreach ($tokenMatches[1] as $key => $match) {
			# make find the element of match $1 in tree
			$parts = explode(".", $match);
			$oldValue = $tree;
			for ($i = 0; $i < count($parts) - 1; $i++) {
				$tree = $tree[intval($parts[$i]) - 1];
			}
			# search for token of match $2 in sub tree and store all found items in array
			$tokens = array_unique($this->getValues($tree, $tokenMatches[2][$key]));
			$add = "";
			if (count($tokens) > 0) {
				# then parse $3
				preg_match('/\\$\\(\'([^\']*)\':1\\)/', $tokenMatches[3][$key], $rep);
				$add = str_replace($rep[0], implode($rep[1], $tokens), $tokenMatches[3][$key]);
				# and put the result instead of $tokenMatch[0][$key]
			}
			$template = str_replace($tokenMatches[0][$key], $add, $template);
			# reset tree refs
			$tree = $oldValue;
		}

		preg_match_all('/\\\\([1-9][0-9.]*)/m', $template, $matches);
		foreach ($matches[1] as $key => $match) {
			$parts = explode(".", $match);
			$oldValue = $tree;
			for ($i = 0; $i < count($parts) - 1; $i++) {
				$key2 = intval($parts[$i]) - 1;
				$tree = isset($tree[$key2]) ? $tree[$key2] : null;
			}

			//if (($tree[0] !== null && !isset($tree[0])) && !isset($tree["match"])) { $tree = array_pop($tree); }
			$val = $this->getValue($tree, intval($parts[$i]) - 1);
			$replacements[0][$matches[0][$key]] = $val;
			$tree = $oldValue;
		}

		preg_match_all('/\\$\\{I(-[0-9]+)?\\}/m', $template, $indentMatches);
		foreach ($indentMatches[0] as $key => $match) {
			$indent = $tree["indent"];
			if (isset($indentMatches[1][$key][0]) && $indentMatches[1][$key][0] == "-") {
				$indent = $tree["indent"] - (-1 * $indentMatches[1][$key]);
			}
			if ($indent > 0)
				$replacements[0][$match] = str_repeat("\t", $indent);
			else 
				$replacements[0][$match] = "";
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
						$sn = new SnowCompiler($m, false);
						$repl["\\".$match] = str_replace($evals[0][$k], str_replace('\\1', $sn->compile(), $evalMatches[3][$key]), $repl['\\'.$match]);
					}
				}
				$resultTemplate = str_replace($evalMatches[0][$key], "\\".$match, $resultTemplate);
			}
			# need to somehow make it also take the recurse command into consideration...
			uksort($repl, create_function("\$a, \$b", 'return strlen($b) - strlen($a);'));
			foreach ($repl as $key => $re) {
				# probably only needs to take the sub string into account that comes after the <Rx>
				$resultTemplate = str_replace($key, $re, $resultTemplate);
			}
			$result = $resultTemplate; 
		}
		return $result;
	}

	# retrieves all values of a tree-node and it's sub nodes that conform to the given ruleName.
	function getValues($ruleArray, $ruleName) {
		$result = Array();
		if (isset($ruleArray[$ruleName])) {
			$result[] = $this->getValue($ruleArray[$ruleName]);
		} else {
			if (is_array($ruleArray)) foreach ($ruleArray as $name => $rule) {
				if (isset($rule[$ruleName])) {
					$result[] = $this->getValue($rule[$ruleName]);
				} else {
					$result = array_merge($result, $this->getValues($rule, $ruleName));
				}
			}
		}
		return $result;
	}
	
	# triggers the conversion from parsed and tokenized code tree to output code
	function doMapping($tree, $name = "") {
		$result = "";
		if (isset($this->mapping[$name]) && $this->mapping[$name]) {
			$result .= $this->parseMapping($this->mapping[$name], $tree);
		} else {
			foreach ($tree as $nodeName => $value) {
				if (isset($this->mapping[$nodeName]) && $this->mapping[$nodeName]) {
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

	# finds the tree node's actual value (the original source code fragment)
	function getValue($ruleArray, $pos = -1) {
		# go down until I find a match
		$matches = "";
		if (isset($ruleArray["match"])) {
			$matches .= $ruleArray["match"];
		} else {
			if ($pos != -1) {
				$ruleArray = (isset($ruleArray[$pos]) ? $ruleArray[$pos] : null);
				if (isset($ruleArray["match"]) && $ruleArray["match"]) {
					$matches .= $ruleArray["match"];
					return $matches;
				}
			}
			if (is_array($ruleArray)) {
				foreach ($ruleArray as $name => $rule) {
					if (isset($this->mapping[$name]) && $this->mapping[$name]) {
						$matches .= $this->doMapping($rule, $name);
					} else {
						if (isset($rule['match']) && $rule["match"]) {
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

	# checks whether or not the given code starting from $pos does fulfill the rule $ruleName
	function checkRuleByName($ruleName, $pos = 0, $debug = false, $depth = 0) {
		if ($rule = $this->language[$ruleName]) {
			if ($debug) echo str_repeat(" ", $depth) . "Checking rule $ruleName at $pos\n";
			if (isset($this->stack[$pos]) && is_array($this->stack[$pos]) && in_array($ruleName, $this->stack[$pos])) {
				return false;
			}
			if (isset($this->successStack[$pos]) && isset($this->successStack[$pos][0]) && $this->successStack[$pos][0] == $ruleName) return $this->successStack[$pos][1];
			$this->stack[$pos][] = $ruleName;
 			$result = $this->checkRule($rule, $pos, (gettype($debug) != "string" ? $debug : false) || ($ruleName == (gettype($debug) == "string" ? $debug : "")), $depth);
 			# is we're to check for an indentation token
			if ($ruleName === 'T_INDENT') {
				# get the current line
				$cline = substr($this->code, $pos, strpos($this->code, "\n", $pos) - $pos);
				# and get the amount of indentation "characters"
				preg_match_all("/([ ]{4}|[\t])/", $cline, $matches);
				if (isset($matches[1]) && isset($matches[1][0]) && $matches[1][0][0] == "\t" && !is_array($matches[1])) {
					# if we found tabs and spaces mixed
					if (preg_match("/^\t+([ ]{4})+\t*/", $cline)) {
						$lines = explode("\n", substr($this->code, 0, $pos));
						$line = count($lines);
						# tell the user.
						throw new Exception("Mixed spaces with tabs used for indentation in line $line. Please fix.");
					}
					# save the measured indentation depth
					$indentDepth = strlen($matches[1][0]);
				} else {
					# if we found spaces but there's tabs mixed in between
					if (preg_match("/(?:^\t+[ ]{4})|(?:^[ ]{4}\t+)/", $cline)) {
						$lines = explode("\n", substr($this->code, 0, $pos));
						$line = count($lines);
						# tell the user
						throw new Exception("Mixed spaces with tabs used for indentation in line $line. Please fix.");
					}
					# save the measured indentation depth
					$indentDepth = count($matches[1]);
				}
				# the measured indentation depth is smaller than it should be
				if ($indentDepth < $this->indentationLevel) {
					# so we need to reducing the indentation depth and remove items from the stack as well
					$this->stack[$pos] = array_diff($this->stack[$pos], Array($ruleName));
					$result = false;
				}
				$this->indentationLevel = $indentDepth;
			}
			# if the rule matched
			if ($result != false) {
				$res = Array();
				$res[$ruleName] = $result;
				$res["len"] = $result["len"];
				$res["indent"] = $result["indent"];
				unset($result['len'], $result['indent']);
				if ($debug) {
					echo str_repeat(" ", $depth) . "Matched rule $ruleName at $pos <===> " . str_replace("\n", "#", substr($this->code, $pos, 10)) . "\n";
				}
				# add the information to the stack of all successful matches
				$this->successStack[$pos] = Array($ruleName, $res, $this->indentationLevel);
				$this->stack[$pos] = array_diff($this->stack[$pos], Array($ruleName));
			} else {
				# the rule did not match
				$this->lastPos = $pos;
				if ($debug) {
					echo str_repeat(" ", $depth) . "Failed rule $ruleName at $pos <===> " . str_replace("\n", "#", substr($this->code, $pos, 10)) . "\n";
				}
				# remove the dirty flag and save what did not match
				if (isset($this->maxMatch['dirty']) && $this->maxMatch['dirty']) {
					$this->maxMatch['dirty'] = false;
					$this->maxMatch[1] = $ruleName;
				}
				$res = false;
			}
			return $res;
		} else {
			throw new Exception("Unable to check rule ".$ruleName.": no such rule!");
		}
	}
	
	# checks the current code fragment starting in position $pos for the given $rule description
	function checkRule($rule, $pos = 0, $debug = false, $depth = 0) {
		if (gettype($rule) == "string") {
			if (preg_match("`^<([\\w_]+)>\$`m", $rule, $matches)) {
				# found sub rule
				return $this->checkRuleByName($matches[1], $pos, $debug, $depth + 1);
			} else {
				# found base rule
				$mfrag = substr($this->code, $pos, strpos($this->code, "\n", $pos) - $pos);
				if (preg_match("`^(".$rule.")`", substr($this->code, $pos), $matches)) {
					if ($debug) echo str_repeat(" ", $depth) . "Matched at $pos: base rule $rule <===> " . str_replace("\n", "#", substr($this->code, $pos, 10)) ."\n";
					return Array("match" => $matches[1], "pos" => $pos, "len" => strlen($matches[1]), "indent" => $this->indentationLevel);
				} else {
					if ($debug) echo str_repeat(" ", $depth) . "Fail at $pos: base rule $rule <===> " . str_replace("\n", "#", substr($this->code, $pos, 10)) ."\n";
					return false;
				}
			}
		} else {
			$matches = true;
			$resultTree = Array();
			$checkPos = $pos;
			$matchLen = 0;
			foreach ($rule as $modifier => $subRule) {
				# if we have a complex rule
				if (in_array($modifier, Array("+", "?", "*", "|"), true)) {
					# check if the type of complex rule is an "OR" rule
					if ($modifier == "|") {
						$matches = false;
						# yep, is an "OR" rule, so then at least one of the sub-rules needs to match
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
								if ($checkPos >= strlen($this->code)) {
									$matches = false;
									break;
								}
								$result = $this->checkRule($subsubRule, $checkPos, $debug, $depth + 1);
								$matches = $matches || $result != false;
								if ($matches) {
									$resultTree[] = $result;
									$checkPos += $result["len"];
									if ($debug) echo str_repeat(" ", $depth). "Modifier $modifier matched for rule " . json_encode($rule) . "\n";
									break;
								} else {
									$resultTree[] = null;
									if ($debug) echo str_repeat(" ", $depth). "Unmatched modifier $modifier for rule " . json_encode($rule) . "\n";
								}
							}
						}
					} else {
						# we got a quantity modifier (+ ? or *)
						$found = 0;
						$matches = true;
						$oldCheckPos = $checkPos;
						# remember the old result tree (just in case we have to roll back to the previous state)
						$oldResultTree = array_merge($resultTree, Array());
						$indent = $this->indentationLevel;
						# so we just for as long as we find correct matches
						while ($matches) {
							# just in case we reached the end of the code
							if ($checkPos >= strlen($this->code)) {
								$matches = false;
								# we stop it here...
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
									# if we reached the end of the code
									if ($checkPos >= strlen($this->code)) {
										# if we're not checking for a newline, then it's an automatic fail match
										if (is_array($subsubRule) || $subsubRule != "<T_NEWLINE>") {
											$matches = false;
										}
										# stop it
										break;
									}
									# no stopping necessary, so we just check the sub sub rule
									$result = $this->checkRule($subsubRule, $checkPos, $debug, $depth + 1);
									$matches = $matches && $result != false;
									if ($matches) {
										$checkPos += $result["len"];
										$resultTree[] = $result;
									} else {
										$resultTree[] = null;
										break;
									}
								}
								if (!$matches) {
									# the result does not match, so we need to roll back
									$resultTree = $oldResultTree;
									$checkPos = $oldCheckPos;
								}
							}
							if ($matches) $found++;
						}
						# if the exact number of matches was found for the given modifier
						if (($found >= 1 && $modifier == "+") || $modifier == "*" || ($found <= 1 && $modifier == "?")) {
							# we have a match
							$matches = true;
							if ($debug) echo str_repeat(" ", $depth). "Modifier $modifier matched for rule " . json_encode($rule) . "\n";
						} else {
							# if the indentation is not the current indentation level any more
							if ($indent != $this->indentationLevel) {
								# then the modifier matched, but we are in the wrong recursion level to handle it.
								$matches = true;
								if ($debug) echo str_repeat(" ", $depth). "Modifier $modifier matched but have to jump further up for rule " . json_encode($rule) . "\n";
								$checkPos -= $result['len'];
								$resultTree = array_slice($resultTree, 0, -1);
								unset($found, $oldCheckPos, $oldResultTree);
								break;
							} else {
								$resultTree = $oldResultTree;
								$checkPos = $oldCheckPos;
								$matches = false;
								if ($debug) echo str_repeat(" ", $depth). "Unmatched modifier $modifier (found $found times) for rule " . json_encode($rule) . "\n";
							}
						}
						unset($found, $oldCheckPos, $oldResultTree);
					}
				} else {
					# we have a simple array rule
					$result = $this->checkRule($subRule, $checkPos, $debug, $depth + 1);
					$matches = true;
					# if one of the entries fails, then the whole array is "failed".
					if ($result == false) {
						if ($checkPos >= $this->maxMatch[0]) {
							$this->maxMatch[0] = $checkPos;
							$this->maxMatch[2] = $matchLen;
							$this->maxMatch['dirty'] = true;
							$this->maxMatch['error'] = $checkPos;
						}
						# so we just break here
						return false;
					}
					$matchLen++;
					$resultTree[] = $result;
					$checkPos += $result["len"];
				}
			}
			$len = 0;
			$indent = $this->indentationLevel;
			foreach ($resultTree as &$rt) {
				$len += $rt["len"];
				if (isset($rt["indent"])) {
					$indent = $rt["indent"];
				}
				unset($rt['len'], $rt["indent"]);
			}
			$resultTree["len"] = $len;
			$resultTree["indent"] = $indent;
			unset($len, $indent);
			return ($matches ? $resultTree : false);
		}
	}
}
?>
