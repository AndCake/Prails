/**
 * Useful stuff for Test-Driven Development
 * 
 * @author Robert Kunze
 */

// default test target window
window.__testTargetWindow = top;

// definition of all assertions
var TestingAssertions = {
		assert: function(a) { report("log", "Executing assert("+a+")"); if (!a) throw "Assertion failed! "+a+" is not true."; window.__currentCommandComplete = true; },
		assertEqual: function(a,b) { report("log", "Executing assertEqual("+a+", "+b+")"); if (a != b) throw "Assertion failed! "+a+" != "+b; window.__currentCommandComplete = true; },
		assertNotEqual: function(a,b) { report("log", "Executing assertNotEqual("+a+", "+b+")"); if (a == b) throw "Assertion failed! "+a+" == "+b; window.__currentCommandComplete = true; },
		assertExists: function(a) { report("log", "Executing assertExists("+a+")"); if (__testTargetWindow.$$(a).length <= 0) throw "Assertion failed! "+a+" does not exist."; window.__currentCommandComplete = true; },
		assertNotExists: function(a) { report("log", "Executing assertNotExists("+a+")"); if (__testTargetWindow.$$(a).length > 0) throw "Assertion failed! "+a+" does exist."; window.__currentCommandComplete = true; },
		assertText: function(a, b) { report("log", "Executing assertText("+a+", "+b+")"); if (__testTargetWindow.$$(a).length < 0) throw "Assertion failed! "+a+" does not exist."; if (__testTargetWindow.$$(a)[0].innerText.search(b) < 0) throw "Assertion failed! "+b+" not found in "+a+"."; window.__currentCommandComplete = true; },
		assertTextExists: function(a) { report("log", "Executing assertTextExists("+a+")"); if (__testTargetWindow.document.body.innerText.search(a) < 0) throw "Assertion failed! "+a+" not found on page."; window.__currentCommandComplete = true; },
		assertTextNotExists: function(a) { report("log", "Executing assertTextNotExists("+a+")"); if (__testTargetWindow.document.body.innerText.search(a) >= 0) throw "Assertion failed! "+a+" found on page."; window.__currentCommandComplete = true; },
		assertLocationEqual: function(a) { report("log", "Executing assertLocationEqual("+a+")"); if (__testTargetWindow.location.href.search(a) < 0) throw "Assertion failed! Not on location "+a+"."; window.__currentCommandComplete = true; },
		assertLocationNotEqual: function(a) { report("log", "Executing assertLocationNotEqual("+a+")"); if (location.href.search(a) >= 0) throw "Assertion failed! On location "+a+"."; window.__currentCommandComplete = true; },
		assertVisible: function(a) { report("log", "Executing assertVisible("+a+")"); if (!__testTargetWindow.$$(a)[0].visible()) throw "Assertion failed! "+a+" not visible."; window.__currentCommandComplete = true; },
		assertNotVisible: function(a) { report("log", "Executing assertNotVisible("+a+")"); if (__testTargetWindow.$$(a)[0].visible()) throw "Assertion failed! "+a+" visible."; window.__currentCommandComplete = true; }
};

// install assertions
for (var a in TestingAssertions) {
	window[a] = TestingAssertions[a];
}

// definition of all actions
var TestingActions = { 
		clickAtAndWait: function(el) {
			clickAt(el, false);
			setTimeout(function() {
				waitFor("body");
			}, 100);
		},

		clickAt: function(el, complete) {
			report("log", "Clicking at "+el);
			if (__testTargetWindow.$$(el).length > 0) {
				__testTargetWindow.$$(el).each(function(item) {
					item.simulate("focus");
					item.simulate("click");
					if (complete !== false) {
						window.__currentCommandComplete = true;				
					}
				});
			} else {
				throw "Unable to click at "+el+". Element not found!";
			}
		},

		enterValue: function(el, value) {
			report("log", "Entering value "+value+" at "+el);
			if (__testTargetWindow.$$(el).length > 0) {
				__testTargetWindow.$$(el).each(function(item) {
					item.simulate("focus");
					item.value = value;
					window.__currentCommandComplete = true;			
				});
			} else {
				throw "Unable to enter a value to "+el+". Element not found!";
			}
		},

		waitFor: function(el, counter, complete) {
			if (counter == null) {
				report("log", "Waiting for "+el);
				counter = 300; 
			}
			
			if (!__testTargetWindow.$$ || __testTargetWindow.$$(el).length <= 0) {
				if (counter > 0) {
					setTimeout(function() {
						waitFor(el, counter--, complete);
					}, 100);
				} else {
					throw "Unable to waitFor "+el+". Timed out.";
				}
			} else {
				if (complete !== false) {
					window.__currentCommandComplete = true;			
				}
			}
		},

		open: function(url) {
			if (url.indexOf("http://") < 0 || url.indexOf("https://") < 0) url = baseHref + url;
			report("log", "Opening "+url);
			__testTargetWindow.location.href = url;
			setTimeout(function() {
				waitFor("body");
			}, 100);	
		}
};

