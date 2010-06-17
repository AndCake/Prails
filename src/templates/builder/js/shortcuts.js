/**
 * @author roq
 */

(function(){
	
	// check if prototype is existent
	if (!Prototype || Prototype.Version.length <= 0) return;
	
	document.observe("keyup", function(event){
		var keys = [{
			key: Event.KEY_LEFT,
			callback: function() {
				var w = window.document.parent;
				var spanel = w.Ext.getCmp("qwbuilder_startupPanel");
				var cactive = spanel.getActiveTab();
				cactive.metaDataObj.lastFocus = window;
				var prev = -1;
				spanel.items.each(function(item, key){
					if (item == this) {
						prev = key - 1;
					}
				}.bind(cactive));
				if (prev >= 0) {
					spanel.setActiveTab(prev);
				}
			}
		},{
			key: Event.KEY_RIGHT,
			callback: function() {
				var w = window.document.parent;
				var spanel = w.Ext.getCmp("qwbuilder_startupPanel");
				var cactive = spanel.getActiveTab();
				cactive.metaDataObj.lastFocus = window;
				var prev = -1;
				spanel.items.each(function(item, key){
					if (item == this) {
						prev = key + 1;
					}
				}.bind(cactive));
				if (prev >= 0) {
					spanel.setActiveTab(prev);
				}
			}
		},{
			key: "X",
			callback: function() {
				var w = window.document.parent;
				if (typeof(window.document.run) == "function") {
					window.document.run();
				}
			}
		},{
			key: "Q",
			callback: function() {
				var w = window.document.parent;
				var spanel = w.Ext.getCmp("qwbuilder_startupPanel");
				if (spanel.items[0] != spanel.getActiveTab()) {
					spanel.remove(spanel.getActiveTab());
				}
			}			
		},{
			key: "D",
			callback: function() {
				var w = window.document.parent;
				w.Builder.quickOpen();
			}
		}]; 
		
		if (event.ctrlKey && event.shiftKey) {
			try {
				keys.each(function(key){
					if (isNaN(parseInt(key.key))) key.key = key.key.toUpperCase().charCodeAt(0);
					if (key.key == event.keyCode) {
						event.stop();
						key.callback();
						window.document.parent.focus();
						throw $break;
					}
				});
			} catch (e){};
		}
	});
	
})();