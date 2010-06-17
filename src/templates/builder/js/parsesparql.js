Editor.Parser = (function() {
  function wordRegexp(words) {
    return new RegExp("^(?:" + words.join("|") + ")$", "i");
  }
  var ops = wordRegexp([":=", "<", "<=", "==", "<>", ">", ">=", "like", "rlike", "in", "xor", "between"]);
  var keywords = wordRegexp(["alter", "grant", "revoke", "primary", "key", "table", "start",
                             "transaction", "select", "update", "insert", "delete", "create", "describe",
                             "from", "into", "values", "where", "join", "inner", "left", "natural", "and",
                             "or", "in", "not", "xor", "like", "using", "on", "order", "group", "by",
                             "asc", "desc", "limit", "offset", "union", "all", "as", "distinct", "set",
                             "commit", "rollback", "replace", "view", "database", "separator", "if",
                             "exists", "null", "truncate", "status", "show", "lock", "unique"]);
  var functions = wordRegexp([
                              "abs", "acos", "adddate", "aes_encrypt", "aes_decrypt", "ascii",
                              "asin", "atan", "atan2", "avg", "benchmark", "bin", "bit_and",
                              "bit_count", "bit_length", "bit_or", "cast", "ceil", "ceiling",
                              "char_length", "character_length", "coalesce", "concat", "concat_ws",
                              "connection_id", "conv", "convert", "cos", "cot", "count", "curdate",
                              "current_date", "current_time", "current_timestamp", "current_user",
                              "curtime", "database", "date_add", "date_format", "date_sub",
                              "dayname", "dayofmonth", "dayofweek", "dayofyear", "decode", "degrees",
                              "des_encrypt", "des_decrypt", "elt", "encode", "encrypt", "exp",
                              "export_set", "extract", "field", "find_in_set", "floor", "format",
                              "found_rows", "from_days", "from_unixtime", "get_lock", "greatest",
                              "group_unique_users", "hex", "ifnull", "inet_aton", "inet_ntoa", "instr",
                              "interval", "is_free_lock", "isnull", "last_insert_id", "lcase", "least",
                              "left", "length", "ln", "load_file", "locate", "log", "log2", "log10",
                              "lower", "lpad", "ltrim", "make_set", "master_pos_wait", "max", "md5",
                              "mid", "min", "mod", "monthname", "now", "nullif", "oct", "octet_length",
                              "ord", "password", "period_add", "period_diff", "pi", "position",
                              "pow", "power", "quarter", "quote", "radians", "rand", "release_lock",
                              "repeat", "reverse", "right", "round", "rpad", "rtrim", "sec_to_time",
                              "session_user", "sha", "sha1", "sign", "sin", "soundex", "space", "sqrt",
                              "std", "stddev", "strcmp", "subdate", "substring", "substring_index",
                              "sum", "sysdate", "system_user", "tan", "time_format", "time_to_sec",
                              "to_days", "trim", "ucase", "unique_users", "unix_timestamp", "upper",
                              "user", "version", "week", "weekday", "yearweek"
                            ]);
  var types = wordRegexp([
                          "bigint", "binary", "bit", "blob", "bool", "char", "character", "date",
                          "datetime", "dec", "decimal", "double", "enum", "float", "float4", "float8",
                          "int", "int1", "int2", "int3", "int4", "int8", "integer", "long", "longblob",
                          "longtext", "mediumblob", "mediumint", "mediumtext", "middleint", "nchar",
                          "numeric", "real", "set", "smallint", "text", "time", "timestamp", "tinyblob",
                          "tinyint", "tinytext", "varbinary", "varchar", "year"
                        ]);  
  var operatorChars = /[*+\-<>=&|]/;

  var tokenizeSparql = (function() {
    function normal(source, setState) {
      var ch = source.next();
      if (ch == "$" || ch == "?") {
        source.nextWhile(matcher(/[\w\d]/));
        return "sp-var";
      }
      else if (ch == "<" && !source.applies(matcher(/[\s\u00a0=]/))) {
        source.nextWhile(matcher(/[^\s\u00a0>]/));
        if (source.equals(">")) source.next();
        return "sp-uri";
      }
      else if (ch == "\"" || ch == "'") {
        setState(inLiteral(ch));
        return null;
      }
      else if (/[{}\(\),\.;\[\]]/.test(ch)) {
        return "sp-punc";
      }
      else if (ch == "#") {
        while (!source.endOfLine()) source.next();
        return "sp-comment";
      }
      else if (operatorChars.test(ch)) {
        source.nextWhile(matcher(operatorChars));
        return "sp-operator";
      }
      else if (ch == ":") {
        source.nextWhile(matcher(/[\w\d\._\-]/));
        return "sp-prefixed";
      }
      else {
        source.nextWhile(matcher(/[_\w\d]/));
        if (source.equals(":")) {
          source.next();
          source.nextWhile(matcher(/[\w\d_\-]/));
          return "sp-prefixed";
        }
        var word = source.get(), type;
        if (ops.test(word))
          type = "sp-operator";
        else if (keywords.test(word))
          type = "sp-keyword";
        else if (functions.test(word))
        	type = "sp-function";
        else if (types.test(word))
        	type = "sp-type";
        else
          type = "sp-word";
        return {style: type, content: word};
      }
    }

    function inLiteral(quote) {
      return function(source, setState) {
        var escaped = false;
        while (!source.endOfLine()) {
          var ch = source.next();
          if (ch == quote && !escaped) {
            setState(normal);
            break;
          }
          escaped = ch == "\\";
        }
        return "sp-literal";
      };
    }

    return function(source, startState) {
      return tokenizer(source, startState || normal);
    };
  })();

  function indentSparql(context) {
    return function(nextChars) {
      var firstChar = nextChars && nextChars.charAt(0);
      if (/[\]\}]/.test(firstChar))
        while (context && context.type == "pattern") context = context.prev;

      var closing = context && firstChar == matching[context.type];
      if (!context)
        return 0;
      else if (context.type == "pattern")
        return context.col;
      else if (context.align)
        return context.col - (closing ? context.width : 0);
      else
        return context.indent + (closing ? 0 : 2);
    }
  }

  function parseSparql(source) {
    var tokens = tokenizeSparql(source);
    var context = null, indent = 0, col = 0;
    function pushContext(type, width) {
      context = {prev: context, indent: indent, col: col, type: type, width: width};
    }
    function popContext() {
      context = context.prev;
    }

    var iter = {
      next: function() {
        var token = tokens.next(), type = token.style, content = token.content, width = token.value.length;

        if (content == "\n") {
          token.indentation = indentSparql(context);
          indent = col = 0;
          if (context && context.align == null) context.align = false;
        }
        else if (type == "whitespace" && col == 0) {
          indent = width;
        }
        else if (type != "sp-comment" && context && context.align == null) {
          context.align = true;
        }

        if (content != "\n") col += width;

        if (/[\[\{\(]/.test(content)) {
          pushContext(content, width);
        }
        else if (/[\]\}\)]/.test(content)) {
          while (context && context.type == "pattern")
            popContext();
          if (context && content == matching[context.type]) 
            popContext();
        }
        else if (content == "." && context && context.type == "pattern") {
          popContext();
        }
        else if ((type == "sp-word" || type == "sp-prefixed" || type == "sp-uri" || type == "sp-var" || type == "sp-literal") &&
                 context && /[\{\[]/.test(context.type)) {
          pushContext("pattern", width);
        }

        return token;
      },

      copy: function() {
        var _context = context, _indent = indent, _col = col, _tokenState = tokens.state;
        return function(source) {
          tokens = tokenizeSparql(source, _tokenState);
          context = _context;
          indent = _indent;
          col = _col;
          return iter;
        };
      }
    };
    return iter;
  }

  return {make: parseSparql, electricChars: "}]"};
})();
