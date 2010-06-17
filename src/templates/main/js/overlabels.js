var OverLabels = {
    init: function () {
	   if (!document.getElementById) return;

        var labels, id, field;

		// Set focus and blur handlers to hide and show
		// LABELs with 'overlabel' class names.
		labels = document.getElementsByTagName('label');
		for (var i = 0; i < labels.length; i++) {

		  if (labels[i].className == 'overlabel') {

		  // Skip labels that do not have a named association
		  // with another field.
		  id = labels[i].htmlFor || labels[i].getAttribute('for');
		  if (!id || !(field = document.getElementById(id))) {
		      continue;
		  }

		  // Change the applied class to hover the label
		  // over the form field.
		  labels[i].className = 'overlabel-apply';

		  // Hide any fields having an initial value.
		  if (field.value !== '') {
		      OverLabels.hideLabel(field.getAttribute('id'), true);
		  }

		  // Set handlers to show and hide labels.
		  field.observe('focus', function(event) {
		      OverLabels.hideLabel(event.element().getAttribute('id'), true);
		  });
		  field.observe('blur', function (event) {
		      if (event.element().value === '') {
		          OverLabels.hideLabel(event.element().getAttribute('id'), false);
	          }
		  });

		  // Handle clicks to LABEL elements (for Safari).
          labels[i].observe('click', function (event) {
		      var id, field;
		      id = event.element().getAttribute('for');
		      if (id && (field = document.getElementById(id))) {
		          field.focus();
		      }
          });

        }
      }
    },

    hideLabel: function (field_id, hide) {
	  var field_for;
	  var labels = document.getElementsByTagName('label');
	  for (var i = 0; i < labels.length; i++) {
	    field_for = labels[i].htmlFor || labels[i].getAttribute('for');
	    if (field_for == field_id) {
	      labels[i].style.display = (hide) ? 'none' : '';
	      return true;
	    }
	  }
	}
};

addLoadEvent(OverLabels.init);