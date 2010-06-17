/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addLibrary: function(n) {
		id = 0;
		var mid = 0;
		Builder.addTab("?event=builder:editLibrary&module_id="+mid+"&library_id="+id, "New Library", "l_"+id, "library");		
	},
	editLibrary: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Builder.addTab("?event=builder:editLibrary&library_id="+id, n.text, "l_"+id, "library");
	},
	delLibrary: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Ext.Msg.confirm("Delete Library", "Do you really want to delete this Library?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteLibrary', {library_id:id}, false, function(req) {
					Ext.ux.util.msg("Library deleted", "The Library has been successfully deleted.");
					Builder.libRoot.removeChild(n);
					Builder.closeTab(n.id);
				});
			}
		});
	}, 
	listLibrary: function(n) {
		invoke(null, "builder:listLibrary", {module_id: n.id}, false, function(req) {
			// make the req.responseText create a var called "newNodes", which is an 
			// array that contains TreeNodes to be inserted...
			Builder.resetTree(Builder.libRoot, newNodes);			
		});
	}
});
