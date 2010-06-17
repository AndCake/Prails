/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	editModuleResource: function(n) {
		Builder.addTab("?event=builder:listResources&module_id="+n.id+"", n.text + " Resources", "rm_"+n.id, "resource");
	},
	
	editModuleResourceItem: function(n, mid) {
		var id = (""+n.id).split(/_/g)[1];
		var url = "?event=builder:editResource&resource_id="+id+"&module_id="+mid; 
		Ext.getCmp("rm_"+mid+"_container").load({
			url: url,
			timeout: 30,
			scripts: true									
		});		
	},
	
	delModuleResourceItem: function(n, mid) {
		var id = (""+n.id).split(/_/g)[1];
		Ext.Msg.confirm("Delete Resource", "Do you really want to delete this resource?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteResource', {resource_id:id}, false, function(req) {
					Ext.ux.util.msg("Resource deleted", "The resource has been successfully deleted.");
					Ext.getCmp("rm_"+mid+"_tree").getRootNode().removeChild(n);
				});
			}
		});		
	}
});