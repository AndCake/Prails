/***********************************************************************************
 * Quixotic Worx File Upload Widget, version 1.0
 * (c) 2010 Robert Kunze, Quixotic Worx
 *
 * All rights reserved.
 *
 * 
 ***********************************************************************************
 * 
 * USAGE:
 *    <qw:file [attributes]>[click element]</qw:file>
 * 
 * Supported [attributes]: 
 *		target			server-side script, the file should be submitted to 
 *						(hint: the current file's name might be appended to the URL)
 * 		multiple		enable the user to select multiple files for upload [OPTIONAL]
 *  	progress		ID the the progress element (it's width is changed during 
 *						upload) [OPTIONAL]
 * 		onstart			event, call at the start of each file's upload process ("this" 
 *						context is the current file) [OPTIONAL]
 *		ondone			called once all files are uploaded [OPTIONAL]
 * 
 * The [click element] represents the HTML code used as the visual element 
 * for the user to click on in order to select files to be uploaded.
 *
 * 
 ***********************************************************************************
 *
 * EXAMPLES:
 * 
 * Complete example:
 *
 	<qw:file multiple="multiple" 
 		  target="http://192.168.1.20/workspace/test.php?name=" 
 		  progress="progress" 
 		  onstart="document.getElementById('currentFile').innerHTML='Uploading '+this.fileName+' ('+Math.round(this.fileSize / 1024)+'kB)...';"
 		  ondone="alert('upload done!');" 
 	>
 		<button>Click to upload</button>
 	</qw:file>
	<div id="currentFile"></div>
	<div style="position:relative;width:200px;height:10px;border:1px solid #ccc;background-color:#fff;display:none;">
		<div id="progress" style="position:absolute;left:0px;top:0px;height:100%;width:0px;background-color:#cf9;border-right:1px solid #ccc;"></div>
	</div>
 *
 *	
 * Minimal example:
 *
	<qw:file target="http://www.example.org/upload_receiver.php?name=">upload</qw:file>
 * 
 *
 ***********************************************************************************
 * 
 * NOTE:
 * In order to upload files to other hosts, the server-side script needs to set some 
 * response headers. These are in detail:
 * 
 * Origin: <page's-base-url>
 * Access-Control-Allow-Origin: <page's-base-url>
 * Access-Control-Max-Age: 3628800
 * Access-Control-Allow-Methods: POST 
 *
 * Here the page's base url is the one of the page, this widget script runs in.
 * 
 * Furthermore for best compatibility to all browsers, the page containing the 
 * widget should add the correct prefix as namespace like:
 *
 * <html xmlns:qw="http://www.quixotic-worx.de/2010/qwf">
 *
 *
 ***********************************************************************************/

