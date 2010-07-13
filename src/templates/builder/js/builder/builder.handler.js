/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addHandler: function(n) {
		id = 0;
		Builder.addTab("?event=builder:editHandler&module_id="+Builder.currentModule.id+"&handler_id="+id, "New Event", "h_"+id, "handler");		
	},
	editHandler: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Builder.addTab("?event=builder:editHandler&module_id="+Builder.currentModule.id+"&handler_id="+id, n.text, "h_"+id, "handler");
	},
	runHandler: function(module, event) {
		if (module == "Global") {
			var win = window.open(location.href.replace(/\?.*$/im, ""), "_new");
		} else {
			var win = window.open(module+"/"+event, "_new");
		}
		win.focus();
	},
	editNiceUrl: function(name, id) {
		Builder.addTab("?event=builder:niceUrl&handler_id="+id, name, "hr_"+id, "handler-url");
	},
	delHandler: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Ext.Msg.confirm("Delete Event", "Do you really want to delete this event handler?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteHandler', {handler_id:id}, false, function(req) {
					Ext.ux.util.msg("Event deleted", "The event has been successfully deleted.");
					var mod = Builder.root.findChild("id", Builder.currentModule.id);
					var index = -1;
					$A(mod.handlers).each(function(item, key) {
						if (item.id == n.id) {
							index = key;
							throw $break;
						}
					});
					if (index >= 0) {
						mod.handlers.splice(index, 1);
					}
					Builder.handlerRoot.removeChild(n);
					Builder.closeTab(n.id);
				});
			}
		});
	},
	
	browseHandlerHistory: function (id) {
		try {
			$("container_h_"+id).select("ul#ha_"+id)[0].remove();
		} catch (e) {};
		new Ajax.Request("?event=builder:handlerHistory&handler_id="+id, {
			evalJS: true,
			onSuccess: function(req) {
				var el = new Element("div");
				el.update(req.responseText);
				$("container_h_"+id).appendChild(el.down().cloneNode(true));
				$("container_h_"+id).fire("history:loaded");
			}
		});
	}	
});