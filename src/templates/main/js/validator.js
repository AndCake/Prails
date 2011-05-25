/**
 * Simple Form Validation, controlled by HTML alone
 *
 * Copyright (c) 2011 Robert Kunze
 *
 * Validation syntax:
    <form method="post" action="">
        <div class="validate-email-error" error="empty=Your eMail address is important!|invalid=Oh! Seems you have a type in your eMail address."></div>
        <div class="required-error" error="empty=Don't want to give us your eMail address? Oh, please..."></div>
        <input type="text" name="email" class="validate-email required" />
        <button type="submit">send</button>
    </form>
 *
 * CSS classes used + generated:
 * =============================
 *
 * Field Classes (provided by user):
 * - required: this field is required
 * - validate-number: this field needs to be a number
 * - validate-alpha: this field needs to consist of letters only
 * - validate-alphanum: this field needs to contain only word characters
 * - validate-date: this field needs to contain a date (yyyy-mm-dd)
 * - validate-email: this field needs to contain an email address
 * - validate-url: this field needs to contain a valid URL
 * - no-cancel: set for form; if set, then invalid fields won't stop form submission
 *
 * Field classes (generated):
 * - validate-valid: this field has been detected as valid
 * - validate-invalid: this field has been detected as invalid
 * - validate-valid-hint: this element will be put after the current form field, indicating, that the current field has been correctly filled in. No content.
 * - validate-advice: this element will be put after the current form field, giving the user some advice on what to fill in this field. Text content.
 *
 * By default the internally defined expressions are checked for. But you may extend and override them using elements with the corresponding CSS class:
 * - validate-number-error: override definition of validate-number.
 * - validate-<anything>-error: override / create definition of validate-<anything>.
 * 
 * The element containing exactly one of the above CSS classes will be checked for a "rel" attribute, defining the regular expression (or some custom Javascript 
 * code, if prepended with "javascript:") to use for validation and also checked for a "error" attribute, which can contain the error messages to be shown 
 * for this kind of field. Both are optional. 
 * Furthermore you can override these globally-defined classes by specifying the "rel" or/and the "error" attribute in the element itself to be validated.
 * The error attribute must be structured like this: "empty=<text to show, if this field is empty>|invalid=<text to show, if this field is invalid>"
 *
 * Example form:
 * =============
 
<form method="post" action="?submitted">

    <!-- definition of a new validator, called "meiner", which does not accept any alpha character -->
    <div class="validate-meiner-error" error="empty=My own empty field.|invalid=Hey! It's invalid..." rel="^[^a-z]+$"></div>
    <fieldset>
        <legend>Login</legend>
        <div class="formfield">
            <label for="test">eMail</label>
            
            <!-- using the standard email validator -->
            <input type="text" name="email" id="test" class="required validate-email" />
        </div>
        <div class="formfield">
            <label for="test2">Password</label>
            
            <!-- using no special validator, but a dedicated valid pattern and invalid advice text -->
            <input type="password" name="password" id="test2" class="required" rel=".{6,}" error="invalid=Your password must have at least 6 characters." />
        </div>
        <div class="formfield">
            <label for="test3">No Chars</label>
            
            <!-- using the previously defined "meiner" validator -->
            <input type="text" name="chars" id="test3" class="required validate-meiner" />
        </div>
        <div class="formfield">
            <label for="test4">Gender</label>
            <select name="gender" id="test4" size="1" class="required">
                <option value="">select one</option>
                <option value="m">male</option>
                <option value="f">female</option>
            </select>
         </div>
        <div class="formfield">
            <label for="test5">School</label>
            
            <!-- using a validator for a group of radio boxes -->
            <div class="radiobox required">
                <div class="radio">
                    <input type="radio" name="school" value="1" id="gs" />
                    <label for="gs">Grundschule</label>
                </div>
                <div class="radio">
                    <input type="radio" name="school" value="2" id="sek1" />
                    <label for="sek1">Sekundarstufe 1</label>
                </div>
                <div class="radio">
                    <input type="radio" name="school" value="3" id="sek2" />
                    <label for="sek2">Sekundarstufe 2</label>
                </div>
                <div class="radio">
                    <input type="radio" name="school" value="4" id="uni" />
                    <label for="uni">Hochschule</label>
                </div>
            </div>
        </div>
        <button type="submit">Login</button>
    </fieldset>
</form> 
 *
 *
 */

