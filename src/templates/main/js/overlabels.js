window.Overlabel = {};
addLoadEvent(Overlabel.init = function() {
	var convertToOverlabel = function(element, content) {
		element = $(element);
		element.wrap("span", {"class": 'withLabel'});
		element.parentNode.insert({before: label=new Element("label", {"class": 'overlabel-apply'}).update(content).hide()});
		label.observe("click", function() {
			this.hide();
			element.focus();
		}).observe("mouseover", function() {
			this.addClassName("hover");
		}).observe("mouseout", function(){
			this.removeClassName("hover");
		});
		
		$(element).observe("blur", function() {
			if (this.value.length <= 0) {
				this.up().up().down("label").show();
			}
		});
		$(element).observe("focus", function() {
			this.up().up().down("label").hide();
		});
		if (element.value.length <= 0) {
			label.show();
		}
	};
	
	$$("label.overlabel, input[type='text'].overlabel, textarea.overlabel").each(function(item) {
		if (item.nodeName.toUpperCase() == "LABEL") {
			convertToOverlabel(item.getAttribute("for"), item.innerHTML);
			$(item).remove();
		} else {
			if (!$(item).up().hasClassName("withLabel")) {
				convertToOverlabel(item, item.getAttribute("label"));
			}
		}
	});	
});