// install actions
for (var a in TestingActions) {
	window[a] = TestingActions[a];
}

// definition of testsuites and cases
var TestingFixture = {
		Testcase: function(options) {
			options = Object.extend({
				name: "",
				run: [],
				setup: [],
				teardown: []
			}, options || {});
			
			this.name = options.name;
			this.options = options;
			this.setup = function(scope) {
				this.options.setup.push(function(){
					scope.__currentCommandComplete = true;
				});
				commandExecuter(this.options.setup, window);
			}.bind(this);
			this.run = function(scope) {
				this.options.run.push(function(){
					scope.__currentCommandComplete = true;
				});
				commandExecuter(this.options.run, window);
			}.bind(this);
			this.teardown = function(scope) {
				this.options.teardown.push(function(){
					scope.__currentCommandComplete = true;
				});
				commandExecuter(this.options.teardown, window);
			}.bind(this);
	
			var that = this; 
			window.__currentTestcase = this;
			this.start = function(scope) {
				commandExecuter([
				         function() { that.setup(that); },
		                 function() { that.run(that); },
		                 function() { that.teardown(that); },
		                 function() { scope.__currentCommandComplete = true; report("success", "Finished Testcase "+that.name); }
		                ], that);
			};
	},

	Testsuite: function(options) {
		options = Object.extend({
			name: "",
			targetWindow: null,
			testcases: []
		}, options || {});
	
		if (options.targetWindow) {
			window.__testTargetWindow = options.targetWindow;
		}
		this.name = options.name;
		this.testcases = options.testcases;
	
		var that = this;
		this.addTestcase = function(testcase) {
			that.testcases.push(testcase);
		};
	
		this.start = function(scope) {
			if (!scope) scope = that;
			var initial = false;
			if (!window.testStarted) {
				window.testStarted = true;
				initial = true;
			}
			report("log", "Running testsuite "+that.name+"...");
			var fns = [];
			$A(that.testcases).each(function(item) {
				fns.push(function() {
					report("log", "Running testcase "+this.name+"...");
					this.start(that);
				}.bind(item));
			}); 
			fns.push(function() {
				if (initial) {
					window.testStarted = false;
				}
				// if we got here, everything went through perfectly
				report("success", "Finished Testsuite "+this.name);
				this.scope.__currentCommandComplete = true;
			}.bind({scope: scope, name: that.name}));
			commandExecuter(fns, that);
		}
	}
};

// install fixture
for (var a in TestingFixture) {
	window[a] = TestingFixture[a];
}


// definition of internal functions
var commandExecuter = function(commandList, scope) {
	if (!commandList || commandList.length <= 0) {
		return;
	} 
	scope.__currentCommandComplete = true;
	new PeriodicalExecuter(function(pe) {
		if (this.scope.__currentCommandComplete) {
			if (this.commandList.length <= 0) {
				pe.stop();
			}
			var command = this.commandList.shift();
			this.scope.__currentCommandComplete = false;
			try {
				if (typeof(command) != "function") {
					if (typeof(command) == "string" && command.replace(/^\s+|\s+$/gi, "").length > 0) {
						eval(command);
					} else {
						this.scope.__currentCommandComplete = true;
					}
				} else {
					command();
				}
			} catch(e) {
				report("error", e);
				// halt on error
				window.testStarted = false;				
				pe.stop();
				this.scope.__currentCommandComplete = true;
			}
		}
	}.bind({scope: scope, commandList: commandList}), 0.1);
};

