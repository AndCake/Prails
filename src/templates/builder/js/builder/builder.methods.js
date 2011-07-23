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
		// save the content
        var content = $(el).innerHTML;
        // create an iframe to load bespin
        el.innerHTML = "<div class='blockable' id='"+el.id+"-blocked-text'></div><iframe src='about:blank' name='"+el.id+"' style='display:block;width:100%;' height='100%' scrolling='no' frameborder='no'></iframe>";

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
        
        var cwin = document.getElementsByName(el.id)[0].contentWindow;
        var pe = new PeriodicalExecuter(function(pe) {
        	pe.stop();
            var win = document.getElementsByName(pe.el.id)[0].contentWindow;
            win.id = el.id;
            // apply context
            win.prails = Object.clone(pe.obj);
            // load all needed bespin components
            var link = win.document.createElement('link');
            link.id="bespin_base";
            link.href="templates/builder/js/bespin";
            win.document.getElementsByTagName('head')[0].appendChild(link);
            var script = win.document.createElement('script');
            script.src="templates/builder/js/bespin/BespinEmbedded.js";
            script.type="text/javascript";
            win.document.getElementsByTagName('head')[0].appendChild(script);
            var style = win.document.createElement('link');
            style.rel = "stylesheet";
            style.href="templates/builder/js/bespin/BespinEmbedded.css";
            style.type="text/css";
            win.document.getElementsByTagName('head')[0].appendChild(style);
            // write the actual element to be bespinned
            var b = win.document.createElement("div");
            b.className = "bespin";
            b.setAttribute("data-bespinoptions", pe.el.getAttribute("data-bespinoptions"));
            b.innerHTML = content;
            win.document.body.appendChild(b);
            win.document.body.style.margin = "0px";
            win.document.body.style.padding = "0px";

            // scan for resizing events (shrinking in particular)
            var pl = new PeriodicalExecuter(function(pl) {
            	if (pl.el.parentNode.visible()) {
            		// if the container width has been reduced 
					try {
		                if (pl.el.clientWidth + 50 < pl.win.document.width || pl.el.clientWidth - 50 > pl.div.clientWidth) {
		                	// adapt the inner canvas
		                	pl.div.style.width = pl.el.clientWidth+'px';
							pl.div.getElementsByTagName("canvas")[1].width = pl.el.clientWidth - parseInt(pl.div.getElementsByTagName("canvas")[1].style.left);
							// and refresh views
		                	pl.div.bespin.editor.textView.invalidate();
	                		pl.div.bespin.editor.dimensionsChanged();
		                }
					} catch(e) {
						pl.stop();
					}
            	}
            }, 0.25);
            pl.el = el;
            pl.div = b;
            pl.win = win;
            win.document.body.onkeyup = function(event) {
            	if (!event) event = win.event;
            	if (event.keyCode == 'F'.charCodeAt(0) && event.ctrlKey) {
            		win.parent.Builder.searchInBespin(win);
            		try {
            			event.stopPropagation();
            			event.cancelBubble = true;
            		} catch(e){};
            		return false;
            	} else if (event.ctrlKey && event.altKey) {
            		if (event.keyCode == 39) {
            			Builder.nextTab(event);
            		} else if (event.keyCode == 37) {
            			Builder.previousTab(event);
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
                			setTimeout(function() {win.parent.closed = false;}, 100);
            			}
            		}            		
            	}
            };
            
            win.onBespinLoad = function() {
            	var env = win.document.getElementsByTagName("div")[0].bespin;
            	el.crc.content = Builder.getCode(el.id);
	            if (el.getAttribute("onload")) {
	            	eval(el.getAttribute("onload"))(env);
	            }
            };
            
            new PeriodicalExecuter(function(pa) { try { win.onready(); pa.stop(); 'stopped for '+pe.el.id+'!'; }catch(e){};}, 0.25);
            if (typeof(pe.fn) == "function") {
            	// fire callback as soon as settled
            	setTimeout(function() {pe.fn.apply(window, [win])}, 1);
            }
        }, 0.01);
        pe.fn = fn;
        pe.el = el;
        pe.obj = obj;
    },
    
    setCode: function(el, newval) {
    	el = $(el);
    	var bespin = document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin;
    	var sel = bespin.editor.selection;
    	bespin.editor.value = newval;
    	bespin.editor.selection = sel;
    },
    
    /** 
     * retrieve the code from a bespin instance
     */
    getCode: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin.editor.value;
    },
    
    enableBespin: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin.editor.readOnly = false;
    },

    disableBespin: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin.editor.readOnly = true;
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
    	var bespin = win.document.getElementsByTagName("div")[0].bespin;
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
					bespin.editor.searchController.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var dir = ($("forward").checked && {func: "findNext", attr: "end"}) || ($("backward").checked && {func: "findPrevious", attr: "start"});
					var nextMatch = bespin.editor.searchController[dir.func](bespin.editor.selection[dir.attr], $("wrapsearch").checked);
					if (nextMatch) {
						bespin.editor.setLineNumber(nextMatch.start.row + 1);
						bespin.editor.selection = nextMatch;
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
					bespin.editor.searchController.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var dir = ($("forward").checked && {func: "findNext", attr: "end"}) || ($("backward").checked && {func: "findPrevious", attr: "start"});
					var nextMatch = bespin.editor.searchController[dir.func](bespin.editor.selection[dir.attr], $("wrapsearch").checked);
					if (nextMatch) {
						bespin.editor.setLineNumber(nextMatch.start.row + 1);
						bespin.editor.selection = nextMatch;
						bespin.editor.replace(nextMatch, $("toreplace").getValue());
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
					bespin.editor.searchController.setSearchText($("tosearch").getValue(), $("regexp").checked);
					var replaced = 0;
					var dir = ($("forward").checked && {func: "findNext", attr: "end"}) || ($("backward").checked && {func: "findPrevious", attr: "start"});
					while (nextMatch = bespin.editor.searchController[dir.func](bespin.editor.selection[dir.attr], $("wrapsearch").checked)) {
						bespin.editor.selection = nextMatch;
						bespin.editor.replace(nextMatch, $("toreplace").getValue());
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
				handler: function() { window.sarwin.destroy(); }
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
