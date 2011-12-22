window.requestAnimFrame = (function(){
      return  window.requestAnimationFrame       || 
              window.webkitRequestAnimationFrame || 
              window.mozRequestAnimationFrame    || 
              window.oRequestAnimationFrame      || 
              window.msRequestAnimationFrame     || 
              function( callback ){
                window.setTimeout(callback, 1000 / 60);
              };
    })();

var Timer = function(id, fn, time) {
	var me = this;
	if (!Timer.fns) Timer.fns = {};
	var res = {};
	for (var all in Timer.fns) {
		if (all == id) {
			Timer.fns[all].timer.cancel();
		} else {
			res[all] = Timer.fns[all];
		}
	}
	Timer.fns = res;
	this.cancel = function() {
		clearTimeout(me.res);
	}
	this.res = setTimeout(function() {
		fn();
		var res = {};
		for (var all in Timer.fns) {
			if (id != all) {
				res[all] = Timer.fns[all];
			}
		}
		Timer.fns = res;
	}, time);

	Timer.fns[id] = {fn: fn, timer: this};
	
	return this;
};

function drawCursor(el, updateScrolling) {
	var cnt = el.value.substr(el.selectionStart, el.selectionEnd - el.selectionStart);
	var before = el.value.substr(0, el.selectionStart);
	var after = el.value.substr(el.selectionEnd);
	el._lines = before.split("\n");
	var currentLine = el._lines.length - 1;
	var currentCol = (el._lines.length > 0 ? el._lines[el._lines.length - 1].length + 1 : before.length+1) - 1;
	el.lines = el._lines;
	var cll = el.value.indexOf("\n", el.selectionStart);
	el.lines[el.lines.length - 1] += el.value.substring(el.selectionStart, cll === -1 ? el.value.length: cll);

	el.currentCol = currentCol;
	el.currentLine = currentLine;
	el.before = before;
	el.after = after;

	var it = document.querySelectorAll(".syntaxhighlighter .line");
	for (var i = 0, len = it.length; i< len; i++) {
		if (it[i].className.indexOf("currentLine") >= 0) {
			it[i].className = it[i].className.replace(/\s*currentLine\s*/g, '');
		}
		if (el.hasFocus && el.selectionStart == el.selectionEnd && it[i].className.indexOf("index"+currentLine) >= 0) {
			it[i].className += " currentLine";
		}
	}
	if (el.hasFocus && options.indexOf('php')) {
		var sel = document.querySelectorAll(".highlight");
		for (var i = 0, len = sel.length; i < len; i++) {
			sel[i].parentNode.removeChild(sel[i]);
		}
		var varName = before.match(/(\$[a-zA-Z0-9_]+)$/gi);
		if (varName && varName.length > 0) {
			var p = after.match(/^[a-zA-Z0-9_]*\b/gi);
			varName = varName[0] + (p && p[0]);
			var pos = el.value.indexOf(varName);
			while (pos >= 0) {
				var hl = document.createElement("DIV");
				hl.className = "highlight";
				var cc = el.value.substr(0, pos);
				var ccs = cc.split("\n");
				var cl = ccs.length - 1;
				cc = (ccs.length > 0 ? ccs[ccs.length-1].length+1 : cc.length+1) - 1;
				hl.style.left = (cc * 7) + "px";
				hl.style.top = ((cl * 13) - 2) + "px";
				hl.style.width = (varName.length * 7)+"px";
				var cwrapper = document.getElementById("cwrapper");
				cwrapper.appendChild(hl);
				pos = el.value.indexOf(varName, pos + varName.length);
			}
		}
	}
	if (el.selectionStart == el.selectionEnd && el.hasFocus) {
		document.getElementById("cursor").style.display = "block";
		document.getElementById("cursor").style.left = (currentCol * 7) + "px";
		document.getElementById("cursor").style.top = (currentLine * 13) + "px";
		var sel = document.querySelectorAll(".selection");
		if (sel.length > 0) {
			for (var i = 0, len=sel.length; i < len; i++) {
				sel[i].parentNode.removeChild(sel[i]);
			}
		}
	} else if (el.hasFocus) {
		var sel = document.querySelectorAll(".selection");
		if (sel.length > 0) {
			for (var i = 0, len=sel.length; i < len; i++) {
				sel[i].parentNode.removeChild(sel[i]);
			}
		}
		document.getElementById("cursor").style.display = "none";
		var cwrapper = document.getElementById("cwrapper");
		var lines = cnt.split(/\n/);
		for (var i = 0, len = lines.length; i < len; i++) {
			var sel = document.createElement("div");
			sel.className = "selection";
			if (i == 0) {
				sel.style.left = (currentCol * 7) + "px";
				sel.style.top = (currentLine * 13) + "px";
			} else {
				sel.style.left = "0px";
				sel.style.top = ((currentLine + i) * 13) + "px";
			}
			sel.style.width = (lines[i].length * 7) + "px" ;
			cwrapper.appendChild(sel);
		}
	}	

	if (updateScrolling !== false && !el.mousepressed) {
		new Timer("selection-scroller", function() {
			if (document.querySelector("#cwrapper .selection")) {
				var sel = document.querySelectorAll("#cwrapper .selection");
				sel = sel[sel.length - 1];
				document.body.scrollTop = parseInt(sel.style.top) - (document.body.clientHeight / 2);
				document.body.scrollLeft = (parseInt(sel.style.left) + sel.clientWidth) - (document.body.clientWidth / 2);
			} else if (document.getElementById("cursor") && document.getElementById("cursor").style.display != "none") {
				document.body.scrollTop = parseInt(document.getElementById("cursor").style.top) - (document.body.clientHeight / 2);
				document.body.scrollLeft = parseInt(document.getElementById("cursor").style.left) - (document.body.clientWidth / 2);
			}
		}, 100);
	}
};

