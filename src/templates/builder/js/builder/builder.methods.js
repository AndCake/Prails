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
				if (event.shiftKey && event.ctrlKey && event.keyCode == key) {
					event.stop();
					item.callback(event);
				};
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
        el.innerHTML = "<iframe src='about:blank' name='"+el.id+"' style='display:block;width:100%;' height='100%' frameborder='no'></iframe>";
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
//				console && console.log("try to fix the resize issue! "+e.message);
			}
            	}
            }, 0.25);
            pl.el = el;
            pl.div = b;
            pl.win = win;
            
            win.onBespinLoad = function() {
            	var env = win.document.getElementsByTagName("div")[0].bespin;
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
    	document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin.editor.value = newval;
    },
    
    /** 
     * retrieve the code from a bespin instance
     */
    getCode: function(el) {
        el = $(el);
        return document.getElementsByName(el.id)[0].contentWindow.document.getElementsByTagName("div")[0].bespin.editor.value;
    }	
});