var getStackTrace = function(e) {
	var callstack = [];
	var isCallstackPopulated = false;
    if (e.stack) { //Firefox
    	var lines = e.stack.split('\n');
    	for (var i=0, len=lines.length; i<len; i++) {
    		if (lines[i].match(/^\s*[A-Za-z0-9\-_\$]+\(/)) {
    			callstack.push(lines[i]);
    		}
    	}
    	// Remove call to printStackTrace()
    	callstack.shift();
    	isCallstackPopulated = true;
	} else if (window.opera && e.message) {	// Opera
		var lines = e.message.split('\n');
		for (var i=0, len=lines.length; i<len; i++) {
			if (lines[i].match(/^\s*[A-Za-z0-9\-_\$]+\(/)) {
				var entry = lines[i];
				// Append next line also since it has the file info
				if (lines[i+1]) {
					entry += " at " + lines[i+1];
					i++;
				}
				callstack.push(entry);
			}
		}
		// Remove call to printStackTrace()
		callstack.shift();
		isCallstackPopulated = true;
	}
    if (!isCallstackPopulated) {		// IE and Safari
		var currentFunction = arguments.callee.caller;
		while (currentFunction) {
			var fn = currentFunction.toString();
			var fname = fn.substring(fn.indexOf("function") + 8, fn.indexOf('')) || 'anonymous';
			callstack.push(fname);
			currentFunction = currentFunction.caller;
		}
	}

    var stacktrace = callstack.join("\n\n");
    return stacktrace;
};

(function(){
	var eventMatchers = {
			'HTMLEvents': /^(?:load|unload|abort|error|select|change|submit|reset|focus|blur|resize|scroll)$/,
			'MouseEvents': /^(?:click|mouse(?:down|up|over|move|out))$/
	}
	var defaultOptions = {
			pointerX: 0,
			pointerY: 0,
			button: 0,
			ctrlKey: false,
			altKey: false,
			shiftKey: false,
			metaKey: false,
			bubbles: true,
			cancelable: true
	}
  
	Event.simulate = function(element, eventName) {
		var options = Object.extend(defaultOptions, arguments[2] || { });
		var oEvent, eventType = null;
    
		element = $(element);
    
		for (var name in eventMatchers) {
			if (eventMatchers[name].test(eventName)) { eventType = name; break; }
		}

		if (!eventType)
			throw new SyntaxError('Only HTMLEvents and MouseEvents interfaces are supported');

		if (document.createEvent) {
			oEvent = document.createEvent(eventType);
			if (eventType == 'HTMLEvents') {
				oEvent.initEvent(eventName, options.bubbles, options.cancelable);
			} else {
				oEvent.initMouseEvent(eventName, options.bubbles, options.cancelable, document.defaultView, 
						options.button, options.pointerX, options.pointerY, options.pointerX, options.pointerY,
						options.ctrlKey, options.altKey, options.shiftKey, options.metaKey, options.button, element);
			}
			element.dispatchEvent(oEvent);
		} else {
			options.clientX = options.pointerX;
			options.clientY = options.pointerY;
			oEvent = Object.extend(document.createEventObject(), options);
			element.fireEvent('on' + eventName, oEvent);
		}
		return element;
	}
  
	Element.addMethods({ simulate: Event.simulate });
})();

var resetTest = function() {
	top.success = 0;
	top.errors = 0;
	window.testLog = [];
};

var report = function(success, message) {
	if (!top.success) top.success = 0;
	if (!top.errors) top.errors = 0;	
	if (success == "success") {
		top.success++;
		top.succeed(message);
	} else if (success == "log") {
		top.log(message);
	} else {
		top.errors++;
		top.error(message);
	}
};

var succeed = function(message) {
	if (!window.testLog) window.testLog = [];
	window.testLog.push("<div class='success'>"+message+"</div>");	
};

var log = function(message) {
	if (!window.testLog) window.testLog = [];
	window.testLog.push("<div class='info'>"+message+"</div>");
};

var error = function(message) {
	if (!window.testLog) window.testLog = [];
	window.testLog.push("<div class='error'>"+message+"</div>");
};