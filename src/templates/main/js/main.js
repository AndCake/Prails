/** Section JavaScript
 *  In pages you create using Prails you always have access to the complete functionality provided by !(http://prototypejs.org PrototypeJS), !(http://scripty2.com/ Scripty 2), !(http://jquery.com/ jQuery) and !(http://jqueryui.com/ jQuery UI), which makes it easy to deliver a great user experience. 
 * *Please note*, that due to the presence of both - Prototype and jQuery - you need to access jQuery functionality not via `$` but via `_` (underscore character) or directly via `jQuery`. 
 * 
 * *Example:*
 * {{{
 * <div id="myid"></div>
 * <p style="display: none;">And this will appear</p>
 * <script type="text/javascript">
 * // using jQuery's on-document-ready event registration
 * _(function() {
 *     // using prototype to add some CSS class and insert text
 *     $("myid").addClassName("myclass").update("I read this message");
 *     // using jQuery to show the
 *     _(".myclass + p").addClass("ohmy").show('slow');
 * });
 * </script>
 * }}}
 * This example demonstrates ways in which PrototypeJS and jQuery can be intertwined.
 *
 * This section covers some pre-defined functions inherent in Prails. 
 **/
var baseHref = document.getElementsByTagName("base")[0].getAttribute("href");

/**
 * include(file) -> void
 * - `file` (String) - URL of the javascript file to be loaded
 *
 * This method will load the given javascript file immediately.
 **/
function include(file) {
	if (file.indexOf("http:\/\/") < 0 || file.indexOf("https:\/\/") < 0) {
		file = baseHref + file;
	}
	var head = document.getElementsByTagName("head")[0];
	var script = document.createElement("script");
	script.setAttribute("type", "text/javascript");
	script.setAttribute("src", file);
	head.appendChild(script);
}

/**
 * loadURL(url) -> void
 * - `url` (String) - the URL to load
 *
 * This function loads a given URL by changing the current window's URL.
 **/
function loadURL(url) {
	if (url.indexOf("http://") < 0 && url.indexOf("https://") < 0) {
		url = baseHref + url;
	}

	location.href = url;
}

/**
 * invoke(element, event[, parameters[, post[, onSuccess]]]) -> void
 * invoke(event[, parameters[, post[, onSuccess[, showIndicator]]]]) -> void
 * invoke(event[, onSuccess]) -> void
 * invoke(event[, post]) -> void
 * - `element` (String|DOMElement) - the element the resulting response body should be inserted into (contents are replaced!)
 * - `event` (String) - either a URL (relative or absolute) or a event in colon notation which should be requested
 * - `parameters` (Object|String) - additional parameters to be sent (or POST body, if `post` is set to true)
 * - `post` (Boolean) - what kind of request to perform: a GET request (`false`) or a POST request (`true`), defaults to `false`
 * - `onSuccess` (Function) - the callback function to call when the response is received. In case an element was specified, the callback function is called after the contents have been added to the document.
 * 
 * This method executes an AJAX request.
 *
 * *Example:*
 * {{{
 * <div id="response"></div>
 * <script type="text/javascript">
 *    _(function() {
 *        invoke('response', 'global:home');
 *    });
 * </script>
 * }}}
 * This example sends an AJAX request to the event `global:home` and writes it's response into the `response` element.
 *
 * *Example 2:*
 * {{{
 * invoke("User/search", $("myform").serialize(), true, function(req) {
 *    alert(req.responseText);
 * });
 * }}}
 * This example sends an AJAX POST request to the relative URL User/search, while submitting data from the form `myform`. The server's response is shown in an alert window.
 **/
