window.Overlabel = {};
addLoadEvent(Overlabel.init = function() {
	var attachEvents = function(label, element) {
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
		if (element == $(document.activeElement)) {
			label.hide();
		}
	};
	var convertToOverlabel = function(element, content) {
		element = $(element);
		element.overlabelled = true;
		if (element.wrap) {
			element.wrap("span", {"class": 'withLabel'});
		} else {
			var par = element.parentNode;
			element.parentNode.innerHTML = "<span class='withLabel'>" + element.parentNode.innerHTML + "</span>";
			element = par.down("span.withLabel>*");
		}
		element.parentNode.insert({before: label=new Element("label", {"class": 'overlabel-apply'}).update(content).hide()});
		attachEvents(label, element);
		if (element.value.length <= 0) {
			label.show();
		}
	};
	
	$$("label.overlabel, input[type='text'].overlabel, input[type='password'].overlabel, textarea.overlabel").each(function(item) {
		if (item.nodeName.toUpperCase() == "LABEL") {
			convertToOverlabel(item.getAttribute("for"), item.innerHTML);
			$(item).remove();
		} else {
			if (!$(item).up().hasClassName("withLabel")) {
				convertToOverlabel(item, item.getAttribute("label"));
			} else {
				if (!item.overlabelled) {
					// re-attach events
					attachEvents(item.up(".value").down("label.overlabel-apply"), item);
				}
				if (item.value && item.value.length > 0) {
					item.up(".value").down("label.overlabel-apply").hide();
				}
			}
		}
	});	
});