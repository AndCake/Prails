/** Section Builder
 * The builder section allows for client-side (javascript) customization of the Prails IDE and thus to 
 * extend it's functionality. In order to do so, you will need to define an event handler to hook into the 
 * `[HookCore]builder-init` hook sink. Your output code will then require to be pure javascript code - _no HTML tags allowed_ .
 **/
Builder = Object.extend(Builder || {}, {
	/**
         * addTab(url, title, id[, icon]) -> void
         * - `url` (String) - the URL to load into the newly formed tab (via AJAX)
         * - `title` (String) - the tab's title
         * - `id` (String) - a unique identifier for the page to be loaded within the tab
         * - `icon` (String) - an icon CSS class that will be used for the small icon next to the tab title.
         *
         * This method will open a new tab in the Prails IDE and load the specified URL into it.
         **/
	addTab: function(url, title, id, icon) {
		var spanel = Ext.getCmp("qwbuilder_startupPanel");
		if (!icon) icon = "folder";
		
		// if the to-be-created tab does not yet exist
		if (spanel.getItem("tab_"+id) === undefined) {
			// add a new tab
			spanel.add({
				id: "tab_"+id,
				title: title,
				closable: true,
				iconCls: icon,
				layout: "border",
				items: [{
					region: "center",
					layout: "fit",
					id: "portlet_content_"+id,
					autoScroll: true,
					tbar: new Ext.Toolbar()
				}]
			});
			
			// set this tab as the active one
			spanel.setActiveTab(spanel.items.length - 1);

			// save tab loading data
			var tab = spanel.getItem("tab_"+id);
			tab.metaDataObj = {
					url: url,
					title: title,
					id: id,
					icon: icon	
			};

			// load it's content
			Ext.getCmp("portlet_content_"+id).load({
				url: url,
				timeout: 30,
				scripts: true
			});
		} else {
			// else just set it active
			spanel.setActiveTab(spanel.getItem("tab_"+id));
		}
	},

        /**
         * reloadTab(id) -> void
         * - `id` (String) - the tab's ID to reload
         * 
         * will refresh the whole tab (by first closing it and then re-opening it again).
         **/	
	reloadTab: function(id) {
		var spanel = Ext.getCmp("qwbuilder_startupPanel");
		var tab = spanel.getItem("tab_"+id);
		var obj = Object.clone(tab.metaDataObj);
		
		spanel.remove("tab_"+id, true);
		
		Builder.addTab(obj.url, obj.title, obj.id, obj.icon);
	},
	
        /**
         * closeTab(id) -> void
         * - `id` (String) - the tab's ID to close
         *
         * closes the specified tab.
         **/
	closeTab: function(id) {
		Ext.getCmp("qwbuilder_startupPanel").remove("tab_"+id, true);
	},
	
	debug: function(module, type, item) {
		Builder.addTab("?event=builder:debug&module_id="+module+"&"+type+"="+item, "Debug View", "debugview", 'debug');
	},
	
        /**
         * resetTree(node, newNodes) -> void
         * - `node` (Ext.tree.TreeNode) - the root node which should be resetted
         * - `newNodes` (Array) - an array of `Ext.tree.TreeNode` objects, which should be the root node's children
         *
         * This method will remove all previous nodes from the root node and insert the ones specified instead.
         **/
	resetTree: function(node, newNodes) {
		while (node.hasChildNodes()) {
			node.item(0).remove();
		}
		for (var i = 0; i < newNodes.length; i++) {
			var clone = new Ext.tree.TreeNode({
				text: newNodes[i].text,
				id: newNodes[i].id,
				leaf: newNodes[i].leaf,
				cls: newNodes[i].cls,
				allowChildren: newNodes[i].allowChildren
			});
			node.appendChild(clone);
		}		
	},
	registerShortCut: function(target, shortcuts) {
		target.shortcuts = shortcuts;
		target.observe("keyup", function(event) {
			this.shortcuts.each(function(item) {
				var key = (!isNaN(parseInt(item.key)) ? item.key : item.key.toUpperCase().charCodeAt(0));
				if (item.alt) {
					if (event.altKey && event.ctrlKey && event.keyCode == key) {
						event.stop();
						item.callback(event);
					};
				} else {
					if (event.shiftKey && event.ctrlKey && event.keyCode == key) {
						event.stop();
						item.callback(event);
					};
				}
			});
		}.bindAsEventListener(target));
	},
	historyCleanFields: function(fields) {
		fields.each(function(item){
			if (!(item.tagName != null && item.tagName.toLowerCase() == "input")) {
				item.doc.body.innerHTML = item.doc.body.innerHTML.replace(/<del>([^<]|<[^\/]|<\/[^d]|<\/d[^e]|<\/de[^l]|<\/del[^>])*<\/del>\s*<br\s*\/{0,1}>/gm, "");
				item.reparseBuffer();
			}
		});
	},
	quickOpen: function(id) {
		if (id == null) {
			$("item.quickOpen").focus();
		} else {
			var type = id.split(/_/g)[0];
			switch (type) {
				case "d":
				case "h":
					var node = null;
					var module = Builder.root.findChildBy(function(child) {
						var found = false;
						var list = (type == "d" ? child.datas : child.handlers);
						list.each(function(item){
							if (item.id == id) {
								node = item;
								found = true;
								throw $break;
							}
						});
						return found;
					});
					if (module != null)	{
						Builder.editModule(module);
						if (type == "d") 
							Builder.editData(node); 
						else
							Builder.editHandler(node);
					}
					break;
				case "m":
					var module = Builder.root.findChild("id", id.split(/_/g)[1]);
					if (module != null) {
						Builder.editModuleOptions(module);
					}
					break;
				case "l":
					var lib = Builder.libRoot.findChild("id", id);
					if (lib != null) {
						Builder.editLibrary(lib);
					}
					break;
				case "t":
					var tag = Builder.tagRoot.findChild("id", id);
					if (tag != null) {
						Builder.editTag(tag);
					}
					break;
				case "db":
					var db = Builder.dbRoot.findChild("id", id);
					if (db != null) {
						Builder.editTable(db);
					}
					break;
				case "text":
					var x = null;
					var search = function(root, id) {
						var x = root.findChild("id", id);
						if (x) return x;
						if (root.hasChildNodes()) {
							for (var i = 0; i < root.childNodes.length; i++) {
								if (x = search(root.childNodes[i], id)) {
									return x;
								}
							}
						}
						return null;
					};
					x = search(Builder.langRoot, id);
					if (x != null) {
						Builder.editText(x);
					}
					break;
			}
		}
	},
	
	registerUpdater: function(id, callback) {
		Builder.updaters[id] = callback;
	},

	/**
	 * convert an element (preferably a div) into a bespin editor
	 */
    applyBespin: function(el, fn, obj) {
		el = $(el);
		var sel = el;
		if (el.hasClassName("hcodeh") || el.hasClassName("hhtmlcodeh")) {
			sel = el.up(".x-tab-panel");
		}
		sel.setStyle("-ms-transition:all 0.5s ease-out;-o-transition:all 0.5s ease-out;-moz-transition:all 0.5s ease-out;-webkit-transition:all 0.5s ease-out;");
		// save the content
        var content = $(el).innerHTML;
        // create an iframe to load bespin
        el.innerHTML = "<div class='blockable' id='"+el.id+"-blocked-text'></div><iframe src='templates/builder/html/codeeditor.html' name='"+el.id+"' style='display:block;width:100%;' height='100%' frameborder='no'></iframe>";

        var crc = new PeriodicalExecuter(function(crc) {
        	try {
        		var code = Builder.getCode(crc.el);
        		if (code != crc.content && !crc.el.dirty) {
        			// mark as dirty
        			crc.el.dirty = true;
        			invoke("builder:updateCRCFile&dirty="+crc.el.id);
        		} else if (code == crc.content && crc.el.dirty) {
        			crc.el.dirty = false;
        			invoke("builder:updateCRCFile&clean="+crc.el.id);
        		}
        	} catch(e){};
        }, 0.5);
        crc.content = content;
        crc.el = el;
        el.crc = crc;
        var initWin = function() {
            var cwin = document.getElementsByName(el.id)[0].contentWindow;
            cwin.init = function() {
                cwin.prails = Object.clone(obj);
                cwin.id = document.getElementsByName(el.id)[0].contentWindow.name;
                cwin.el = el;
                var data = JSON.parse(el.getAttribute("data-bespinoptions"));
                cwin.txt.setBrush(data.syntax);
                if (data.html == true) {
                	cwin.txt.enableHtmlScript();
                }
                if (obj && obj.save) {
                	cwin.txt.save = obj.save;
                }
		cwin.txt.changed = function(mode, code) {
			var functions = {"text/x-php": "checkPHPSyntax", "application/x-httpd-php": "checkHtmlSyntax", "text/javascript": "checkJSSyntax"};
			if (functions[mode]) {
				var result = Builder[functions[mode]](code, function(errors) {
					if (cwin.txt.previousErrors) for (var i = 0; i < cwin.txt.previousErrors.length; i++) {
						cwin.txt.editor.clearMarker(cwin.txt.previousErrors[i].line - 1);
						cwin.el.style.border = "inherit";
					}
					if (errors) {
						for (var i = 0; i < errors.length; i++) {
							var info = cwin.txt.editor.lineInfo(errors[i].line - 1);
							if (info && !info.markerText) {
								cwin.txt.editor.setMarker(errors[i].line - 1, "<span class='error' title=\""+errors[i].message.replace(/"/g, "''")+"\"><span>&bull;</span></span>"+errors[i].line);
							}
						}
						cwin.el.style.border = "1px solid red";

						cwin.txt.previousErrors = errors;
					}
				});
			}
		};
                cwin.txt.setCode(content);
                if (el.getAttribute("onload")) {
                	try {
                		eval(el.getAttribute("onload"))(cwin.txt);
                	} catch(e){console.log(e);};
                }            
                
                cwin.document.body.onkeydown = function(event) {
                	if (!event) event = cwin.event;
    				Ext.getCmp("qwbuilder_startupPanel").getActiveTab().el.dom.hasFocus = cwin.id;
    	        	if (event.keyCode == 'F'.charCodeAt(0) && (event.ctrlKey || event.metaKey)) {
    	        		cwin.parent.Builder.searchInBespin(cwin);
    	        		try {
    	        			event.stopPropagation();
    	        			event.cancelBubble = true;
    	        		} catch(e){};
    	        		return false;
    	        	} else if (event.keyCode == "S".charCodeAt(0) && (event.ctrlKey || event.metaKey)) {
				cwin.txt.save();
				try {
    	        			event.stopPropagation();
    	        			event.cancelBubble = true;
				} catch(e){};
				return false;
			} else if (event.ctrlKey && event.altKey) {
    	        		if (event.keyCode == 39) {
    	        			if (window.switching) return;
    	        			window.switching = true;
    	        			Builder.blurBespin(el);
    	        			Builder.nextTab(event);
    	        			setTimeout(function() { window.switching = false; }, 200);
    	        			return false;
    	        		} else if (event.keyCode == 37) {
    	        			if (window.switching) return;
    	        			window.switching = true;
    	        			Builder.blurBespin(el);
    	        			Builder.previousTab(event);
    	        			setTimeout(function() { window.switching = false; }, 200);
    	        			return false;
    	        		}
    	        	} else if (event.ctrlKey && event.shiftKey) {
    	        		if (event.keyCode == "D".charCodeAt(0)) {
    	        			Builder.quickOpen();
    	        		} else if (event.keyCode == "A".charCodeAt(0)) {
    						Builder.queryTest();
    	        		} else if (event.keyCode == "Q".charCodeAt(0)) {
    	        			if (!win.parent.closed) {
    	        				win.parent.closed = true;
    	            			Builder.closeCurrentTab();  
    	            			setTimeout(function() {cwin && cwin.parent && (cwin.parent.closed = false);}, 100);
    	                		try {
    	                			event.stopPropagation();
    	                			event.cancelBubble = true;
    	                		} catch(e){};
    	                		return false;
    	        			}
				}           		
    	        	}
                };
                if (typeof(fn) == "function") {
                	// fire callback as soon as settled
                	setTimeout(function() {fn.apply(window, [cwin])}, 1);
                }
            };
        	
         };
         if (el.up(".x-tab")) {
        	 setTimeout(initWin, 1);
         } else {
        	 initWin();
         }
    },
    
    setCode: function(el, newval) {
    	el = $(el);
    	var bespin = document.getElementsByName(el.id)[0].contentWindow.txt;
    	bespin.setCode(newval);
    },
    
    /** 
     * retrieve the code from a bespin instance
     */
    getCode: function(el) {
        el = $(el);
        if (!document.getElementsByName(el.id)[0] || !document.getElementsByName(el.id)[0].contentWindow || !document.getElementsByName(el.id)[0].contentWindow.txt || !document.getElementsByName(el.id)[0].contentWindow.txt.getCode) {
        	return "";
        }
        return document.getElementsByName(el.id)[0].contentWindow.txt.getCode();
    },
    
    focusBespin: function(el) {
    	el = $(el);
    	var oel = el;
    	if (el.hasClassName("hcodeh")) {
    		el = el.up(".x-tab-panel");
    		el.up(".x-tab-panel");
    	}
    	el.setStyle("box-shadow: 0px 0px 10px #db0;");//.morph("border: 1px solid #ccc;");
    	setTimeout(function() {
    		el.setStyle("box-shadow:0px 0px 0px #db0");
    	}, 1000);
    	document.getElementsByName(oel.id)[0].contentWindow.focus();
		document.getElementsByName(oel.id)[0].contentWindow.txt.editor.focus();
    },
    
    blurBespin: function(el) {
    	el = $(el);
    	document.getElementsByName(el.id)[0].contentWindow.txt.blur();
    	document.getElementsByName(el.id)[0].contentWindow.blur();
    },
    
    enableBespin: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.txt.enable();
    },

    disableBespin: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.txt.enable(false);
    },
    
    refreshBespin: function(el) {
    	el = $(el);
    	var url = el.getAttribute("rel");
    	invoke(url, function(req){
    		if (req.responseText.length > 0) {
    			var res = eval("("+req.responseText+")");
    			Builder.setCode(el, res["code"]);
    		}
    	});
    },
    searchInBespin: function(win) {
    	var bespin = win.txt;
		window.sarwin = new Ext.Window({
			layout: "fit",
			title: "Search & Replace in Code",
			modal: false,
			autoScroll: true,
			resizable: true,					
			shadow: true,
			width: 316,
			height: "auto",
			plain: true,
			html: window.searchReplaceForm.cloneNode(true).innerHTML,
			bbar: [{
				text: "Find",
				handler: startFind = function() {
					Cookie.create("prails-last-search", $("tosearch").getValue());
					bespin.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var dir = ($("forward").checked && {func: "findNext", attr: "End"}) || ($("backward").checked && {func: "findPrevious", attr: "Start"});
					var nextMatch = bespin[dir.func](bespin["selection"+dir.attr], $("wrapsearch").checked);
					if (nextMatch) {
						bespin.setSelection(nextMatch);
						$("tosearch").focus();
					} else {
						Ext.Msg.alert("Not found", "There was no match for \""+$('tosearch').getValue()+"\"", function() {
							setTimeout(function() { $("tosearch").focus(); }, 100);							
						});
					}
				}
			}, "-", {
				text: "Replace",
				handler: function() {
					Cookie.create("prails-last-search", $("tosearch").getValue());
					Cookie.create("prails-last-replace", $("toreplace").getValue());
					bespin.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var dir = ($("forward").checked && {func: "findNext", attr: "End"}) || ($("backward").checked && {func: "findPrevious", attr: "Start"});
					var nextMatch = bespin[dir.func](bespin["selection"+dir.attr], $("wrapsearch").checked);
					if (nextMatch) {
						bespin.setSelection(nextMatch);
						bespin.replace(nextMatch, $("toreplace").getValue());
						$("tosearch").focus();
					} else {
						Ext.Msg.alert("Not found", "There was no match for \""+$('tosearch').getValue()+"\"", function() {
							setTimeout(function() { $("tosearch").focus(); }, 100);							
						});
					}
				}
			}, "-", {
				text: "Replace All",
				handler: function() {
					Cookie.create("prails-last-search", $("tosearch").getValue());
					Cookie.create("prails-last-replace", $("toreplace").getValue());
					bespin.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var replaced = 0;
					var dir = ($("forward").checked && {func: "findNext", attr: "End"}) || ($("backward").checked && {func: "findPrevious", attr: "Start"});
					while (nextMatch = bespin[dir.func](bespin["selection"+dir.attr], $("wrapsearch").checked)) {
						bespin.setSelection(nextMatch);
						bespin.replace(nextMatch, $("toreplace").getValue());
						replaced++;
					} 
					if (replaced > 0) {
						Ext.Msg.alert("Replace complete", "Successfully replaced "+replaced+" occurrences of \""+$('tosearch').getValue()+"\" with \""+$("toreplace").getValue()+"\".");
					} else {
						Ext.Msg.alert("Not found", "There was no match for \""+$('tosearch').getValue()+"\"", function() {
							setTimeout(function() { $("tosearch").focus(); }, 100);							
						});
					}
				}
			}, "-", {
				text: "Close",
				handler: function() {
					var hf = Ext.getCmp("qwbuilder_startupPanel").getActiveTab().el.dom.hasFocus;
        			hf && Builder.focusBespin(hf);
					window.sarwin.destroy();
				}
			}]
		});
		window.sarwin.show();
		setTimeout(function() {
			$("tosearch").value = Cookie.read("prails-last-search") || "";
			$("toreplace").value = Cookie.read("prails-last-replace") || "";
			$("tosearch").focus();
			$("tosearch").observe("keyup", function(event) {
				if (event.keyCode == 13) {
					startFind();
				}
			});
		}, 100);
    },

    checkPHPSyntax: function(code, callback) {
	var cb = callback;
	invoke(null, 'builder:checkPHPSyntax', {code: code}, true, function(req) {
		var data = null;
		data = JSON.parse(req.responseText.split(/\n/g)[0]);
		if (data) cb([data]); else cb(false);
	});
    },
    checkHtmlSyntax: function(code, callback) {
	while (code.match(/<%([^%]|%[^>])*%>|<@([^@]|@[^>])*@>|<\?([^?]|\?[^>])*\?>/mg) || code.match(/<(qw|c):\w+(\s*\w+=("[^"]*")|('[^']*'))*\s*\/?>|<\/(qw|c):\w+>/mg)) {
		code = code.replace(/<%([^%]|%[^>])*%>|<@([^@]|@[^>])*@>|<\?([^?]|\?[^>])*\?>/mg, '').replace(/<(qw|c):\w+(\s*\w+=("[^"]*")|('[^']*'))*\s*\/?>|<\/(qw|c):\w+>/gm, '');
	}
	code = "<script type='text/javascript'>/*global $:false, $$:false, $H: false, Overlabel: false, $A: false, jQuery: false, _:false, addLoadEvent:false, invoke:false, S2:false, Cookie:false */</script>" + code;
	var result = JSLINT(code, {
		anon: true,
		bitwise: true,
		browser: true,
		'continue': true,
		css: true,
		fragment: true,
		debug: true,
		devel: true,
		eqeq: true,
		evil: true,
		nomen: true,
		on: true,
		plusplus: true,
		regexp: true,
		undef: true,
		sloppy: true,
		vars: true,
		white: true

	});
	if (result) callback();
	else {
		var result = [];
		for (var i = 0; i < JSLINT.errors.length; i++) {
			if (JSLINT.errors[i]) 
				result.push({message: JSLINT.errors[i].reason, line: JSLINT.errors[i].line});
		}
		callback(result);
	}
    },
    checkJSSyntax: function(code, callback) {
	code = "/*global $:false, $$:false, $H: false, Overlabel: false, $A: false, jQuery: false, _:false, addLoadEvent:false, invoke:false, S2:false, Cookie:false */" + code;
	var result = JSLINT(code, {
		anon: true,
		bitwise: true,
		browser: true,
		'continue': true,
		debug: true,
		devel: true,
		eqeq: true,
		evil: true,
		nomen: true,
		on: true,
		plusplus: true,
		regexp: true,
		undef: true,
		sloppy: true,
		vars: true,
		white: true
	});
	if (result) callback();
	else {
		var result = [];
		for (var i = 0; i < JSLINT.errors.length; i++) {
			if (JSLINT.errors[i]) 
				result.push({message: JSLINT.errors[i].reason, line: JSLINT.errors[i].line});
		}
		callback(result);
	}
    },
    
    /**
     * addSection(panel) -> Ext.tree.TreeNode
     * addSection(title, defaultClickCallback) -> Ext.tree.TreeNode
     * - `panel` (Object) - an object describing the panel's details. Can contain additional parameters as explained in !(http://docs.sencha.com/ext-js/3-4/#!/api/Ext.tree.TreePanel)
     * - `title` (String) - the section's title
     * - `defaultClickCallback` (Function) - a callback function that is called whenever a node is double-clicked (first parameter is the node object)
     *
     * `Ext.tree.TreeNode` the tree node that double-clickable items can be attached to
     * this function adds a new section to the Prails IDE; the `TreeNode` returned can be appended with double-clickable items (other tree nodes). For this purpose, there
     * are the two methods `addNodes(nodeList)` and `addNode(node[, link[, subNodes]])`. The first of these methods takes an array of hash maps containing the three attributes: `title`, `link` and `nodes`
     * whereas the latter one is optional. The other method can accept a hashmap of the same structure or up to three parameters. 
     *
     * *Example:*
     * {{{
     * var rootNode = Builder.addSection("Test Section");
     * rootNode.addNodes([{
     *     title: "First node", 
     *     link: "Admin/firstPage"
     * }, {
     *     title: "Second Node", 
     *     link: "Admin/secondPage", 
     *     nodes: [{
     *         title: "Sub Node", 
     *         link: "Admin/subPage"
     *     }]
     * }]);
     * }}}
     * This example adds a new section called "Test Section" and attaches several nodes, one of which has a 
     * child node. When a node is double-clicked it will open the node's link address in a new IDE tab (this is
     * the default behavior).
     *
     * *Example 2:*
     * {{{
     * var rootNode = Builder.addSection({
     *     title: "Test Section", 
     *     listeners: {
     *         dblclick: function(n) { 
     *             alert('Node '+n.id+' was double-clicked!');
     *         } 
     *     }
     * });
     * rootNode.addNode("First Node", "Admin/firstPage");
     * rootNode.addNode("Second Node", "Admin/secondPage", [{
     *     title: "Sub Node", 
     *     link: "Admin/subPage"
     * }]);
     * }}}
     * This example adds a new section, called "Test Section" and defines the double-click handler to show
     * an alert when a node is double-clicked. It adds the same node structure as the first example.
     **/
    addSection: function(panel) {
	var tree = new Ext.tree.TreeNode();
	if (typeof(panel) === "string") {
		panel = {title: panel};
		if (arguments.length > 1) {
			panel["listeners"] = { dblclick: arguments[1] };
		}
	}
	var defaultPanel = {
		id: "qwbuilder_custom-"+(new Date().getTime()),
		title: "Custom Panel",
		xtype: "treepanel",
		region: "south",
		border: false,
		width: 150,
		minSize: 120,
		maxSize: 540,
		split: true,
		autoScroll: true,
		root: tree,
		rootVisible: false,
		listeners: { 
			dblclick: function(n) {
				Builder.addTab(n.id, n.text, "tab-"+n.id);
			}
		}
        };
    	Builder.hookedPanels.push(Object.extend(defaultPanel, panel || {}));
	tree = panel["root"] || tree;
	var nodeAdding = {
		addNodes: function(nodeList, customTree) {
			for (var i = 0; i < nodeList.length; i++) {
				(customTree || tree).addNode(nodeList[i], customTree || tree);
			}
			return (customTree || tree);
		},
		addNode: function(node, customTree) {
			var extNode;
			if (typeof(node) === "string") {
				node = {title: node};
				node.link = customTree;
				customTree = null;
				if (arguments.length > 2) {
					node.nodes = arguments[2];
				}
			}
			if (node.nodes) {
				extNode = new Ext.tree.TreeNode({
					text: node.title,
					id: node.link,
					leaf: false,
					allowChildren: true
				});
			} else {
				extNode = new Ext.tree.TreeNode({
					text: node.title,
					leaf: true,
					id: node.link,
					allowChildren: false
				});
			}
			extNode = Object.extend(extNode, nodeAdding);
			(customTree || tree).appendChild(extNode);
			if (node.nodes) {
				(customTree || tree).addNodes(node.nodes, extNode);
			}
			return (customTree || tree);
		}
	};
	tree = Object.extend(tree, nodeAdding);
	return tree;
    }
    
});