function invoke(element, event, parameters, post, onSuccess, showIndicator) {
	var params = "";
	if (typeof(parameters) === "function") {
		onSuccess = parameters;
		parameters = "";
		post = false;
	}
	if (!post || post == null) {
		params = "&" + $H(parameters).toQueryString();
	}
	if ($(element) == null && (event == null || typeof(event) != "string")) {
		switch (typeof(event)) {
			case "function":
				onSuccess = event;
				break;
			case "object":
				showIndicator = onSuccess;
				onSuccess = post;
				post = parameters;
				parameters = event;
				break;
			case "boolean":
				post = event;
				break;
		}
		event = element;
		element = null;
	}
	if (event.indexOf("/") < 0 && event.indexOf("event=") < 0) {
		event = baseHref+"?event="+event;
	}  else if (event.indexOf("http://") < 0 && event.indexOf("https://") < 0) {
		event = baseHref + event;
	}
	if (element != null) {
		if (showIndicator !== false) {
			// show loading indicator...
			var div = document.createElement("DIV");
			div.className = "loading-indicator";
			div.appendChild(document.createTextNode(" please wait..."));
			$(element).insertBefore(div, $(element).firstChild);
		}

		new Ajax.Updater(element, event, {
			parameters : parameters,
			evalScripts : true,
			method : (post ? 'post' : 'get'),
			onSuccess : function(req) {
				// eval script tags without content, but src
				var match = req.responseText.match(/<script\s+([^>]*)>([^<]|<[^\/]|<\/[^s]|<\/s[^c]|<\/sc[^r]|<\/scr[^i]|<\/scri[^p]|<\/scrip[^t]|<\/script[^>])*<\/script>/gi);
				if (match) {
					setTimeout(function() {
						for (var i = 0; i < match.length; i++) {
							if (match[i].search(/\s+src=/gi) >= 0) {
								var span = new Element("span");
								span.innerHTML = match[i];
								var s = new Element("script");
								s.type = "text/javascript";
								s.src = span.down("script").getAttribute("src");
								s.innerHTML = span.down("script").innerHTML;
								document.getElementsByTagName("head")[0].appendChild(s);
							}
						}
					}, 100);
				}
				setTimeout(function() {
					document.fire("dom:loaded");	
				}, 10);				
				onSuccess(req);
			}	
		});
	} else {
		new Ajax.Request(event, {
			parameters : parameters,
			method : (post ? 'post' : 'get'),
			onSuccess : onSuccess
		});
	}
}

