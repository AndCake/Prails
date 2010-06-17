/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addTable: function(n) {
		id = 0;
		Builder.addTab("?event=builder:editTable&table_id="+id, "New Table", "db_"+id, "table");		
	},
	editTable: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Builder.addTab("?event=builder:editTable&table_id="+id, n.text, "db_"+id, "table");
	},
	delTable: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Ext.Msg.confirm("Delete Table", "Do you really want to delete this Table from the Database?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteTable', {table_id:id}, false, function(req) {
					Ext.ux.util.msg("Table deleted", "The Table has been successfully removed from the Database.");
					Builder.dbRoot.removeChild(n);
					Builder.closeTab(n.id);
				});
			}
		});
	}
});