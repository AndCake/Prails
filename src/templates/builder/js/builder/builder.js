/**
 * @author roq
 */

Builder = Object.extend(Builder || {}, {

	updaters: [],
	root: new Ext.tree.TreeNode(),
	dbRoot: new Ext.tree.TreeNode(),
	libRoot: new Ext.tree.TreeNode(),
	tagRoot: new Ext.tree.TreeNode(), 
	handlerRoot: new Ext.tree.TreeNode(),
	dataRoot: new Ext.tree.TreeNode(),
	langRoot: new Ext.tree.TreeNode(),
	currentModule: null,
	prailsNamespace: {
	  save: function(){},
	  run: function(){},
	  open: function() {parent.Builder.quickOpen.apply(parent, []);},
	  close: function() {parent.Builder.closeCurrentTab.apply(parent, []);},
	  query: function() {parent.Builder.queryTest.apply(parent, []);},
	  previousTab: function() {parent.Builder.previousTab.apply(parent, []);},
	  nextTab: function() {parent.Builder.nextTab.apply(parent, []);}
	},
		
	init: function(openedPanel) {
	   Ext.state.Manager.setProvider(new Ext.state.CookieProvider());
	   
		var headerPanel = new Ext.Panel({
			id: "qwbuilder_headerPanel",
			region: "north",
			layout: "border",
			height: 30,
			xtype: "panel",
			border: false,
			items: [{
				xtype: "panel",
				border: false,
				html: $("header").innerHTML,
				region: "center"
			},{
				xtype: "panel",
				width: 500,
				border: false,
				layout: "table",
				cls: "header-nav",
				layoutConfig: {columns: 3, tableAttrs: {style: {width: '100%'}}},
				region: "east",
				items: [{
					xtype: "button",
					id: "testresult",
					style: "margin-right: 10px;",
					iconCls: "testUnknown",
					text: "Testsuite",
					menu: {
						defaults : {
			                checked: false,
			                group: 'testsuiteCheckGroup',
			                listeners: {
								click: window.__runTest = function(item) {
									if (item.id == "view-log")
										return;
																		
									Ext.getCmp("testresult").setText(item.text);
									
									if (!$("background_window")) {
										window.__testTargetWindowContainer = new Ext.Window({
											layout: "fit",
											title: "Test Run",
											modal: false,
											autoScroll: true,
											resizable: true,					
											shadow: true,
											width: 800,
											height: 600,
											plain: true,
											listeners: {
												close: function(p) {
													Ext.getCmp("view-run").setChecked(false);
												}
											},
											html: "<iframe name='background_testing_window' id='background_window' class='testRunWindow'></iframe>"					
										});
										window.__testTargetWindowContainer.show();
										window.__testTargetWindowContainer.hide();
//										document.body.appendChild(new Element("iframe", {name: "background_testing_window", id: "background_window", 'class': "testRunWindow"}))
									}
									window.__testTargetWindow = window.frames["background_testing_window"];
									// run selected test cases
									new Ajax.Request("?event=builder:listTestcase&json=1&fetch="+item.id+(item.type == "testcase" ? '&type=single' : ''), {
										method: "GET",
										onSuccess: function(req) {
											resetTest();
											var testsuite = eval(req.responseText);
											testsuite.start();
										}
									});
								}
							}
			            },
						items: window.testsuites
					}
				}, {
					xtype: "button",
					style: "margin-right: 10px;",
					iconCls: "package",
					text: "Package",
					handler: function(){
						new Ext.Window({
							layout: "fit",
							title: "Package",
							iconCls: "package",
							modal: true,
							shadow: true,
							width: 441,
							height: 384,
							plain: true,
							items: [
							        new Ext.TabPanel({
							        	activeTab: 0,
							        	border: false,
							        	items: [{
							        		title: "Export",
							        		html: $("export_panel").innerHTML
							        	},{
							        		title: "Import",
							        		id: "import",
							        		html: $("import_panel").innerHTML.replace(/<!--|-->/gmi, "")
							        	}],
							        	listeners: {
							        		tabchange: function(panel, tab) {
							        			if (tab.getId() == "import") {
							        				QuixoticWorxUpload.init();							        				
							        			}
							        		}
							        	}
							        })
							]
						}).show(this);
						QuixoticWorxUpload.init();
					}
				}, {
					xtype: "combo",
					width: 200,
					id: "item.quickOpen",
					emptyText: "Open item...",
					selectOnFocus: true,
					displayField: "name",
					valueField: "id",
					typeAhead: true,
					mode: "remote",
					listeners: {
						specialkey: function(field, event) {
							if (event.getKey() == event.ENTER) {
								var val = field.getValue();
								field.blur();
								field.reset();
								Builder.quickOpen(val);
							}
						}
					},
					store: mystore=new Ext.data.JsonStore({
						autoDestroy: true,
						url: "?event=builder:searchItem",
						idProperty: "id",
						fields: ["id", "name", "type"]
					})
				}]
			}]
		});
		mystore.load();
		
		var contentPanel = new Ext.Panel({
			id: "qwbuilder_contentPanel",
			region: "center",
			xtype: "panel",
			border: false,
			margins: "5 0 0 0",
			layout: "border",
			items: [
				new Ext.TabPanel({
					id: "qwbuilder_startupPanel",
					region: "center",
					xtype: "tabpanel",
			        enableTabScroll:true,
					activeTab: 0,
					items: [{
						title: "Help",
						iconCls: "HelpTabIcon",
						xtype: "panel",
						html: $("help").innerHTML	
					}],
					listeners: {
						tabchange: function(tab, content) {
							if (content.metaDataObj != null && content.metaDataObj.lastFocus != null) {
								setTimeout(function() {
									content.metaDataObj.lastFocus.focus();
								}, 50);
							}
							var id = content.getId();
							var idParts = id.split(/_/g);
							var type = idParts[1];
							switch (type) {
								case "l":
								case "t":
									Ext.getCmp("qwbuilder_navarea").getLayout().setActiveItem("qwbuilder_libsPanel");
									break;
								case "h":
								case "d":
									Ext.getCmp("qwbuilder_navarea").getLayout().setActiveItem("qwbuilder_detailsPanel");
									break;
								case "c":
								case "m":
								case "rm":
									Ext.getCmp("qwbuilder_navarea").getLayout().setActiveItem("qwbuilder_modulePanel");
									break;
								case "db":
									Ext.getCmp("qwbuilder_navarea").getLayout().setActiveItem("qwbuilder_dbPanel");
									break;
							}
						}
					}
				})
			]
		});

		var handlerPanel = new Ext.tree.TreePanel({
			id: "qwbuilder_handlerPanel",
			title: "Event Handlers",
			xtype: "treepanel",
			region: "center",
			border: false,
			split: true,
			height: 200,
			width: 150,
			minSize: 120,
			maxSize: 540, 
			autoScroll: true,
			root: Builder.handlerRoot,
			rootVisible: false,
			tbar: [{
				text: "Add",
				id: "qwbuilder_handlerPanel_addEvent",
				iconCls: "add",
				handler: Builder.addHandler
			}, "-", {
				text: "Run",
				iconCls: "run",
				handler: function() {
					Builder.runHandler(Builder.currentModule.text, Ext.getCmp("qwbuilder_handlerPanel").getSelectionModel().getSelectedNode().text);
				}
			}, "-", {
				text: "Delete",
				id: "qwbuilder_handlerPanel_deleteEvent",
				iconCls: "delete",
				handler: function() {
					Builder.delHandler(Ext.getCmp("qwbuilder_handlerPanel").getSelectionModel().getSelectedNode());
				}
			}, "-"],
			listeners: {
				dblclick: function(n){
					Builder.editHandler(n);
				},
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Event",
							iconCls: "edit",
							handler: function() {
								Builder.editHandler(n);
							}
						}, {
							text: "Run Event",
							iconCls: "run",
							handler: function(){
								Builder.runHandler(Builder.currentModule.text, n.text);
							}
						}, {
							text: "Delete Event",
							iconCls: "delete",
							disabled: (parseInt(Builder.currentModule.id) < 0),
							id: "qwbuilder_handlerMenu_deleteEvent",
							handler: function() {
								Builder.delHandler(n);
							}
						}]
					}).showAt(e.getXY());					
				}				
			}			
		});
		var dataPanel = new Ext.tree.TreePanel({
			id: "qwbuilder_dataPanel",
			title: "Data Queries",
			xtype: "treepanel",
			region: "south",
			border: false,
			height: 200,
			width: 150,
			minSize: 120,
			maxSize: 540, 
			split: true,
			autoScroll: true,
			root: Builder.dataRoot,
			rootVisible: false,
			tbar: [{
				text: "Add",
				iconCls: "add",
				handler: Builder.addData
			}, "-", {
				xtype: "button",
				text: "SQL Query",
				iconCls: "run",
				handler: function(e) {
					Builder.queryTest();
				}
			},"-",{
				text: "Delete",
				iconCls: "delete",
				handler: function() {
					Builder.delData(Ext.getCmp("qwbuilder_dataPanel").getSelectionModel().getSelectedNode());
				}
			}, "-"],
			listeners: {
				dblclick: function(n) {
					Builder.editData(n);
				},
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Query",
							iconCls: "edit",
							handler: function() {
								Builder.editData(n);
								this.hide();
							}
						},{
							text: "Delete Query",
							iconCls: "delete",
							handler: function() {
								Builder.delData(n);
								this.hide();
							}
						}]
					}).showAt(e.getXY());	
				}
			}				
		});
		var modulePanel = new Ext.tree.TreePanel({
			id: "qwbuilder_modulePanel",
			region: "north",
			title: "Modules",
			xtype: "treepanel",
			width: 150,
			minSize: 120,
			maxSize: 240, 
			border: true,
			split: true,
			autoScroll: true,
			root: Builder.root,
			rootVisible: false,
			tbar: [{
				text: "Add",
				iconCls: "add",
				handler: Builder.addModule
			},"-",{
				text: "Change Options",
				iconCls: "options",
				handler: function() {
					Builder.editModuleOptions(Ext.getCmp("qwbuilder_modulePanel").getSelectionModel().getSelectedNode());
					
				}
			},"-", {
				text: "Delete",
				iconCls: "delete",
				handler: function() {
					if (Ext.getCmp("qwbuilder_modulePanel").getSelectionModel().getSelectedNode().id < 0) {
						Ext.Msg.alert("Problem", "You cannot remove the global module.");
					} else {
						Builder.delModule(Ext.getCmp("qwbuilder_modulePanel").getSelectionModel().getSelectedNode());
					}
				}
			}, "-"],
			listeners: {
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Module",
							iconCls: "edit",
							handler: function() {
								Builder.editModule(n);
								this.hide();
							}
						},{
							text: "Change Options",
							iconCls: "options",
							handler: function() {
								Builder.editModuleOptions(n);
								this.hide();
							}
						},{
							text: "Edit Resources",
							iconCls: "resource",
							disabled: (n.id < 0 ? true : false),
							handler: function() {
								Builder.editModuleResource(n);
							}
						},{
							text: "Edit Configuration",
							iconCls: "config",
							handler: function() {
								Builder.editConfiguration(n);
							}
						},{
							text: "Delete Module",
							disabled: (n.id < 0 ? true : false),
							iconCls: "delete",
							handler: function() {
								Builder.delModule(n);
								this.hide();
							}
						}]
					}).showAt(e.getXY());					
				},
				dblclick: function(n) {
					Builder.editModule(n);
				}
			}
		});
		var libraryPanel = new Ext.tree.TreePanel({
			id: "qwbuilder_libraryPanel",
			region: "center",
			title: "Library",
			xtype: "treepanel",
			width: 150,
			minSize: 120,
			maxSize: 240, 
			border: true,
			split: true,
			autoScroll: true,
			root: Builder.libRoot,
			rootVisible: false,
			tbar: [{
				text: "Add",
				iconCls: "add",
				handler: Builder.addLibrary
			},"-", {
				text: "Delete",
				iconCls: "delete",
				handler: function() {
					Builder.delLibrary(Ext.getCmp("qwbuilder_libraryPanel").getSelectionModel().getSelectedNode());
				}
			}, "-"],
			listeners: {
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Library",
							iconCls: "edit",
							handler: function() {
								Builder.editLibrary(n);
								this.hide();
							}
						},{
							text: "Delete Library",
							iconCls: "delete",
							handler: function() {
								Builder.delLibrary(n);
								this.hide();
							}
						}]
					}).showAt(e.getXY());					
				},
				dblclick: function(n) {
					Builder.editLibrary(n);
				}
			}
		});		
		var tagLibPanel = new Ext.tree.TreePanel({
			id: "qwbuilder_tagPanel",
			title: "Tag Library",
			xtype: "treepanel",
			region: "south",
			border: false,
			height: 200,
			width: 150,
			minSize: 120,
			maxSize: 540, 
			split: true,
			autoScroll: true,
			root: Builder.tagRoot,
			rootVisible: false,
			tbar: [{
				text: "Add",
				iconCls: "add",
				handler: Builder.addTag
			}, "-", {
				text: "Delete",
				iconCls: "delete",
				handler: function() {
					Builder.delTag(Ext.getCmp("qwbuilder_tagPanel").getSelectionModel().getSelectedNode());
				}
			}, "-"],
			listeners: {
				dblclick: function(n) {
					Builder.editTag(n);
				},
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Tag",
							iconCls: "edit",
							handler: function() {
								Builder.editTag(n);
								this.hide();
							}
						},{
							text: "Delete Tag",
							iconCls: "delete",
							handler: function() {
								Builder.delTag(n);
								this.hide();
							}
						}]
					}).showAt(e.getXY());	
				}
			}				
		});	
		var dbPanel = new Ext.tree.TreePanel({
			id: "qwbuilder_dbPanel",
			title: "Database Structure",
			xtype: "treepanel",
			region: "south",
			border: false,
			height: 200,
			width: 150,
			minSize: 120,
			maxSize: 540, 
			split: true,
			autoScroll: true,
			root: Builder.dbRoot,
			rootVisible: false,
			tbar: [{
				text: "Add",
				iconCls: "add",
				handler: Builder.addTable
			}, "-", {
				text: "Delete",
				iconCls: "delete",
				handler: function() {
					Builder.delTable(Ext.getCmp("qwbuilder_dbPanel").getSelectionModel().getSelectedNode());
				}
			}, "-"],
			listeners: {
				dblclick: function(n) {
					Builder.editTable(n);
				},
				contextmenu: function(n, e) {
					e.preventDefault();
					var menu = new Ext.menu.Menu({
						items: [{
							text: "Edit Table",
							iconCls: "edit",
							handler: function() {
								Builder.editTable(n);
								this.hide();
							}
						},{
							text: "Delete Table",
							iconCls: "delete",
							handler: function() {
								Builder.delTable(n);
								this.hide();
							}
						}]
					}).showAt(e.getXY());	
				}
			}				
		});	
		var viewport = new Ext.Viewport({
			layout: "border",
			items: [
				headerPanel,
				{
					layout: "accordion",
					id: "qwbuilder_navarea",
					title: "Navigation",
					region: "west",
					collapsible: true,
					border: false,
					split: true,
					margins: "5 0 0 1",
					width: 275,
					minSize: 100,
					maxSize: 500,
					items: [modulePanel, {
						id: "qwbuilder_detailsPanel",
						title: "Module Contents",
						collapsible: true,
						region: "south",
						xtype: "panel",
						width: 150,
						minSize: 120,
						disabled: true,
						maxSize: 240, 
						border: true,
						split: true,
						layout: "border",
						layoutConfig: {
							align: "stretch",
							pack: "start"
						},
						items: [
							handlerPanel,
							dataPanel
						]
					}, {
						xtype: "panel",
						id: "qwbuilder_libsPanel",
						title: "Libraries",
						collapsible: true,
						region: "south",
						width: 150,
						minSize: 120,
						maxSize: 240,
						border: true,
						split: true,
						layout: "border",
						layoutConfig: {
							align: "stretch",
							pack: "start"
						},
						items: [libraryPanel, tagLibPanel]
					}, dbPanel, {
					   xtype: "treepanel",
					   id: "qwbuilder_langsPanel",
					   title: "Translations",
					   collapsible: true,
					   region: "south",
					   width: 150,
					   minSize: 120,
					   maxSize: 240,
					   border: true,
					   split: true,
            		   autoScroll: true,
            		   root: Builder.langRoot,
            		   rootVisible: false,
            		   tbar: [{
            		       text: "Add",
            		       menu: {
            		          items: [{
            		                  text: "Add Top Section", 
            		                  iconCls: "folder", 
            		                  id: "qwbuilder_langs_cts", 
            		                  handler: function() {
            		                      // create new section
            		                      Builder.createSubSection(Builder.langRoot);
            		                  }
            		              }, {
            		                  text: "Add Sub Section", 
            		                  iconCls: "folder", 
            		                  id: "qwbuilder_langs_css",
            		                  handler: function() {
            		                      // create new sub section
                                          selNode = Ext.getCmp("qwbuilder_langsPanel").getSelectionModel().getSelectedNode();
                                          if (selNode.isLeaf()) {
                                              selNode = selNode.parentNode;
                                          }
                		                  Builder.createSubSection(selNode);
            		                  }
            		              }, {
            		                  text: "Add Text", 
            		                  iconCls: "locale", 
            		                  id: "qwbuilder_langs_ct",
            		                  handler: function() {
            		                      // create new text
                                          selNode = Ext.getCmp("qwbuilder_langsPanel").getSelectionModel().getSelectedNode();
                                          if (selNode.isLeaf()) {
                                              selNode = selNode.parentNode;
                                          }
                                          Builder.createText(selNode);
            		                  }
            		              }
            		          ]
            		       },
            			   iconCls: "add",
            			   handler: function() {
                              selNode = Ext.getCmp("qwbuilder_langsPanel").getSelectionModel().getSelectedNode();
                              Ext.getCmp("qwbuilder_langs_css").setDisabled(selNode == null);
                              Ext.getCmp("qwbuilder_langs_ct").setDisabled(selNode == null);                              
            			   }
            			}, "-", {
            			   text: "Delete",
            			   iconCls: "delete",
            			   handler: function() {
            			      var node = Ext.getCmp("qwbuilder_langsPanel").getSelectionModel().getSelectedNode();
            			      if (node == null) {
            			         Ext.Msg.alert("Problem", "Please select an item in order to remove it.");
            			      } else {
            			          if (node.isLeaf()) {
            			              Builder.deleteText(node);
            			          } else {
                                      Builder.deleteSection(node);
            			          }
            			      }
            			   }
            			}, "-"],
            			listeners: {
            				contextmenu: function(n, e) {
            					e.preventDefault();
            					var menu = new Ext.menu.Menu({
            						items: [{
            							text: "Add Sub Section",
            							iconCls: "folder",
            							handler: function() {
                                            selNode = n;
                                            if (selNode.isLeaf()) {
                                                selNode = selNode.parentNode;
                                            }
                                            Builder.createSubSection(selNode);
            								this.hide();
            							}
            						},{
            							text: "Add Text",
            							iconCls: "locale",
            							handler: function() {
            		                      // create new text
                                            selNode = n;
                                            if (selNode.isLeaf()) {
                                                selNode = selNode.parentNode;
                                            }
                                            Builder.createText(selNode);
            								this.hide();
            							}
            						}, {
            						    text: "Delete " + (n.isLeaf() ? "Text" : "Section"),
            						    iconCls: "delete",
            						    handler: function() {
            						       selNode = n;
            						       if (selNode.isLeaf()) {
            						          Builder.deleteText(selNode);
            						       } else {
            						          Builder.deleteSection(selNode);
            						       }
            						    }
            						}]
            					}).showAt(e.getXY());	
            				}
            			}
					}]
				},
				contentPanel, 
				{
					xtype: "panel",
					region: "east",
					id: "sb_panel",
					autoScroll: true,
					html: $("shoutbox").innerHTML,
					title: "Developer Chat",
					collapsible: true,
					width: 220
				}				
			]
		});
		
		Builder.registerShortCut(document, [{
			key: Event.KEY_LEFT, 
			callback: Builder.previousTab = function(e){
				var spanel = Ext.getCmp("qwbuilder_startupPanel");
				var cactive = spanel.getActiveTab();
				var prev = -1;
				spanel.items.each(function(item, key){
					if (item == this) {
						prev = key - 1;
					}
				}.bind(cactive));
				if (prev >= 0) {
					spanel.setActiveTab(prev);
				}
			}
		},{
			key: Event.KEY_RIGHT,
			callback: Builder.nextTab = function(e) {
				var spanel = Ext.getCmp("qwbuilder_startupPanel");
				var cactive = spanel.getActiveTab();
				var next = -1;
				spanel.items.each(function(item, key){
					if (item == this) {
						next = key + 1;
					}
				}.bind(cactive));
				if (next >= 0) {
					spanel.setActiveTab(next);
				}
			}
		},{
			key: "Q",
			callback: Builder.closeCurrentTab = function(e) {
				var spanel = Ext.getCmp("qwbuilder_startupPanel");
				if (spanel.getActiveTab().initialConfig.closable) {
					spanel.remove(spanel.getActiveTab());
				}
			}
		},{
			key: "D",
			callback: function(e) {
				Builder.quickOpen();
			}
		},{
			key: "A",
			callback: function(e) {
				Builder.queryTest();
			}
		}]);
		Builder.dold = (new Date()).getTime();
		
		if (openedPanel.length > 0) {
    		Ext.getCmp(openedPanel).expand(true);
		}
	    new PeriodicalExecuter(function(pe){
	    	new Ajax.Request("builder.crc32", {
				method: "get",
				requestHeaders: {
//					"If-Modified-Since": (new Date(Builder.dold)).toGMTString(),
//					"If-None-Match": Builder.detag 
				}, 
				onSuccess: function(req) {
					var date = new Date(req.getHeader("Last-Modified"));
					var dnew = date.getTime();
//					if (dnew > Builder.dold) {
						Builder.dold = dnew;
						Builder.detag = req.getHeader("Etag");
						// fetch new content
						var obj = eval("("+req.responseText+")");
						/*
						 * obj array will look like:
						 * {"h_html_9":  {"user": "Testuser", "uid": "9584737"},
						 *  "m_js_6": 121232345
						 * }
						 */
						$$(".blockable").each(function(item) {
							if (!item.up().dirty) {
								if (item.up().hasClassName("dirty") && !obj[item.up().id]) {
									item.hide();
									item.up().removeClassName("dirty");
								} else if (obj[item.up().id] && !item.up().hasClassName("dirty")) {
									item.update('Currently being modified by '+obj[item.up().id].user);
									item.show();
									item.up().addClassName("dirty");
								}
							}
						});
//					}
				}
			});
	    }, 5);
	}
});

(function() {
		window.showHistory = function(target, source) {
			if (source.tagName.toLowerCase() == "textarea") {
				target.setCode(source.value);
			} else if (target.tagName != null && target.tagName.toLowerCase() == "input" && source.tagName.toLowerCase() == "input") {
				target.value = source.value;
			} else {
				var pos = 0;
				source.up().childElements().each(function(item, key){
					if (item == source) pos = key;
				});
				if (source.tagName.toLowerCase() == "code") {
					while (source != null && source.up().up().previous() && (source.innerHTML.length <= 0 && (source.value == null || source.value.length <= 0))) source = source.up().up().previous().down("a code", pos);
				}
				if (source != null) {
					if (target.tagName != null && target.tagName.toLowerCase() == "input" && (source.innerHTML || source.value)) {
						target.value = source.innerHTML || source.value;
					} else if (!target.tagName || target.tagName.toLowerCase() != "input") {
						Builder.setCode(target, source.innerText || source.innerHTML || source.value || "");
					}
				}
			}
		};	
})();
