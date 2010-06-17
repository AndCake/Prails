/* Parse function for PHP. Makes use of the tokenizer from
 * tokenizephp.js.
 *
 * See manual.html for more info about the parser interface.
 *
 * Features:
 * + special "deprecated" style for PHP4 keywords like 'var'
 * + support for PHP 5.3 keywords: 'namespace', 'use'
 * + 911 predefined constants, 1301 predefined functions, 105 predeclared classes
 *   from a typical PHP installation in a LAMP environment
 * + new feature: syntax error flagging, thus enabling strict parsing of:
 *   + function definitions with explicitly or implicitly typed arguments and default values
 *   + modifiers (public, static etc.) applied to method and member definitions
 *   + foreach(array_expression as $key [=> $value]) loops
 * + differentiation between single-quoted strings and double-quoted interpolating strings  
 * 
 * Copyright (c) 2008 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content of this file are licensed under the BSD
 * open source license
 *
 * @author Dan Vlad Dascalescu <dandv@yahoo-inc.com>
 *
 * TODO: either remove the JS local variable scoping code, or add proper support for PHP
 */

var PHPParser = Editor.Parser = (function() {
  // Token types that can be considered to be atoms.
  var atomicTypes = {"atom": true, "number": true, "variable": true, "string": true, "t_string": true};
  // Constructor for the lexical context objects.
  function JSLexical(indented, column, type, align, prev) {
    // indentation at start of this line
    this.indented = indented;
    // column at which this scope was opened
    this.column = column;
    // type of scope ('stat' (statement), 'form' (special form), '[', '{', or '(')
    this.type = type;
    // '[', '{', or '(' blocks that have any text after their opening
    // character are said to be 'aligned' -- any lines below are
    // indented all the way to the opening character.
    if (align != null)
      this.align = align;
    // Parent scope, if any.
    this.prev = prev;
  }
  // 4-space PHP indentation rules
  function indentJS(lexical) {
    return function(firstChars) {
      var firstChar = firstChars && firstChars.charAt(0);
      var closing = firstChar == lexical.type;
      if (lexical.type == "form" && firstChar == "{")
        return lexical.indented;
      else if (lexical.type == "stat" || lexical.type == "form")
        return lexical.indented + 4;
      else if (lexical.align)
        return lexical.column - (closing ? 1 : 0);
      else
        return lexical.indented + (closing ? 0 : 4);
    };
  }

  // The parser-iterator-producing function itself.
  function parsePHP(input, basecolumn) {
    // Wrap the input in a token stream
    var tokens = tokenizePHP(input);
    // The parser state. cc is a stack of actions that have to be
    // performed to finish the current statement. For example we might
    // know that we still need to find a closing parenthesis and a
    // semicolon. Actions at the end of the stack go first. It is
    // initialized with an infinitely looping action that consumes
    // whole statements.
    var cc = [statements];
    // Context contains information about the current local scope, the
    // variables defined in that, and the scopes above it.
    var context = null;
    // The lexical scope, used mostly for indentation.
    var lexical = new JSLexical((basecolumn || 0) - 2, 0, "block", false);
    // Current column, and the indentation at the start of the current
    // line. Used to create lexical scope objects.
    var column = 0;
    var indented = 0;
    // Variables which are used by the mark, cont, and pass functions
    // below to communicate with the driver loop in the 'next'
    // function.
    var consume, marked;
  
    // The iterator object.
    var parser = {next: next, copy: copy};

    // parsing is accomplished by calling next() repeatedly
    function next(){
      // Start by performing any 'lexical' actions (adjusting the
      // lexical variable), or the operations below will be working
      // with the wrong lexical state.
      while(cc[cc.length - 1].lex)
        cc.pop()();

      // Fetch the next token.
      var token = tokens.next();

      // Adjust column and indented.
      if (token.type == "whitespace" && column == 0)
        indented = token.value.length;
      column += token.value.length;
      if (token.content == "\n"){
        indented = column = 0;
        // If the lexical scope's align property is still undefined at
        // the end of the line, it is an un-aligned scope.
        if (!("align" in lexical))
          lexical.align = false;
        // Newline tokens get an indentation function associated with
        // them.
        token.indentation = indentJS(lexical);
      }
      // No more processing for meaningless tokens.
      if (token.type == "whitespace" || token.type == "comment")
        return token;
      // When a meaningful token is found and the lexical scope's
      // align is undefined, it is an aligned scope.
      if (!("align" in lexical))
        lexical.align = true;

      // Execute actions until one 'consumes' the token and we can
      // return it. 'marked' is used to change the style of the current token.
      while(true){
        consume = marked = false;
        // Take and execute the topmost action.
        var action = cc.pop();
        // we pass both token.* and token because require() needs the token
        // to pass on the style of the token that caused the syntax error
        // FIXME: we could pass only the token, or get the style otherwise in require()
        action(token.type, token.content, token);
        
        if (consume){
          if (marked)
            token.style = marked;
          // Here we differentiate between local and global variables.
          else if (token.type == "variable" && inScope(token.content))
            token.style = "js-localvariable";
          return token;
        }
      }
    }

    // This makes a copy of the parser state. It stores all the
    // stateful variables in a closure, and returns a function that
    // will restore them when called with a new input stream. Note
    // that the cc array has to be copied, because it is contantly
    // being modified. Lexical objects are not mutated, and context
    // objects are not mutated in a harmful way, so they can be shared
    // between runs of the parser.
    function copy(){
      var _context = context, _lexical = lexical, _cc = cc.concat([]), _tokenState = tokens.state;
  
      return function(input){
        context = _context;
        lexical = _lexical;
        cc = _cc.concat([]); // copies the array
        column = indented = 0;
        tokens = tokenizePHP(input, _tokenState);
        return parser;
      };
    }

    // Helper function for pushing a number of actions onto the cc
    // stack in reverse order.
    function push(fs){
      for (var i = fs.length - 1; i >= 0; i--)
        cc.push(fs[i]);
    }
    // cont and pass are used by the action functions to add other
    // actions to the stack. cont will cause the current token to be
    // consumed, pass will leave it for the next action.
    function cont(){
      push(arguments);
      consume = true;
    }
    function pass(){
      push(arguments);
      consume = false;
    }
    // Used to change the style of the current token.
    function mark(style){
      marked = style;
    }
    // Add a lyer of style to the current token, for example syntax-error
    function mark_add(style){
      marked = marked + ' ' + style;
    }
    
    // Push a new scope. Will automatically link the current scope.
    function pushcontext(){
      context = {prev: context, vars: {"this": true}};
    }
    // Pop off the current scope.
    function popcontext(){
      context = context.prev;
    }
    // Check whether a variable is defined in the current scope.
    function inScope(varname){
      var cursor = context;
      while (cursor) {
        if (cursor.vars[varname])
          return true;
        cursor = cursor.prev;
      }
      return false;
    }
  
    // Push a new lexical context of the given type.
    function pushlex(type){
      var result = function(){
        lexical = new JSLexical(indented, column, type, null, lexical)
      };
      result.lex = true;
      return result;
    }
    // Pop off the current lexical context.
    function poplex(){
      lexical = lexical.prev;
    }
    poplex.lex = true;
    // The 'lex' flag on these actions is used by the 'next' function
    // to know they can (and have to) be ran before moving on to the
    // next token.
  
    // Creates an action that discards tokens until it finds one of
    // the given type. This will ignore (and recover from) syntax errors.
    function expect(wanted){
      return function(type){
        if (type == wanted) cont();  // consume the token
        else {
          cont(arguments.callee);
        }
      };
    }

    // Require a specific token type, or one of the tokens passed in the 'wanted' array
    // Used to detect blatant syntax errors.
    function require(wanted){
      return function(type, content, token){
        var ok;
        if (typeof(wanted) == "string")
          ok = type == wanted;
        else
          ok = wanted.indexOf(type) != -1;
        if (ok) {
           // FIXME: this works correctly but could be more elegant and extensible
           // for other statement types than "function"
          if (type == "function")
            funcdef();
          else
            cont();  // just consume the token
        }   
        else {
          if (!marked) mark(token.style);
          mark_add("syntax-error");
          cont(arguments.callee);
        }
      };
    }
    
    // Looks for a statement, and then calls itself.
    function statements(type){
      return pass(statement, statements);
    }
    // Dispatches various types of statements based on the type of the
    // current token.
    function statement(type){
      if (type == "keyword a") cont(pushlex("form"), expression, statement, poplex);
      else if (type == "keyword b") cont(pushlex("form"), statement, poplex);
      else if (type == "{") cont(pushlex("}"), block, poplex);
      else if (type == "function") funcdef();
      // technically, "class implode {...}" is correct, but we'll flag that as an error because it overrides a predefined function. FIXME: should be a warning
      // FIXME: the "expect("{")' is rough. 'implements' or 'extends' can follow.
      else if (type == "class") cont(require("t_string"), expect("{"), pushlex("}"), block, poplex);
      else if (type == "foreach") cont(pushlex("form"), require("("), pushlex(")"), expression, require("as"), require("variable"), /* => $value */ expect(")"), poplex, statement, poplex);
      else if (type == "for") cont(pushlex("form"), require("("), pushlex(")"), expression, require(";"), expression, require(";"), expression, require(")"), poplex, statement, poplex);
      // public final function foo(), protected static $bar;
      else if (type == "modifier") cont(require(["modifier", "variable", "function"]));
      else if (type == "case") cont(expression, require(":"));
      else if (type == "default") cont(require(":"));
      // FIXME: remove context
      else if (type == "catch") cont(pushlex("form"), pushcontext, require("("), require("t_string"), require("variable"), require(")"), statement, poplex, popcontext);
      else if (type == "const") cont(require("t_string"));  // 'const static x=5' is a syntax error
      // technically, "namespace implode {...}" is correct, but we'll flag that as an error because it overrides a predefined function. FIXME: should be a warning
      // FIXME: namespaces can contain double-colons.
      else if (type == "namespace") cont(require("t_string"));
      else if (type == "variable") cont(maybeoperator, require(";"));
      else pass(pushlex("stat"), expression, expect(";"), poplex);
    }
    // Dispatch expression types.
    function expression(type){
      if (atomicTypes.hasOwnProperty(type)) cont(maybeoperator);
      else if (type == "keyword c") cont(expression);
      else if (type == "(") cont(pushlex(")"), expression, expect(")"), poplex);
      else if (type == "operator") cont(expression);
      else if (type == "[") cont(pushlex("]"), commasep(expression), expect("]"), poplex);
    }
    // Called for places where operators, function calls, or
    // subscripts are valid. Will skip on to the next action if none
    // is found.
    function maybeoperator(type){
      if (type == "operator") cont(expression);
      else if (type == "(") cont(pushlex(")"), expression, commasep(expression), expect(")"), poplex);
      else if (type == "[") cont(pushlex("]"), expression, expect("]"), poplex);
    }
    // the declaration or definition of a function
    function funcdef() {
      cont(require("t_string"), require("("), commasep(funcarg), require(")"), statement);
    }
    // Parses a comma-separated list of the things that are recognized
    // by the 'what' argument.
    function commasep(what){
      function proceed(type) {
        if (type == ",") cont(what, proceed);
      };
      return function() {
        pass(what, proceed);
      };
    }
    // Look for statements until a closing brace is found.
    function block(type) {
      if (type == "}") cont();
      else pass(statement, block);
    }
    function maybedefaultparameter(type, value){
      if (value == "=") cont(expression);
    }
    // support for default arguments: http://us.php.net/manual/en/functions.arguments.php#functions.arguments.default
    function funcarg(type, value){
      // function foo(myclass $obj) {...}
      if (type == "t_string") cont(require("variable"), maybedefaultparameter);
      // function foo($string) {...}
      else if (type == "variable") cont(maybedefaultparameter);
    }

    return parser;
  }

  return {make: parsePHP, electricChars: "{}"};
})();