requestAnimFrame(cursorHint = function() {
	var el = window.txt;
	if (!el.lastPos) el.lastPos = [];
	if (el.lastPos[0] != el.selectionStart || el.lastPos[1] != el.selectionEnd) {
		el.lastPos[0] = el.selectionStart;
		el.lastPos[1] = el.selectionEnd;
		
		drawCursor(el);
	}
	requestAnimFrame(cursorHint);
});

requestAnimFrame(globalRefresh = function() {
	if (txt.refreshWanted && typeof(txt.currentLine) !== "undefined" && document.getElementsByClassName("container")[0].childNodes[txt.currentLine]) {
		txt.refreshWanted = false;
		document.getElementsByClassName("container")[0].childNodes[txt.currentLine].innerHTML = txt.currentHighlighter.parseCode(txt.lines, txt.currentLine + 1);
	}
	requestAnimFrame(globalRefresh);
});

function refresh(code, currentLine, replaceit) {
	var fullrefresh = replaceit === false; 
	if (!replaceit) {
		code = code.replace(/&([#a-zA-Z][a-zA-Z0-9]+);/gi, "&amp;$1;").replace(/</gi, "&lt;").replace(/>/gi, "&gt;");
	}

	var currentHighlighter = txt.currentHighlighter;
	if (!currentHighlighter) {
		for (var all in SyntaxHighlighter.vars.highlighters) {
			currentHighlighter = SyntaxHighlighter.vars.highlighters[all];
			break;
		}
	}
	if (currentHighlighter) {
		txt.currentHighlighter = currentHighlighter;
		if (typeof(txt.currentLine) !== "undefined" && document.getElementsByClassName("container")[0].childNodes[txt.currentLine]) {
			document.getElementsByClassName("container")[0].childNodes[txt.currentLine].innerHTML = txt.lines[txt.currentLine].replace(/&([#a-zA-Z][a-zA-Z0-9]+);/gi, "&amp;$1;").replace(/</gi, "&lt;").replace(/>/gi, "&gt;");
		}
		txt.refreshWanted = true;

		if (fullrefresh) {
			Timer && Timer.fns && Timer.fns["global-refresh"] && Timer.fns["global-refresh"].timer && Timer.fns["global-refresh"].timer.cancel();
			document.getElementsByClassName("container")[0].innerHTML = currentHighlighter.parseCode(code);
		} else {
			new Timer("global-refresh", function() {
				document.getElementsByClassName("container")[0].innerHTML = currentHighlighter.parseCode(code);
			}, 1000);
		}
		new Timer("gutter-update", function() {
			document.getElementsByClassName("gutter")[0].innerHTML = currentHighlighter.getLineNumbersHtml(code);
		}, 75);
	} else {
		var b = document.getElementById("highlight").firstChild;
		var d = document.createElement("pre");
		d.innerHTML = code;
		d.className = window.options;
		if (!b) {
			b = document.getElementById("highlight");
			b.appendChild(d);
		} else {
			var c = b.parentNode;
			c.replaceChild(d, b);
		}
		SyntaxHighlighter.vars.highlighters = {};
		SyntaxHighlighter.highlight({toolbar: false}, d);
		window.cel = document.getElementsByClassName("code")[1];	
	}
};

var handleIncDec = function(el, e) {
	var a = [el.selectionStart, el.selectionEnd];
	var before = el.before;
	if (!before) before = el.before = el.value.substr(0, el.selectionStart);
	var after = el.after;
	if (!after) after = el.after = el.value.substr(el.selectionEnd);
	var offset = 1;
	if (el.selectionStart != el.selectionEnd) {
		// tab is pressed with actual selection active...
		var content = el.value.substr(el.selectionStart, el.selectionEnd - el.selectionStart);
		var lines = content.split("\n");
		for (var i = 0; i < lines.length; i++) {
			if (e.shiftKey) {
				lines[i] = lines[i].replace(/^ {4}/, '');
			} else {
				lines[i] = "    " + lines[i];
			}
		}
		offset = lines.length;
		el.value = before + lines.join("\n") + after;
	} else {
		// first find the current line
		var lastN = before.search(/\n\s*[^\n]+$/gi);
		if (lastN >= 0) {
			after = before.substr(lastN + 1) + after;				
			before = before.substr(0, lastN) + "\n";
		}
		if (e.shiftKey) {
			el.value = before + after.replace(/^ {4}/, '');
		} else {
			el.value = before + "    " + after;
		}
	}
	el.selectionStart = a[0] + (e.shiftKey ? 0 : (offset > 1 ? 0 : 4));
	el.selectionEnd = a[1] + (e.shiftKey ? -4 * offset : 4 * offset);
};

var handleAutoComplete = function(el, e) {
	// first remove all current dialogs...
	var ds = document.querySelectorAll("ul.dialog");
	for (var i = 0; i < ds.length; i++) {
		document.getElementById("cwrapper").removeChild(ds[i]);
	}
	
	var before = el.before;
	if (!before) before = el.before = el.value.substr(0, el.selectionStart);
	var activeList = [];
	for (var i = 0; i < window.keywords.length; i++) {
		var itemList = (typeof(window.keywords[i].items) === "function" && window.keywords[i].items(el)) || window.keywords[i].items;
		itemList.sort();
		if (window.keywords[i].prefix) {
			if (before.search(new RegExp(window.keywords[i].prefix+"$", "gi")) >= 0) {
				for (var j = 0; j < itemList.length; j++) {
					var item = itemList[j]; 
					activeList.push({text: item, pos: 0});
				}
			} else {
				for (var j = 0; j < window.keywords[i].items.length; j++) {
					var item = itemList[j]; 
					for (var k = item.length; k > 1; k--) {
						if (before.search(new RegExp(window.keywords[i].prefix+item.substr(0, k - 1).replace('$', '\\$').replace('(', "\\(").replace(')', "\\)")+"$", "gi")) >= 0) {
							activeList.push({text: item, pos: k - 1});
							break;
						}
					}
				}
			}
		} else {
			for (var j = 0; j < itemList.length; j++) {
				var item = itemList[j]; 
				for (var k = item.length; k > 1; k--) {
					if (before.search(new RegExp("[^a-zA-Z0-9$]+"+item.substr(0, k - 1).replace('$', '\\$').replace('(', "\\(").replace(')', "\\)")+"$", "gi")) >= 0) {
						activeList.push({text: item, pos: k - 1});
						break;
					}
				}
			}							
		}
	}
	if (activeList.length == 1) {
		// just add it at current position
		insertAt(el, activeList[0].pos, activeList[0].text);
		el.dialogOpen = false;		
	} else if (activeList.length > 1) {
		// render selection dialog...			
		var currentLine = el.currentLine || before.split(/\n/).length;
		var currentCol = el.currentCol || (before.indexOf("\n") >= 0 ? before.match(/\n[^\n]+$/gi)[0].length : before.length);
		var dialog = document.createElement("ul");
		dialog.className = "dialog";
		for (var i = 0; i < activeList.length; i++) {
			var li = document.createElement("li");
			if (i == 0) li.className = "active";
			li.id = "dialog-p_"+activeList[i].pos;
			li.innerHTML = activeList[i].text.replace(/&([#a-zA-Z][a-zA-Z0-9]+);/gi, "&amp;$1;").replace(/</gi, "&lt;").replace(/>/gi, "&gt;");
			dialog.appendChild(li);
		}
		dialog.style.left = currentCol+"ex";
		dialog.style.top = currentLine+"em";
		document.getElementById("cwrapper").appendChild(dialog);
		el.dialogOpen = dialog;
	}
};

var insertAt = function(el, pos, text) {
	var a = [el.selectionStart, el.selectionEnd];
	var before = el.before || (el.before = el.value.substr(0, el.selectionStart));
	var after = el.after || (el.after = el.value.substr(el.selectionStart));
	el.value = before.substr(0, before.length - pos) + text + after;
	el.selectionStart = a[0] + (text.length - pos);
	el.selectionEnd = el.selectionStart;
};

window.txt = document.createElement("textarea");

txt.onkeydown = txt.onkeyup = function(e) {
	if (!this.undoStack) {
		this.undoStack = [];
	}
	var fullrefresh = null;
	if (!this.hasFocus) {
		this.hasFocus = true;
		drawCursor(this, false);
	}
	var stop = false;
	window.keypressed = true;
	if (e.keyCode == 27 && this.dialogOpen) {
		var el = document.querySelector("ul.dialog");
		document.getElementById("cwrapper").removeChild(el);
		this.dialogOpen = false;
		return false;
	}
	if (e.keyCode == 37 || e.keyCode == 38 || e.keyCode == 39 || e.keyCode == 40) {
		if (this.dialogOpen && (e.keyCode == 38 || e.keyCode == 40)) {
			if (e.type == "keydown") return false;
			var el = document.querySelector("ul.dialog li.active");
			if (e.keyCode == 38 && el.previousSibling) {
				el.previousSibling.className = "active";
				el.className = "";
			} else if (e.keyCode == 40 && el.nextSibling) {
				el.nextSibling.className = "active";
				el.className = "";
			}
			return false;
		} 
		if (e.keyCode == 37 && (e.metaKey || e.ctrlKey) && !e.shiftKey) {
			var before = this.before || (this.before = this.value.substr(0, this.selectionStart));
			var after = this.after || (this.after = this.value.substr(this.selectionEnd));
			var lastN = before.search(/\n\s*[^\n]+$/gi);
			if (lastN >= 0) {
				this.selectionStart = lastN + 1;
				this.selectionEnd = lastN + 1;
			} else {
				if (before.search(/\n/gi) >= 0) {
					var match = after.match(/^(\s*)[^\n]+/gi);
					this.selectionStart += RegExp.$1.length;
					this.selectionStart = this.selectionEnd;
				} else {
					this.selectionStart = 0;
					this.selectionEnd = 0;						
				}
			}
			return false;
		}
		return true;
	} 
	if (this.scrollTop > 0) {
		this.style.height = (this.scrollHeight + 20) + "px";
	}
	
	if (e.ctrlKey || e.metaKey) {
		switch (e.keyCode) {
			case 70: 
				this.search();
				break;
			case 90: // handle undo/redo
				if (e.type == "keydown") stop = true;
				if (!e.shiftKey && this.undoStack && this.undoStack.length > 0) {
					var entry = this.undoStack.pop();
					this.value = entry[0];
					this.selectionStart = entry[1];
					this.selectionEnd = entry[2];
					if (!this.redoStack) this.redoStack = [];
					this.redoStack.push(entry);
				} else if (e.shiftKey && this.redoStack && this.redoStack.length > 0) {
					var entry = this.redoStack.pop();
					this.value = entry[0];
					this.selectionStart = entry[1];
					this.selectionEnd = entry[2];
					if (!this.undoStack) this.undoStack = [];
					this.undoStack.push(entry);
				}
				refresh(this.value, this.currentLine, false);
				break; 
			case 86:	// handle paste
				var me = this;
				setTimeout(function() {
					me.value = me.value.replace(/\t/g, '    ');
					me.undoStack.push([me.value, me.selectionStart, me.selectionEnd]);			
					refresh(me.value, this.currentLine, false);
				}, 10);
				return true;
			case 83:
				this.save();
				break;
			case 88:
				if (e.shiftKey) {
					try { this.run(); } catch(e){window.console && console.log(e);};
				} else {
					setTimeout(function() {
						refresh(me.value, this.currentLine, false);
					}, 10);
					return true;
				}
				break;
			case 32:
				handleAutoComplete(this, e);
				break;
			case 65:  // select all
				this.selectionStart = 0;
				this.selectionEnd = this.value.length;
				break;
			default:
				return true;
		}
		return false;
	}
			
	if (e.keyCode == 9) {
		stop = true;
		if (e.type == "keyup") {
			handleIncDec(this, e);
			fullrefresh = false;
		}
	}
	if (e.keyCode == 8 || e.keyCode == 46) {
		fullrefresh = false;
	}

	if (e.keyCode == 13) {
		if (e.type == "keydown") return false;
		if (this.dialogOpen) {
			var el = document.querySelector("ul.dialog li.active");
			var pos = parseInt(el.id.replace("dialog-p_", ""), 10);
			insertAt(this, pos, el.innerHTML.replace(/&lt;/gi, "<").replace(/&gt;/gi, ">").replace(/&amp;/gi, "&"));
			document.getElementById("cwrapper").removeChild(el.parentNode);
			this.dialogOpen = false;
		} else {
			var before = this.before || (this.before = this.value.substr(0, this.selectionStart));
			var simpleBefore = before.replace(/\s*/gi, '');
			var prevLine = before.search(/\n +[^\n]+$/gi) + 1;
			var add = "";
			if (prevLine > 0) {
				before.match(/\n( +)[^\n]+$/gi);
				add = RegExp.$1;
			}
			var after = this.after || (this.after = this.value.substr(this.selectionEnd));
			
			// detect PHP & JS block start
			var oadd = add;
			this.value = before + "\n" + add + after;
			this.selectionStart = before.length + 1 + add.length;
			this.selectionEnd = before.length + 1 + add.length;
			refresh(this.value, this.currentLine + 1, false);
		}
		this.undoStack.push([this.value, this.selectionStart, this.selectionEnd]);
		stop = true;
	}
	
	me = this;
	new Timer("refreshing", function() {
		refresh(me.value, me.currentLine, fullrefresh);
		new Timer("resizing", function() {
			//me.style.height = (me.scrollHeight + 20) + "px";
			window.txt.parentNode.style.width = (window.cel.clientWidth - window.coffset)+"px";
			window.txt.style.width = (window.cel.clientWidth + 2)+"px";	
//			document.getElementsByClassName("syntaxhighlighter")[0].scrollLeft = (txt.clientWidth + document.querySelector(".syntaxhighlighter .gutter").clientWidth) - document.getElementsByClassName("syntaxhighlighter")[0].clientWidth;
		}, 1);
	}, 5);
	if (stop) {
		return false;
	}
	if (this.undoStack && this.undoStack[this.undoStack.length - 1] && this.value != this.undoStack[this.undoStack.length - 1][0]) {
		while (this.undoStack.length >= 1000) {
			this.undoStack.shift();
		}
		this.undoStack.push([this.value, this.selectionStart, this.selectionEnd]);
	}
};
txt.onscroll = function() {
	txt.style.height = (txt.scrollHeight + 20) + "px";
	txt.style.width = window.cel.clientWidth+"px";
	txt.parentNode.style.width = (window.cel.clientWidth - window.coffset)+"px";
	return false;
};

(function() {
  if (navigator.userAgent.indexOf("Gecko/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " firefox";
  if (navigator.userAgent.indexOf("Presto/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " opera";
  if (navigator.userAgent.indexOf(" MSIE") > 0) {
    document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " msie";
    version=Math.floor(parseFloat(/\s+MSIE\s*([0-9.]+);/.exec(navigator.userAgent)[1]));
    document.getElementsByTagName("html")[0].className += " msie"+version;
  }

  if (navigator.userAgent.indexOf("AppleWebKit/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " webkit";
})();

window.onload = function() { 
	document.getElementById("cwrapper").appendChild(txt);
	var cursor = document.createElement("div");
	cursor.id = "cursor";
	document.getElementById('cwrapper').appendChild(cursor);
	typeof(window.init) == "function" && window.init();

	window.coffset = 0;
	switch (document.getElementsByTagName("html")[0].className.replace(/^\s+|\s+$/gi, '')) {
		case "firefox":
			window.coffset = 1;
			window.toffset = 2;
			break;
		case "opera":
			window.coffset = 2;
			window.toffset = 0;
			break;
		default:
			window.coffset = 2;
			window.toffset = 0;
		break;
	}
	window.cel = document.getElementsByClassName("code")[1];
	setTimeout(window.updateSize = function() {
		txt.parentNode.style.width = (cel.clientWidth - window.coffset)+"px";
		txt.parentNode.parentNode.style.width = document.getElementsByTagName("table")[0].clientWidth+"px";
		txt.style.width = (cel.clientWidth + window.toffset)+"px";
		txt.style.height = (txt.scrollHeight) + "px";
		setTimeout(window.updateSize, 1500);
	}, 150);
//	txt.value = txt.value.replace(/^\s+/g, '');
	refresh(txt.value, 1);

	window.txt.onmousedown = function(e) {
		this.mousepressed = true;
	};
	window.txt.onmouseup = function(e) {
		this.mousepressed = false;
	};
	window.txt.onfocus = function(e) {
		this.hasFocus = true;
		drawCursor(this, false);
	};

	window.txt.onblur = function(e) {
		document.getElementById("cursor").style.display = "none";
    	this.sel = [this.selectionStart, this.selectionEnd];
		window.txt.hasFocus = false;
	}	
};