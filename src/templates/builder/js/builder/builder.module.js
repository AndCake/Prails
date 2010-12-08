/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addModule: function(n) {
		Builder.addTab("?event=builder:editModule&module_id=0&extjs=1", "New Module", "m_0");
	},
	
	editModule: function(n) {
		Builder.resetTree(Builder.handlerRoot, n.handlers);
		Builder.resetTree(Builder.dataRoot, n.datas);
		Ext.getCmp("qwbuilder_detailsPanel").setTitle(n.text+" Module Contents");
		Ext.getCmp("qwbuilder_detailsPanel").enable();
		Ext.getCmp("qwbuilder_detailsPanel").expand(true);
		if (parseInt(n.id) < 0) {
			Ext.getCmp("qwbuilder_dataPanel").disable();
			Ext.getCmp("qwbuilder_handlerPanel_addEvent").disable();
			Ext.getCmp("qwbuilder_handlerPanel_deleteEvent").disable();
		} else {
			Ext.getCmp("qwbuilder_dataPanel").enable();
			Ext.getCmp("qwbuilder_handlerPanel_addEvent").enable();
			Ext.getCmp("qwbuilder_handlerPanel_deleteEvent").enable();
		}
		Builder.currentModule = n;
	},
	
	editModuleOptions: function(n) {
		Builder.addTab("?event=builder:editModule&module_id="+n.id+"&extjs=1", n.text, "m_"+n.id);
	},
	
	delModule: function(n) {
		Ext.Msg.confirm("Delete Module", "Do you really want to delete this whole module including all of it's events and queries?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteModule', {module_id:n.id}, false, function(req) {
					Ext.ux.util.msg("Module deleted", "The module has been successfully deleted.");
					Builder.root.removeChild(n);
					Builder.closeTab("m_"+n.id)
				});
			}
		});
	},
	
	editConfiguration: function(o) {
		Builder.addTab("?event=builder:editConfiguration&module_id="+o.id+"", o.text, "c_"+o.id, "config");
	},
	
	browseModuleHistory: function (id) {
		try {
			$("container_m_"+id).select("ul#h_"+id)[0].remove();
		} catch (e) {};
		new Ajax.Request("?event=builder:moduleHistory&module_id="+id, {
			evalJS: true,
			onSuccess: function(req) {
				/** @TODO - use bespin diff here, if possible **/				
				var el = new Element("div");
				el.update(req.responseText);
				$("container_m_"+id).appendChild(el.down().cloneNode(true));
				$("container_m_"+id).fire("history:loaded");
			}
		});
	}
});