/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addData: function(n) {
		id = 0;
		Builder.addTab("?event=builder:editData&module_id="+Builder.currentModule.id+"&data_id="+id, "New Query", "d_"+id, "data");		
	},
	editData: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Builder.addTab("?event=builder:editData&module_id="+Builder.currentModule.id+"&data_id="+id, n.text, "d_"+id, "data");
	},
	queryTest: function() {
		Builder.addTab("?event=builder:queryTest", "SQL Query", "query_test", "data");
	},
	delData: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Ext.Msg.confirm("Delete Query", "Do you really want to delete this Query?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteData', {data_id:id}, false, function(req) {
					Ext.ux.util.msg("Query deleted", "The Data Query has been successfully deleted.");
					var mod = Builder.root.findChild("id", Builder.currentModule.id);
					var index = -1;
					$A(mod.datas).each(function(item, key) {
						if (item.id == n.id) {
							index = key;
							throw $break;
						}
					});
					if (index >= 0) {
						mod.datas.splice(index, 1);
					}
					Builder.dataRoot.removeChild(n);
					Builder.closeTab(n.id);
				});
			}
		});
	}, 
	browseDataHistory: function(id) {
		try {
			$("container_d_"+id).select("ul#d_"+id)[0].remove();
		} catch (e) {};
		new Ajax.Request("?event=builder:dataHistory&data_id="+id, {
			evalJS: true,
			onSuccess: function(req) {
				var el = new Element("div");
				el.update(req.responseText);
				$("container_d_"+id).appendChild(el.down().cloneNode(true));
				$("container_d_"+id).fire("history:loaded");
			}
		});		
	}
});