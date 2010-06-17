/* Copyright (c) 2008 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content of this file are licensed under the BSD
 * open source license
 *
 * @author Dan Vlad Dascalescu <dandv@yahoo-inc.com>
 */

var PHPHTMLMixedParser = Editor.Parser = (function() {
  if (!(PHPParser && CSSParser && JSParser && XMLParser))
    throw new Error("PHP, CSS, JS, and XML parsers must be loaded for PHP+HTML mixed mode to work.");
  XMLParser.configure({useHTMLKludges: true});

  function stringAhead(stream, string) {
    stream.nextWhile(matcher(/[\s\u00a0]/));
    var found = stream.matches(string, false);
    stream.reset();
    return found;
  }

  function parseMixed(stream) {
    var htmlParser = XMLParser.make(stream), localParser = null, inTag = false;
    var iter = {next: top, copy: copy};

    function top() {
      var token = htmlParser.next();
      if (token.content == "<")
        inTag = true;
      else if (token.style == "xml-tagname" && inTag === true)
        inTag = token.content.toLowerCase();
      else if (token.type == "xml-processing-start") {
        // dispatch on PHP or XML or others
        if (token.content == "<?php")
          iter.next = local(PHPParser, "?>");    
        else if (token.content == "<?xml")
          ; // ignore it
        else
          iter.next = local(PHPParser, "?>");
          //alert("Unsupported processing instruction: [" + token.content + ']');
      }
      // "xml-processing" tokens are ignored, because they should be handled by a specific local parser
      else if (token.content == ">") {
        if (inTag == "script")
          iter.next = local(JSParser, "</script");
        else if (inTag == "style")
          iter.next = local(CSSParser, "</style");
        inTag = false;
      }
      return token;
    }
    function local(parser, tag) {
      localParser = parser.make(stream, htmlParser.indentation() + 2);
      return function() {
        if (stringAhead(stream, tag)) {
          localParser = null;
          iter.next = top;
          return top();
        }
        var token = localParser.next();
        // stop when encountering the end tag
        var lt = token.value.lastIndexOf("<"), sz = Math.min(token.value.length - lt, tag.length);
        if (lt != -1 && token.value.slice(lt, lt + sz).toLowerCase() == tag.slice(0, sz) && stringAhead(stream, tag.slice(sz))) {
          stream.push(token.value.slice(lt));
          token.value = token.value.slice(0, lt);
        }
        return token;
      };
    }

    function copy() {
      var _html = htmlParser.copy(), _local = localParser && localParser.copy(),
          _next = iter.next, _inTag = inTag;
      return function(_stream) {
        stream = _stream;
        htmlParser = _html(_stream);
        localParser = _local && _local(_stream);
        iter.next = _next;
        inTag = _inTag;
        return iter;
      };
    }
    return iter;
  }

  return {make: parseMixed, electricChars: "{}/"};
})();