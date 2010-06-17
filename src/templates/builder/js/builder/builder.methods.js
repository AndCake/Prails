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
			}
		}
	},
	
	registerUpdater: function(id, callback) {
		Builder.updaters[id] = callback;
	}
});