function initAjaxLinks() {
	$$("a[rel], a.ajax, a.modal, a.dialog").each(function(item) {
		if ((item.hasClassName("modal") || item.hasClassName("dialog")) && !item._ajaxified) {
			var params = {buttons: false};
			item._ajaxified = true;
			if (item.rel) {
				var paramList = item.rel.split("|");
				$A(paramList).each(function(p) {
					var parts = p.split("=");
					params[parts[0]] = parts[1];
				});
			}
			if (item.href.indexOf(baseHref) < 0) {
				params["iframe"] = true;
			}
			if (item.getAttribute("title")) params["title"] = item.getAttribute("title");
			item.observe("click", function(event) {
				event.stop();
				if (this.disabled || this.getAttribute("disabled")) {
					return false;
				}
				if (this.getAttribute("href").indexOf('#') >= 0) {
					var el = $$(this.getAttribute("href").replace(location.href.replace(/#(.*)$/gi, ''), ''))[0];
					if (el) {
						window.currentDialog = new S2.UI.Dialog(el.cloneNode(true), params).open();
						window.currentDialog.element.observe("ui:dialog:after:close", function(obj) {
							window.currentDialog.element.remove();
						});
						var me = this;
                        setTimeout(function() {
                                document.fire("dom:loaded");
                                var loaded = me.onload || me.getAttribute("onload");
                                try { typeof(loaded) == 'string' && eval(loaded) || typeof(loaded) == 'function' && loaded.call(it); } catch(e){};
                        }, 10);
					}
				} else {
					var suffix = this.getAttribute("href").split(".").pop();
					if (["png", "jpg", "jpeg", "tiff"].indexOf(suffix.toLowerCase()) >= 0) {
						params["content"] = "<img src='"+this.getAttribute("href")+"' alt='' border='0'/>";
						window.currentDialog = new S2.UI.Dialog(params).open();
						window.currentDialog.element.observe("ui:dialog:after:close", function(obj) {
							window.currentDialog.element.remove();
						});
						var me = this;
						setTimeout(function() {
							document.fire("dom:loaded");
							var loaded = me.onload || me.getAttribute("onload");
							try { typeof(loaded) == 'string' && eval(loaded) || typeof(loaded) == 'function' && loaded.call(it); } catch(e){};
						}, 10);
					} else {
						var me = this;
						invoke(null, this.getAttribute("href"), null, false, function(req) {
							params["content"] = req.responseText;
							window.currentDialog = new S2.UI.Dialog(params).open();
							window.currentDialog.element.observe("ui:dialog:after:close", function(obj) {
								window.currentDialog.element.remove();
							});
							setTimeout(function() {
								document.fire("dom:loaded");	
								var loaded = me.onload || me.getAttribute("onload");
								try { typeof(loaded) == 'string' && eval(loaded) || typeof(loaded) == 'function' && loaded.call(it); } catch(e){};
							}, 10);
						});
					}
				}
			});
		} else if ($(item.rel) != null && !item._ajaxified) {
			item._ajaxified = true;
			item.observe("click", function(event) {
				event.stop();
				if (this.disabled || this.getAttribute("disabled")) {
					return false;
				}
				var me = this;
				invoke(this.rel, this.href, null, false, function(req) {
					var it = this;
					setTimeout(function() {
						document.fire("dom:loaded");
						var loaded = me.onload || me.getAttribute("onload");
						try { typeof(loaded) == 'string' && eval(loaded) || typeof(loaded) == 'function' && loaded.call(it); } catch(e){window.console && console.log(e.message);};
					}, 10);
				});
			});
		} else if (!item._ajaxified) {
			item._ajaxified = true;
			var callback = function(req) {
				if (this.onload || this.getAttribute("onload")) {
					var loaded = this.onload || this.getAttribute("onload");
					try { typeof(loaded) == 'string' && eval(loaded) || typeof(loaded) == 'function' && loaded.call(this); } catch(e){window.console && console.log(e.message);};
				}
			};
			if (item.href.indexOf("#") >= 0) {
				var el = $(item.href.replace(/^([^#]*#)/gi, ''));
				if (el) {
					item.observe("click", function(event) {
						event.stop();
						if (this.disabled || this.getAttribute("disabled")) {
							return false;
						}
						invoke(el, this.href, callback.bind(this));
					});
				}

			}
			item.observe("click", function(event) {
				event.stop();
				if (this.disabled || this.getAttribute("disabled")) {
					return false;
				}
				invoke(this.href, callback.bind(this));
			});
		}
	});
}

function initWysiwyg() {
	$$(".wysiwyg").each(function(item) {
		if (item._wysiwygtified) return true;
		item._wysiwygtified = true;
		if (!item.id) item.id = "wysiwyg_"+(new Date().getTime());
		var params = {
			theme : "advanced",
			mode : "exact",
			elements: item.id,
		    theme_advanced_toolbar_location : "top",
	        theme_advanced_toolbar_align : "left",		    
		    skin : "o2k7",
		    use_native_selects: true,
	        skin_variant : "silver",
	        readonly: ((item.disabled || item.getAttribute("disabled")) && 1) || 0,
	        plugins: "style,table,advimage,inlinepopups,searchreplace,contextmenu,paste,media,fullscreen,advlist",
	        theme_advanced_buttons1: "fullscreen,|,cut,copy,paste,pastetext,pasteword,|,search,replace,|,bold,italic,underline,|,justifyleft,justifycenter,justifyright,justifyfull,|,formatselect,fontselect,fontsizeselect",
	        theme_advanced_buttons2: "bullist,numlist,|,outdent,indent,|,undo,redo,|,link,unlink,media,image,styleprops,code,|,forecolor,backcolor,|,tablecontrols",
	        theme_advanced_buttons3: ""
		};
		if (item.getAttribute("rel")) {
			var paramList = item.getAttribute("rel").split("|");
			$A(paramList).each(function(p) {
				var parts = p.split("=");
				if (parts[0] == "buttonList") {
					params[parts[0]] = parts.slice(1).join("=").split(",");
				} else {
					try {
						if (!params[parts[0]]) params[parts[0]] = ""; 
						params[parts[0]] += parts.slice(1).join("=");
					} catch(e) {
						params[parts[0]] = parts.slice(1).join("=");
					}
				}
			});
		}
		if (item.onsave || item.getAttribute("onsave")) params["onSave"] = function(content, id, instance) { eval(item.onsave || item.getAttribute("onsave")); };
		tinyMCE.init(params);
		setTimeout(function() {
			item._editor = tinyMCE.get(item.id);
		}, 0);
	});
}

/**
 * addLoadEvent(callback) -> void
 * `callback` (Function) - the callback function to call. Notice, that it will also be called for AJAX requests via `invoke`
 * 
 * Registers a function to be called as soon as the document was loaded completely. When `invoke` is used to load additional contents into the page, the callback functions registered through `addLoadEvent` will be called again. If you don't want that to happen, use the jQuery-style onload registration.
 *
 * *Example 1:*
 * {{{
 * addLoadEvent(function() {
 *     $$("form").each(function(item) {
 *         if (item._eventAttached) return;
 *         item._eventAttached = true;
 *         item.observe("submit", function(event) {
 *             alert("tried to submit a form");
 *             event.stop();
 *         });
 *     });
 * });    
 * }}}
 * This example will prevent all forms on the page to be submitted, if not triggered via javascript. 
 **/
function addLoadEvent(func) {
	document.observe('dom:loaded', func);
}

/**
 * crc32(data) -> Number
 * `data` (String) - the data to compute the checksum for
 * 
 * This function computes the CRC32 checksum for a given string and returns the checksum.
 **/
function crc32(str) {
	var table = "00000000 77073096 EE0E612C 990951BA 076DC419 706AF48F E963A535 9E6495A3 0EDB8832 79DCB8A4 E0D5E91E 97D2D988 09B64C2B 7EB17CBD E7B82D07 90BF1D91 1DB71064 6AB020F2 F3B97148 84BE41DE 1ADAD47D 6DDDE4EB F4D4B551 83D385C7 136C9856 646BA8C0 FD62F97A 8A65C9EC 14015C4F 63066CD9 FA0F3D63 8D080DF5 3B6E20C8 4C69105E D56041E4 A2677172 3C03E4D1 4B04D447 D20D85FD A50AB56B 35B5A8FA 42B2986C DBBBC9D6 ACBCF940 32D86CE3 45DF5C75 DCD60DCF ABD13D59 26D930AC 51DE003A C8D75180 BFD06116 21B4F4B5 56B3C423 CFBA9599 B8BDA50F 2802B89E 5F058808 C60CD9B2 B10BE924 2F6F7C87 58684C11 C1611DAB B6662D3D 76DC4190 01DB7106 98D220BC EFD5102A 71B18589 06B6B51F 9FBFE4A5 E8B8D433 7807C9A2 0F00F934 9609A88E E10E9818 7F6A0DBB 086D3D2D 91646C97 E6635C01 6B6B51F4 1C6C6162 856530D8 F262004E 6C0695ED 1B01A57B 8208F4C1 F50FC457 65B0D9C6 12B7E950 8BBEB8EA FCB9887C 62DD1DDF 15DA2D49 8CD37CF3 FBD44C65 4DB26158 3AB551CE A3BC0074 D4BB30E2 4ADFA541 3DD895D7 A4D1C46D D3D6F4FB 4369E96A 346ED9FC AD678846 DA60B8D0 44042D73 33031DE5 AA0A4C5F DD0D7CC9 5005713C 270241AA BE0B1010 C90C2086 5768B525 206F85B3 B966D409 CE61E49F 5EDEF90E 29D9C998 B0D09822 C7D7A8B4 59B33D17 2EB40D81 B7BD5C3B C0BA6CAD EDB88320 9ABFB3B6 03B6E20C 74B1D29A EAD54739 9DD277AF 04DB2615 73DC1683 E3630B12 94643B84 0D6D6A3E 7A6A5AA8 E40ECF0B 9309FF9D 0A00AE27 7D079EB1 F00F9344 8708A3D2 1E01F268 6906C2FE F762575D 806567CB 196C3671 6E6B06E7 FED41B76 89D32BE0 10DA7A5A 67DD4ACC F9B9DF6F 8EBEEFF9 17B7BE43 60B08ED5 D6D6A3E8 A1D1937E 38D8C2C4 4FDFF252 D1BB67F1 A6BC5767 3FB506DD 48B2364B D80D2BDA AF0A1B4C 36034AF6 41047A60 DF60EFC3 A867DF55 316E8EEF 4669BE79 CB61B38C BC66831A 256FD2A0 5268E236 CC0C7795 BB0B4703 220216B9 5505262F C5BA3BBE B2BD0B28 2BB45A92 5CB36A04 C2D7FFA7 B5D0CF31 2CD99E8B 5BDEAE1D 9B64C2B0 EC63F226 756AA39C 026D930A 9C0906A9 EB0E363F 72076785 05005713 95BF4A82 E2B87A14 7BB12BAE 0CB61B38 92D28E9B E5D5BE0D 7CDCEFB7 0BDBDF21 86D3D2D4 F1D4E242 68DDB3F8 1FDA836E 81BE16CD F6B9265B 6FB077E1 18B74777 88085AE6 FF0F6A70 66063BCA 11010B5C 8F659EFF F862AE69 616BFFD3 166CCF45 A00AE278 D70DD2EE 4E048354 3903B3C2 A7672661 D06016F7 4969474D 3E6E77DB AED16A4A D9D65ADC 40DF0B66 37D83BF0 A9BCAE53 DEBB9EC5 47B2CF7F 30B5FFE9 BDBDF21C CABAC28A 53B39330 24B4A3A6 BAD03605 CDD70693 54DE5729 23D967BF B3667A2E C4614AB8 5D681B02 2A6F2B94 B40BBE37 C30C8EA1 5A05DF1B 2D02EF8D";

	var crc = 0;
	var x = 0;
	var y = 0;

	crc = crc ^ (-1);
	for ( var i = 0, iTop = str.length; i < iTop; i++) {
		y = (crc ^ str.charCodeAt(i)) & 0xFF;
		x = "0x" + table.substr(y * 9, 8);
		crc = (crc >>> 8) ^ x;
	}
	return crc ^ (-1);
}

(function() {
  if (navigator.userAgent.indexOf("Gecko/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " firefox";
  if (navigator.userAgent.indexOf("Presto/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " opera";
  if (navigator.userAgent.indexOf(" MSIE") > 0) {
    document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " msie";
    version=Math.floor(parseFloat(/\s+MSIE\s*([0-9.]+);/.exec(navigator.userAgent)[1]));
    document.getElementsByTagName("html")[0].className += " msie"+version;
  }

  if (navigator.userAgent.indexOf("AppleWebKit/") > 0) document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " webkit";
  if (navigator.userAgent.indexOf("Chrome/") > 0) 
    document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " chrome";
  else if (navigator.userAgent.indexOf("Safari/") > 0) 
    document.getElementsByTagName("html")[0].className = document.getElementsByTagName("html")[0].className + " safari"; 
})();

addLoadEvent(initAjaxLinks);
addLoadEvent(initWysiwyg);
addLoadEvent(function() {
	if (!window.debugRefresher && window.devel && document.body.className.indexOf("get-request") >= 0) {
		window.debugRefresher = function() {
			if (localStorage && localStorage.getItem("__prails_newData")) {
				$$("input, textarea, select").each(function(item) {
					var value;
					if (value = localStorage.getItem("__prails_input+"+item.name)) {
						if (item.tagName.toLowerCase() == 'input' && (item.type == 'radio' || item.type == 'checkbox')) 
							item.checked = (value == "true");
						else
							item.value = value;
						localStorage.removeItem("__prails_input+"+item.name);
					}
				});
				localStorage.removeItem("__prails_newData");
			}
			setTimeout(function() {
				_.getJSON("cache/update-stream?"+(new Date().getTime()), function(data) {
					var all, i;
					for (all in data) 
						if (typeof data[all] != 'function')
							for (i in data[all]) 
								if (typeof data[all][i] != 'function' && data[all][i].time)
									if (data[all][i].time > window.debugRefresher.now) {
										$$("input, textarea, select").each(function(item) {
											var value = "";
											if (item.value.length > 0) {
												if (item.tagName.toLowerCase() == 'input' && (item.type == 'radio' || item.type == 'checkbox')) 
													value = item.checked.toString();
												else 
													value = item.value;
												localStorage && localStorage.setItem("__prails_input+"+item.name, value);
											}
										});
										localStorage && localStorage.setItem("__prails_newData", true);
										location.reload();
									}
								
					window.debugRefresher();
				});
			}, 500);
		};
		window.debugRefresher.now = (new Date().getTime() / 1000).toFixed();
		window.debugRefresher();
	}
});