var QuixoticWorxUpload = {
	
		/**
		 * Upload Mechanism for new browsers (which support the File API)
		 * currently works in: Firefox 3.5.x - 3.6.x, Safari 4, Chromium 4
		 * does not work in: Chromium 5 (MacOS X beta - files are not transmitted)
		 */
		upload: function(options) {
			var file = options.fileList[0];

			if (typeof(options.onStart) == "function") {
				options.onStart(file);
			}
			var xhr = new XMLHttpRequest();
			
			if (options.progress != null) {
				options.progress.style.width = "0%";
				xhr.upload.addEventListener("progress", prog = function(ev) {
					var total = ev.total;
					if (total <= 0 || total > file.fileSize && file.fileSize > 0) { total = file.fileSize; }
					if (total <= 0 || total > file.size && file.size > 0) { total = file.size; }
					var percent = ev.loaded / total;
					if (percent > 1) percent = 1;
					options.progress.style.width = (percent * 100.0) + "%";
				}, false);
				xhr.addEventListener("progress", prog, false);				
			}
			// register for the upload finished event
			xhr.addEventListener("load", function(event) {
				// if existent, set the progress to 100%
				if (options.progress != null) {
					options.progress.style.width = "100%";
				}
				// if more than one file is left
				if (options.fileList.length > 1) {
					files = [];
					// strip the first one
					for (var i = 1; i < options.fileList.length; i++) {
						files.push(options.fileList[i]);
					}
					// continue with uploading the next file
					QuixoticWorxUpload.upload({fileList: files, progress: options.progress, onStart: options.onStart, onDone: options.onDone, target: options.target});
				} else if (typeof(options.onDone) == "function") {
					// all files have been uploaded => we're done
					options.onDone();
				}
			}, false);
			
			// send the actual file content to the target address
			xhr.open("POST", options.target+file.fileName, true);
			if (typeof(xhr.sendAsBinary) == "function" && typeof(file.getAsBinary) == "function") {
				xhr.sendAsBinary(file.getAsBinary());	
			} else {
				xhr.send(file);
			}
		},

		/**
		 * Upload Mechanism for older browsers (which are not supporting File API)
		 * should work in all browsers (including IE and Opera)
		 */
		addUpload: function(wrapper, name, options) {
			// create an iFrame to hold the upload form
			var iframe = '<iframe frameborder="0" scrolling="no" width="'+options.width+'" height="'+options.height+'" name="'+name+'" style="border:0px solid #fff;margin:0px;padding:0px;"></iframe>';
			var tmpParent = document.createElement("div");
			tmpParent.innerHTML = iframe;
			iframe = tmpParent.firstChild;
			wrapper.appendChild(iframe);
			
			var mine = this;
			// wait for a very short time in order to let the browser put the empty document into the iframe doc
			setTimeout(function() {
				// IE doesn't know about contentDocument, just about contentWindow
				var doc = iframe.contentWindow || iframe.contentDocument;
				if (doc.document) doc = doc.document;
		        var win = iframe.contentWindow || iframe;
		        if (doc != null) {
		        	// copy the stylesheets from the parent document to the iframe
		        	styles = "";
		        	try {
			        	var links = document.getElementsByTagName("link");
			        	for (var i = 0, len = links.length; i < len; i++) {
			        		if (links[i].rel && links[i].rel == "stylesheet") {
			        			styles += '<link rel="stylesheet" href="'+links[i].href+'" type="text/css" />';
			        		}
			        	}
		        	} catch(e){}; 
		        	// build the document's content
		        	var content = '<html><head><style type="text/css">\nbody{margin:0px !important;padding:0px !important;}\n</style>'+styles+
		        				  '</head><body><form method="post" action="'+options.target+'" enctype="multipart/form-data">'+
  	        					  QuixoticWorxUpload.template.replace("[content]", options.content).replace("width:100%;height:100%;", "width:"+options.width+"px;height:"+options.height+"px;").replace("[event]", 'onchange="upload(self, this);"')+'</form></body></html>';
  	        		// and write it into the document
					doc.write(content);
					
					// definition of the upload function, to be called when the file upload value has changed
					win.upload = function(obj, me) {
		            	var par = obj.parent.document;	
		            	var progress = par.getElementById(options.progress);
		            	if (progress) {
			            	progress.style.width = "0%";
		            	}
		            	
		            	// first notify onStart
						if (options.onStart) {
							var s = options.onStart;
							(function() {
								eval(s);
							}).apply({fileName: me.value.substr(me.value.lastIndexOf("/")+1).substr(me.value.lastIndexOf("\\")+1), fileSize: "0"});
						}
		
		               	// hide old iframe
						var iframes = par.getElementsByName(name);
						var frame = iframes[iframes.length - 1] || iframe; 
						frame.style.visibility = "hidden";
						frame.style.width = "0px";
						frame.style.height = "0px";
						
						// add new iframe + initialize it
						mine.addUpload(wrapper, name, options);
						
						// continously check if the file upload has been completed
						var onDone = options.onDone;
						var current = 0;
						var testInt = setInterval(function() {
							if (progress) {
								progress.style.width = (current % 10) * 10 + "%";
							}
							var finished = false;
							try {
								finished = (obj.document.getElementsByTagName("form").length <= 0);
							} catch (e) {
								finished = true;
							}
							if (finished) {
								// file received on server => upload done
								if (progress) {
									progress.style.width = "100%";
								}
								clearInterval(testInt);
								if (onDone) {
									eval(onDone);
								}
							}
							current++;
						}, 500);
						
						// send file
						setTimeout(function() { obj.document.getElementsByTagName("form")[0].submit(); }, 100);
					};
		        }
			}, 100);			
		},
		
		// upload button HTML template
		template: '<div style="clear:both;"></div><div style="float:left;position:relative;cursor:pointer;overflow:hidden;">'+
				  '<input type="file" name="file" [multi] id="fopen[id]" style="position:absolute;z-index:2;opacity:0;filter'+
				  ':alpha(opacity=0);width:100%;height:100%;border:0px solid #f00;zoom:1;cursor:pointer;" [event] /><span style="po'+
				  'sition:relative;z-index:1;cursor:pointer;display:block;" class="__qw_content_container">[content]</span></div><div style="clear:both;"><'+
				  '/div>',
		
		initSingleUpload: function(file, i) {
			file.style.display = "inline-block";
			var code = QuixoticWorxUpload.template.replace("[id]", i).
												   replace("[content]", file.innerHTML).
												   replace("[multi]", (file.getAttribute("multiple") != null ? "multiple=''" : "")).
												   replace("[event]", "");
			var progress = file.getAttribute("progress");
			if (progress != null && progress.length > 0) progress = document.getElementById(progress);
			file.innerHTML = code;
			var target = file.getAttribute("target");
			var fu = file;
			var me = this;
			document.getElementById("fopen"+i).addEventListener("change", function() {
				var options = {
					fileList: this.files,
					progress: progress,
					target: target
				};
				if (fu.getAttribute("ondone")) {
					var ondone = fu.getAttribute("ondone");
					options["onDone"] = function() {
						eval(ondone);
					};
				}
				if (fu.getAttribute("onstart")) { 
					var onstart = fu.getAttribute("onstart");
					options["onStart"] = function(file) {
						var s = onstart;
						(function() {
							eval(s);
						}).apply(file);
					}
				}
				me.upload(options);
			}, false);
		},
				  
		/**
		 * Initializes the file upload mechanism, therefore it first determines
		 * whether the browser supports the File API or not
		 */
		init: function() {
			var input = document.createElement("input");
			input.type = "file";
			// make sure, the current browser supports the File API
			if (typeof(input.files) != "undefined" && typeof(File) != "undefined") {
				var fileUploads = document.getElementsByTagName("file");
				if (fileUploads.length <= 0) fileUploads = document.getElementsByTagName("qw:file");
				var len = fileUploads.length;
				for (var i = 0; i < len; i++) {
					var file = fileUploads[i];
					if (file.transformed) continue;
					file.transformed = true;
					var qwu = new QuixoticWorxUploadClass();
					qwu.initSingleUpload(file, i);
				}
			} else {		
				// else initialize it for old browsers...
				var fileUploads = document.getElementsByTagName("file");
				if (fileUploads.length <= 0) fileUploads = document.getElementsByTagName("qw:file");
				var len = fileUploads.length;
				for (var i = 0; i < len; i++) {
					var file = fileUploads[i];
					if (file.transformed) continue;
					file.style.width = "auto";
					file.style.height = "auto";
					file.style.border = "0px solid #fff";	
					file.style.display = "inline-block";
					try {
						file.style.zoom = "1";
					} catch (e){};
					file.transformed = true;
					var fc = file.cloneNode(true);
					fc.style.position = "absolute";
					fc.style.left = "-1000000px";
					document.body.appendChild(fc);
					var content = fileUploads[i].innerHTML;
					var target = file.getAttribute("target");
					var width = (fc.clientWidth || file.clientWidth)+"";
					var height = (fc.clientHeight || file.clientHeight)+"";
					var progress = file.getAttribute("progress");
					fc.parentNode.removeChild(fc);
					file.innerHTML = "";
					qwu = new QuixoticWorxUploadClass();
					qwu.addUpload(file, "fileopenframe"+i, {
						width: width, 
						height: height,
						target: target,
						progress: progress, 
						content: content,
						onDone: file.getAttribute("ondone"), 
						onStart: file.getAttribute("onstart")
					});
				}
			}
		}
};

QuixoticWorxUploadClass = function(){};
QuixoticWorxUploadClass.prototype.addUpload = QuixoticWorxUpload.addUpload;
QuixoticWorxUploadClass.prototype.upload = QuixoticWorxUpload.upload;
QuixoticWorxUploadClass.prototype.initSingleUpload = QuixoticWorxUpload.initSingleUpload;

//QuixoticWorxUpload.init();
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", function() {
		QuixoticWorxUpload.loaded = true;
		QuixoticWorxUpload.init();
	}, false);
} else {
	window.onload = function() {
		QuixoticWorxUpload.loaded = true;
		QuixoticWorxUpload.init();
	};
}