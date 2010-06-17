var EvalFields = {
	toCheck: new Array(),
	bStarted: false,

	findFields: function() {
		var elements = document.getElementsByTagName("input");
		for (var i = 0; i < elements.length; i++) {
			if (elements[i].getAttribute("pat") != null && elements[i].getAttribute("ref") != null) {
				EvalFields.addPatternCheck(elements[i]);
				$(elements[i].getAttribute("ref")).origState = ($(elements[i].getAttribute("ref")).disabled == true ? true:false);
			}
		}
		if (!EvalFields.bStarted && EvalFields.toCheck.length > 0)
			EvalFields.checkPatterns();
	},

	addPatternCheck: function(field) {
		EvalFields.toCheck.push(field);
	},

	checkPatterns: function() {
		bStarted = true;
		var ref = "";
		if (EvalFields.toCheck.length > 0) {
			var found = false;
			for (var i = 0; i < EvalFields.toCheck.length; i++) {
				var field = EvalFields.toCheck[i];
				if (field.getAttribute("ref") && field.getAttribute("pat")) {
					ref = field.getAttribute("ref");
					var pattern = field.getAttribute("pat");
					var valid = field.getAttribute("onvalid") || "return false;";
					var invalid = field.getAttribute("oninvalid") || "return false;";
					callback = function(state) {
						if (state) eval(valid); else eval(invalid);
					};
					if (!field.value.match(pattern)) {
						field.className = field.className.replace(" inValidValue", "").replace("inValidValue ", "").replace("inValidValue") + " inValidValue";
						callback(false);
						found = true;
			 		} else {
						field.className = field.className.replace(" inValidValue", "").replace("inValidValue ", "").replace("inValidValue");
						callback(true);
			 		}
			 	}
		 	}
		 	if (found) {
				$(ref).disabled = true;
		 	} else {
		 		$(ref).disabled = $(ref).origState;
			}
		}
		window.setTimeout(function(){EvalFields.checkPatterns();}, 250);
	}
};

addLoadEvent(EvalFields.findFields);