/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
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
	
	reloadTab: function(id) {
		var spanel = Ext.getCmp("qwbuilder_startupPanel");
		var tab = spanel.getItem("tab_"+id);
		var obj = Object.clone(tab.metaDataObj);
		
		spanel.remove("tab_"+id, true);
		
		Builder.addTab(obj.url, obj.title, obj.id, obj.icon);
	},
	
	closeTab: function(id) {
		Ext.getCmp("qwbuilder_startupPanel").remove("tab_"+id, true);
	},
	
	debug: function(module, type, item) {
		Builder.addTab("?event=builder:debug&module_id="+module+"&"+type+"="+item, "Debug View", "debugview", 'debug');
	},
	
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
			$("item.quickOpen").highlight();
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
                var data = JSON.parse(el.getAttribute("data-bespinoptions"));
                cwin.txt.setBrush(data.syntax);
                if (data.html == true) {
                	cwin.txt.enableHtmlScript();
                }
                if (obj && obj.save) {
                	cwin.txt.save = obj.save;
                }
                cwin.txt.setCode(content);
                if (el.getAttribute("onload")) {
                	try {
                		eval(el.getAttribute("onload"))(cwin.txt);
                	} catch(e){console.log(e);};
                }            
                
                cwin.document.body.onkeyup = function(event) {
                	if (!event) event = cwin.event;
    				Ext.getCmp("qwbuilder_startupPanel").getActiveTab().el.dom.hasFocus = cwin.id;
    	        	if (event.keyCode == 'F'.charCodeAt(0) && event.ctrlKey) {
    	        		cwin.parent.Builder.searchInBespin(cwin);
    	        		try {
    	        			event.stopPropagation();
    	        			event.cancelBubble = true;
    	        		} catch(e){};
    	        		return false;
    	        	} else if (event.ctrlKey && event.altKey) {
    	    			window.focus();
    	        		if (event.keyCode == 39) {
    	        			Builder.blurBespin(el);
    	        			Builder.nextTab(event);
    	        		} else if (event.keyCode == 37) {
    	        			Builder.blurBespin(el);
    	        			Builder.previousTab(event);
    	        		}
    	        		return false;
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
    	document.getElementsByName(oel.id)[0].contentWindow.txt.focus();
    	document.getElementsByName(oel.id)[0].contentWindow.txt.selectionStart = document.getElementsByName(oel.id)[0].contentWindow.txt.sel[0];
    	document.getElementsByName(oel.id)[0].contentWindow.txt.selectionEnd = document.getElementsByName(oel.id)[0].contentWindow.txt.sel[1];
    	document.getElementsByName(oel.id)[0].contentWindow.txt.onfocus.apply(document.getElementsByName(oel.id)[0].contentWindow.txt);
    },
    
    blurBespin: function(el) {
    	el = $(el);
    	document.getElementsByName(el.id)[0].contentWindow.txt.onblur.apply(document.getElementsByName(el.id)[0].contentWindow.txt);
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
			$("tosearch").focus();
			$("tosearch").observe("keyup", function(event) {
				if (event.keyCode == 13) {
					startFind();
				}
			});
		}, 100);
    },
    
    addSection: function(panel) {
    	Builder.hookedPanels.push(panel);
    }
    
});
