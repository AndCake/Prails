/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
	editTestcases: function(n) {
		Builder.addTab("?event=builder:listTestcase&module_id="+n.id+"&fetch="+n.id+"", n.text, "tc_"+n.id, "testcase");
	},
	
	editTestcase: function(n, mid) {
		var id = (""+n.id).split(/_/g)[1];
		var url = "?event=builder:editTestcase&testcase_id="+id+"&module_id="+mid;
		Ext.getCmp("tc_"+mid+"_container").load({
			url: url,
			timeout: 30,
			scripts: true									
		});		
	},
	
	delTestcase: function(n, mid) {
		var id = (""+n.id).split(/_/g)[1];
		Ext.Msg.confirm("Delete Testcase", "Do you really want to delete this testcase?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteTestcase', {testcase_id:id}, false, function(req) {
					Ext.ux.util.msg("Testcase deleted", "The testcase has been successfully deleted.");
					Ext.getCmp("tc_"+mid+"_tree").getRootNode().removeChild(n);
				});
			}
		});		
	},
	
	runTestcase: function(n, name) {
		var id = (""+n.id).split(/_/g)[1];
		window.__viewLog();
		window.__runTest({id: id, text: "Testcase "+name, type: "testcase"});
	}
});