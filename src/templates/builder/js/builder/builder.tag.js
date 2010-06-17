/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	addTag: function(n) {
		id = 0;
		Builder.addTab("?event=builder:editTag&tag_id="+id, "New Tag", "t_"+id, "tagLib");		
	},
	editTag: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Builder.addTab("?event=builder:editTag&tag_id="+id, n.text, "t_"+id, "tagLib");
	},
	delTag: function(n) {
		var a_id = n.id.split(/_/gm);
		var id = parseInt(a_id[1]);
		Ext.Msg.confirm("Delete Tag", "Do you really want to delete this Tag from the Tag Lib?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteTag', {tag_id:id}, false, function(req) {
					Ext.ux.util.msg("Tag deleted", "The Tag has been successfully removed from the Tag Lib.");
					Builder.tagRoot.removeChild(n);
					Builder.closeTab(n.id);
				});
			}
		});
	}
});