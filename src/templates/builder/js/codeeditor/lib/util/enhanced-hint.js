(function() {
  CodeMirror.enhancedHint = function(editor, getHints) {
    // We want a single cursor position.
    if (editor.somethingSelected()) return;
    var result = getHints(editor);
    if (!result || !result.list.length) return;
    var completions = result.list;
    function insert(str) {
      editor.replaceRange(str, result.from, result.to);
    }
    // Build the select widget
    if (oldComplete = document.getElementById("CodeMirror-completions-id")) {
    	oldComplete.parentNode.removeChild(oldComplete);
    }
    // When there is only one completion, use it directly.
    if (completions.length == 1) {insert(typeof(completions[0]) == "string" ? completions[0] : completions[0].value); return true;}

    var complete = document.createElement("div");
    complete.className = "CodeMirror-completions";
    complete.id = "CodeMirror-completions-id";
    var inp = complete.appendChild(document.createElement("input"));
    inp.style.opacity = "0";
    inp.style.position = "absolute";
    var sel = complete.appendChild(document.createElement("ul"));
    for (var i = 0; i < completions.length; ++i) {
      var opt = sel.appendChild(document.createElement("li"));
      if (typeof(completions[i]) == "string") {
    	  opt.appendChild(document.createTextNode(completions[i]));
      } else {
    	  opt.appendChild(document.createTextNode(completions[i].displayValue));
    	  opt.setAttribute("value", completions[i].value);
      }
      opt.onmousedown = function() {
    	  sel.querySelector("li.selected").className = "";
    	  this.className = "selected";
    	  inp.focus();
    	  return false;
      };
    }
    sel.firstChild.className = "selected";
    var pos = editor.cursorCoords();
    complete.style.left = pos.x + "px";
    complete.style.top = pos.yBot + "px";
    document.body.appendChild(complete);

    var done = false;
    function close() {
      if (done) return;
      done = true;
      var c = document.getElementById("CodeMirror-completions-id");
      if (c) {
    	  c.parentNode.removeChild(c);
      }
    }
    function pick() {
    	var value = sel.querySelector("li.selected").getAttribute("value") || sel.querySelector("li.selected").innerHTML.replace(/&gt;/g, '>').replace(/&lt;/g, "<").replace(/&amp;/g, "&");
    	insert(value);
    	close();
    	setTimeout(function(){editor.focus();}, 50);
    }
    CodeMirror.connect(inp, "blur", close);
    CodeMirror.connect(inp, "keydown", function(event) {
      var code = event.keyCode;
      // Enter
      if (code == 13) {CodeMirror.e_stop(event); pick(); }
      // Escape
      else if (code == 27) {CodeMirror.e_stop(event); close(); editor.focus();}
      else if (code != 38 && code != 40) {
    	  close(); editor.focus();
    	  setTimeout(function(){CodeMirror.enhancedHint(editor, getHints);}, 50);
      } else {
    	  CodeMirror.e_stop(event);
    	  var el = sel.querySelector("li.selected");
    	  el.className = "";
    	  if (code == 38) {
    		  (el.previousSibling || sel.children[sel.children.length - 1]).className = "selected";    		  
    	  } else {
    		  (el.nextSibling || sel.firstChild).className = "selected";
    	  }
      }
    });
    CodeMirror.connect(sel, "dblclick", pick);

    inp.focus();
    // Opera sometimes ignores focusing a freshly created node
    if (window.opera) setTimeout(function(){if (!done) inp.focus();}, 100);
    return true;
  };
})();
