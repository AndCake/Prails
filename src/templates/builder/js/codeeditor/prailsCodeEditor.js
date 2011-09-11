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

var refresh = function(code, currentLine) {	
	code = code.replace(/&([#a-zA-Z][a-zA-Z0-9]+);/gi, "&amp;$1;").replace(/</gi, "&lt;").replace(/>/gi, "&gt;");

	var currentHighlighter = null;
	for (var all in SyntaxHighlighter.vars.highlighters) {
		currentHighlighter = SyntaxHighlighter.vars.highlighters[all];
		break;
	}
	if (currentHighlighter) {
		if (document.getElementsByClassName("container")[0].childNodes.length > currentLine - 1) 
			document.getElementsByClassName("container")[0].childNodes[currentLine - 1].innerHTML = currentHighlighter.parseCode(code, currentLine);
		new Timer("global-update", function() {
			document.getElementsByClassName("container")[0].innerHTML = currentHighlighter.parseCode(code);
		}, 100);
		document.getElementsByClassName("gutter")[0].innerHTML = currentHighlighter.getLineNumbersHtml(code);
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
	var before = el.value.substr(0, el.selectionStart);
	var after = el.value.substr(el.selectionEnd);
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
	
	var before = el.value.substr(0, el.selectionStart);
	var activeList = [];
	for (var i = 0; i < window.keywords.length; i++) {
		if (window.keywords[i].prefix) {
			if (before.search(new RegExp(window.keywords[i].prefix+"$", "gi")) >= 0) {
				for (var j = 0; j < window.keywords[i].items.length; j++) {
					activeList.push({text: window.keywords[i].items[j], pos: 0});
				}
			} else {
				for (var j = 0; j < window.keywords[i].items.length; j++) {
					for (var k = window.keywords[i].items[j].length; k > 1; k--) {
						if (before.search(new RegExp(window.keywords[i].prefix+window.keywords[i].items[j].substr(0, k - 1)+"$", "gi")) >= 0) {
							activeList.push({text: window.keywords[i].items[j], pos: k - 1});
							break;
						}
					}
				}
			}
		} else {
			for (var j = 0; j < window.keywords[i].items.length; j++) {
				for (var k = window.keywords[i].items[j].length; k > 1; k--) {
					if (before.search(new RegExp("[^a-zA-Z0-9]+"+window.keywords[i].items[j].substr(0, k - 1)+"$", "gi")) >= 0) {
						activeList.push({text: window.keywords[i].items[j], pos: k - 1});
						break;
					}
				}
			}							
		}
	}
	if (activeList.length == 1) {
		// just add it at current position
		insertAt(el, activeList[0].pos, activeList[0].text);
	} else if (activeList.length > 1) {
		// render selection dialog...			
		var currentLine = before.split(/\n/).length;
		var currentCol = before.indexOf("\n") >= 0 ? before.match(/\n[^\n]+$/gi)[0].length : before.length;
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
	var before = el.value.substr(0, el.selectionStart);
	var after = el.value.substr(el.selectionStart);
	el.value = before.substr(0, before.length - pos) + text + after;
	el.selectionStart = a[0] + (text.length - pos);
	el.selectionEnd = el.selectionStart;
};

window.txt = document.createElement("textarea");

txt.onkeydown = txt.onkeyup = function(e) {
	if (!this.undoStack) {
		this.undoStack = [];
	}
	var stop = false;
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
			var before = this.value.substr(0, this.selectionStart);
			var after = this.value.substr(this.selectionEnd);
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
				return false;
			case 90: 
				stop = true;
				if (this.undoStack && this.undoStack.length > 0) {
					var entry = this.undoStack.pop();
					this.value = entry[0];
					this.selectionStart = entry[1];
					this.selectionEnd = entry[2];
				}
				break;
			case 86:
				var me = this;
				setTimeout(function() {
					me.value = me.value.replace(/\t/g, '    ');
					me.undoStack.push(me.value);			
					refresh(me.value, me.value.substr(0, me.selectionStart).split("\n").length);
				}, 10);
				return true;
			case 83:
				this.save();
				break;
			case 32:
				handleAutoComplete(this, e);
				break;
			case 65:
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
		}
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
			var before = this.value.substr(0, this.selectionStart);
			var simpleBefore = before.replace(/\s*/gi, '');
			var prevLine = before.search(/\n +[^\n]+$/gi) + 1;
			var add = "";
			if (prevLine > 0) {
				before.match(/\n( +)[^\n]+$/gi);
				add = RegExp.$1;
			}
			var after = this.value.substr(this.selectionEnd);
			
			// detect PHP & JS block start
			var oadd = add;
			if (simpleBefore[simpleBefore.length - 1] == '{') {
				add += "    ";
				this.value = before + "\n" + add + "\n" + oadd + "}" + after;
			} else {
				this.value = before + "\n" + add + after;
			}
			this.selectionStart = before.length + 1 + add.length;
			this.selectionEnd = before.length + 1 + add.length;
		}
		this.undoStack.push(this.value);
		stop = true;
	}
	
	me = this;
	new Timer("refreshing", function() {
		refresh(me.value, me.value.substr(0, me.selectionStart).split("\n").length);
		new Timer("resizing", function() {
			//me.style.height = (me.scrollHeight + 20) + "px";
			window.txt.parentNode.style.width = (window.cel.clientWidth - window.coffset)+"px";
			window.txt.style.width = (window.cel.clientWidth + 2)+"px";	
			document.getElementsByClassName("syntaxhighlighter")[0].scrollLeft = (txt.clientWidth + document.querySelector(".syntaxhighlighter .gutter").clientWidth) - document.getElementsByClassName("syntaxhighlighter")[0].clientWidth;
		}, 1);
	}, 5);
	if (stop) {
		return false;
	}
	while (this.undoStack.length >= 1000) {
		this.undoStack.shift();
	}
	this.undoStack.push([this.value, this.selectionStart, this.selectionEnd]);
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
	typeof(window.init) == "function" && window.init();

	window.coffset = 0;
	switch (document.getElementsByTagName("html")[0].className.replace(/^\s+|\s+$/gi, '')) {
		case "firefox":
			window.coffset = 1;
			break;
		case "opera":
			window.coffset = 2;
			break;
		default:
			window.coffset = 2;
			break;
	}
	window.cel = document.getElementsByClassName("code")[1];
	setTimeout(window.updateSize = function() {
		txt.style.height = (txt.scrollHeight + 20) + "px";		
		txt.parentNode.style.width = (cel.clientWidth - window.coffset)+"px";
		txt.parentNode.parentNode.style.width = document.getElementsByTagName("table")[0].clientWidth+"px";
		txt.style.width = (cel.clientWidth + 2)+"px";
	}, 150);
	refresh(txt.value, 1); 
};