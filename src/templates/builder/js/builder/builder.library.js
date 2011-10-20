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
		if (id > 0) {
			Builder.addTab("?event=builder:editLibrary&library_id="+id, n.text, "l_"+id, "library");
		}
	},
	delLibrary: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		if (id > 0) {
			Ext.Msg.confirm("Delete Library", "Do you really want to delete this Library?", function(btn){
				if (btn == "yes") {
					invoke(null, 'builder:deleteLibrary', {library_id:id}, false, function(req) {
						Ext.ux.util.msg("Library deleted", "The Library has been successfully deleted.");
						Builder.libRoot.removeChild(n);
						Builder.closeTab(n.id);
					});
				}
			});
		}
	}, 
	listLibrary: function(n) {
		invoke(null, "builder:listLibrary", {module_id: n.id}, false, function(req) {
			// make the req.responseText create a var called "newNodes", which is an 
			// array that contains TreeNodes to be inserted...
			Builder.resetTree(Builder.libRoot, newNodes);			
		});
	},
	uploadLibrary: function() {
		new Ext.Window({
			layout: "fit",
			title: "Upload Library",
			iconCls: "library",
			modal: true,
			shadow: true,
			width: 441,
			height: 404,
			plain: true,
			html: $("library_upload_panel").innerHTML.replace("<!--", '').replace("-->", '')
		}).show();
		QuixoticWorxUpload.init();							        						
		// show upload window including text field for entering the library's name
		// all data that is uploaded here, is put into the LONGBLOB field of a new resource
		// this one is references in the library table - so if something is uploaded, then the
		// default code value for the library should be something like: include("mylib.php"); // change accordingly
		// when executed, the linked resource is put into the file system (lib/custom/<library name>/...) and
		// in case it's zipped/compressed it's also unzipped accordingly.
	}	
});