var Validator = Class.create({
    
    validExpression: {
        required: {matcher: /^.+$/, text: "This is a required field."},
        number: {matcher: /^-{0,1}[0-9]+(\.[0-9]+)?$/, text: "Please enter a valid number in this field."},
        alpha: {matcher: /^[a-zA-Z]+$/, text: "Please use letters only (a-z) in this field."},
        alphanum: {matcher: /^\W+$/, text: "Please use only letters (a-z) or numbers (0-9) only in this field. No spaces or other characters are allowed."},
        date: {matcher: /([0-9]{4}-[0-9]{2}-[0-9]{2})|([0-9]{2}\.[0-9]{2}\.[0-9]{4})/, text: "Please enter a valid date."},
        email: {matcher: /^[a-zA-Z0-9.\-_!#$%&'*+\/=?\^_`{|}~]+[@][\w\-]{1,}([.]([\w\-]{1,})){1,3}$/, text: "Please enter a valid email address. For example fred@example.com ."},        
        url: {matcher: /^(http|https|ftp):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?\/?/i, text: "Please enter a valid URL."}
    },
    
    initialize: function(form, options) {
        this.options = Object.extend({
            cancelSubmit: true,
            animated: true
        }, options || {});
        this.form = form;
        
        var me = this;
        
        if (this.options.cancelSubmit) {
            form.observe("submit", function(event) {
                if (!me.checkAllValid()) {
                    event.stop();
                }
            });
        }
        
        var elements = form.getElements();
        $A(elements).each(function(el) {
            if ((el.hasClassName("required") || (el.type.toLowerCase() == "radio" && el.up(".required"))) && !el.__validator_extended) {
                el.observe((el.type.toLowerCase() == "radio" && el.up(".required") ? "click" : "blur"), function(event) {
                    me.validate(this);
                });
                
                el.observe("focus", function(event) {
                    if (this.hasClassName("required")) {
                        me.reset(this);
                    } else {
                        me.reset(this.up(".required"));
                    }
                });
                el.__validator_extended = true;
            }
        });
    },
    
    checkAllValid: function() {
        var me = this;
        var result = true;
        var elements = this.form.getElements();
        $A(elements).each(function(el) {
            if (el.hasClassName("required") || (el.type.toLowerCase() == "radio" && el.up(".required"))) {
                var res = me.validate(el);
                result = result && res;
            }
        });
        
        return result;
    },
    
    validate: function(el) {
        el = $(el);        
        var me = this;
        if (el.hasClassName("required") && this.isVisible(el)) {
            this.reset(el);
            if (el.value.empty()) {
                this.invalidate(el, "empty");
                return false;
            } else if (el.getAttribute("rel") && el.getAttribute("rel").indexOf("javascript:")<0 && !new RegExp(el.getAttribute("rel")).test(el.value)) {
                this.invalidate(el, "invalid");
                return false;
            } else if (el.getAttribute("rel") && el.getAttribute("rel").indexOf("javascript:")>=0 && (function(){return !eval("("+el.getAttribute("rel").replace("javascript:", "")+")");}).call(el)) {
				this.invalidate(el, "invalid");
				return false;
		    } else {
                var result = true;
                el.classNames().each(function(cls) {
                    if (cls.indexOf("validate-") >= 0) {
                        var reg = "";
                        $$("."+cls+"-error[rel]").each(function(item) {
                            // use reg exp as defined in error tag
                            if (!item.getAttribute("rel").empty()) {
                                reg = new RegExp(item.getAttribute("rel"));
                                throw $break;
                            }
                        });
                        if (reg == "") {
                            // use built-in reg exp
                            reg = (me.validExpression[cls.replace("validate-", "")] || {matcher:/.*/}).matcher;
                        }
                        if (!reg.test(el.value)) {
                            me.invalidate(el, "invalid");
                            result = false;
                        }
                    }
                });
                
                if (!result) return false;
                
                // show that it's correct
                this.hideError(el);
                this.showSuccess(el);
            }
        } else if (el.type.toLowerCase() == "radio" && el.up(".required") && this.isVisible(el)) {
            var container = el.up(".required");
            var oneChecked = false;
            this.reset(container);            
            container.select("input[type='radio']").each(function(radio) {
                if (radio.checked) {
                    oneChecked = true;
                    throw $break;
                }
            });
            if (!oneChecked) {
                this.invalidate(container, "empty");
                return false;
            } else {
                this.hideError(container);
                this.showSuccess(container);
            }
         }
        
        return true;
    },
    
    invalidate: function(el, type) {
        var me = this;
        el = $(el);
        var errorText = me.validExpression["required"].text;
        $$(".required-error").each(function(item) {
            var res = me.parseTitle(item.getAttribute("error"));
            if (res[type]) {
                errorText = res[type];
                throw $break;
            }
        });
        el.classNames().each(function(cls) {
            if (cls.indexOf("validate-") >= 0) {
                if (type != "empty")
                    errorText = (me.validExpression[cls.replace("validate-", "")] ? me.validExpression[cls.replace("validate-", "")].text : "");
                $$("."+cls+"-error").each(function(item) {
                    var res = me.parseTitle(item.getAttribute("error"));
                    if (res[type]) {
                        errorText = res[type];
                        throw $break;
                    }
                });
            }
        });
        if (el.getAttribute("error")) {
            var txt = this.parseTitle(el.getAttribute("error"))[type];
            if (txt && !txt.empty())
                errorText = txt;
        }
        me.showError(el, errorText);
    },
        
    showError: function(el, text) {
        el = $(el);
        el.addClassName("validate-invalid");
        var inp = el;
        if (inp.value === undefined) {
        	inp = el.down("input[type='radio']");
        }
        var advice = new Element("div", {id: inp.name+"-"+inp.id+"-advice", "class": "validate-advice"});
        advice.update(text);
        el.insert({after: advice});
        var height = advice.getHeight();
        advice.hide();
        if (this.options.animated) {
			advice.setStyle("display:block;height:0em;opacity:0;");
            advice.morph("height:"+height+"px;opacity:1;", {duration: 0.5});
        } else
            advice.show();
    },
    
    showSuccess: function(el) {
        el = $(el);
        el.addClassName("validate-valid");
        var hint = new Element("div", {id: el.name+"-"+el.id+"-advice", "class": "validate-valid-hint", style: "display: none;"}).update("&nbsp;");
        el.insert({after: hint});
        if (this.options.animated)
            hint.appear({duration: 0.5});
        else
            hint.show();
    },
    
    hideSuccess: function(el) {
        el = $(el);
        if (el.next() && el.next().hasClassName("validate-valid-hint")) {
            el.next().remove();
        }
        el.removeClassName("validate-valid");
    },
    
    hideError: function(el) {
        el = $(el);
        if (el.next() && el.next().hasClassName("validate-advice")) {
            el.next().remove();
        }
        el.removeClassName("validate-invalid");
    },
    
    reset: function(el) {
        this.hideError(el);
        this.hideSuccess(el);
    },
    
	isVisible : function(elm) {
		while(elm.tagName != 'BODY') {
			if(!$(elm).visible()) return false;
			elm = elm.parentNode;
		}
		return true;
	},    
    
    parseTitle: function(title) {
        if (!title) return [];
        var result = {};
        var parts = title.split("|");
        $A(parts).each(function(part) {
            var keyValues = /^([a-z]+)=(.+)$/.exec(part);
            result[keyValues[1]] = keyValues[2];
        });
        
        return result;
    }
});

// initialize form validator for all forms
addLoadEvent(function() {
    $A(document.body.getElementsByTagName("form")).each(function(form){
        if (!form.validator) {
            var options = {};
            if (form.hasClassName("no-cancel")) {
                options["cancelSubmit"] = false;
            }
            form.validator = new Validator(form, options);
        }
    });
});
