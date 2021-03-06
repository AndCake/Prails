/**
 * @author Robert Kunze
 */
Builder = Object.extend(Builder || {}, {
    createSubSection: function(parentNode) {
        Ext.Msg.prompt("Section Name", "Please enter a name for the new section:", function(btn, text) {
            if (btn == "ok") {
		pid = parentNode.id;
		if (parentNode == Builder.langRoot) pid = "text_";
                parentNode.appendChild(new Ext.tree.TreeNode({
                    text: text,
		    expandable: true,
                    leaf: false,
		    id: pid+"."+text
                }));
            }
        });
	},
	
	createText: function(parentNode) {
		var loop = parentNode.id.replace("text_.", "")+".";
/*		do {
			loop = parentNode.text+"." + loop;
			parentNode = parentNode.parentNode;
		} while (parentNode != Builder.langRoot); */
		Builder.addTab("?event=builder:editText&path="+loop+"&texts_id=0&extjs=1", "New Text", "x_", "locale");
	},
	
	editText: function(n) {
		if (!n.id && n.ident) {
			Builder.addTab("?event=builder:editText&ident="+n.ident+"&path="+n.ident+"&extjs=1", n.text, "x_"+n.ident, "locale");
		} else { 
		    var id = n.id.replace("text_.", "");
		    Builder.addTab("?event=builder:editText&ident="+id+"&extjs=1", n.text, "x_"+id, "locale");
		}
	},
	
	deleteText: function(n) {
	    var id = n.id.replace("text_.", "");
		Ext.Msg.confirm("Delete Text", "Do you really want to delete this text?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteText', {ident:id}, false, function(req) {
					Ext.ux.util.msg("Text deleted", "The text has been successfully deleted.");
					n.parentNode.removeChild(n);
					Builder.closeTab("x_"+id)
				});
			}
		});
	},
	
    deleteSection: function(n) {
        var id = n.id.replace("text_.", "");
		Ext.Msg.confirm("Delete Section", "Do you really want to delete the section \""+n.text+"\" including all of it's contents?", function(btn){
			if (btn == "yes") {
				invoke(null, 'builder:deleteText', {section:id}, false, function(req) {
					Ext.ux.util.msg("Section deleted", "The section has been successfully deleted.");
                    loadURL("?event=builder:home&open_nav=qwbuilder_langsPanel");
				});
			}
		});
    }
});
