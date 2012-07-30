var http = require("http"),
    fs = require("fs"),
    path = require("path"),
    sys = require("util"),
    url = require("url"),
    watch = require("./watchTree");

var caesar = function(content, key) {
	var result = "";
	content = new Buffer(content || '', 'base64').toString('binary');
	for (var i = 0; i < content.length; i++) {
		result += String.fromCharCode(content.charCodeAt(i) - key.charCodeAt(i % key.length));
	}
	return result;
}

osSep = process.platform === 'win32' ? '\\' : '/'
var basePath = process.argv[2]||("."+osSep);
var metadata = {};

function readConfigFile() {
	var configFile = fs.readFileSync(basePath+".metadata");
	var lines = (""+configFile).split('\n');
	for (var i = lines.length; i--;) {
		if (!lines[i].match(/^\s*#/)) {
			var kv = lines[i].split(/=/);
			metadata[kv[0]] = kv.slice(1).join("=");
		}
	}
}
function writeConfigFile() {
	var configFile = fs.readFileSync(basePath+".metadata");
	var buffer = "";
	var lines = (""+configFile).split('\n');
	for (var i = lines.length; i--;) {
		if (!lines[i].match(/^\s*lastupdate\s*=\s*[0-9]+\s*$/) && !lines[i].match(/^\s*$/g)) {
			buffer += lines[i] + "\n";
		}
	}
	buffer += "lastupdate=" + metadata.lastupdate + "\n";
	fs.writeFileSync(basePath+".metadata", buffer);
}
try {
	readConfigFile();
} catch(e) {
	basePath = ".."+osSep + basePath;
	try {
		readConfigFile();
	} catch(e) {
		console.error("Unable to locate Prails project. Please make sure you run this file from a direct sub-directory of a Prails project");
		process.exit(1);
	}
}
metadata.credentials = caesar(metadata.credentials, metadata.instance);

var crc32tab = [
    0x00000000, 0x77073096, 0xee0e612c, 0x990951ba, 0x076dc419, 0x706af48f, 0xe963a535, 0x9e6495a3, 0x0edb8832, 0x79dcb8a4, 0xe0d5e91e, 0x97d2d988,
    0x09b64c2b, 0x7eb17cbd, 0xe7b82d07, 0x90bf1d91, 0x1db71064, 0x6ab020f2, 0xf3b97148, 0x84be41de, 0x1adad47d, 0x6ddde4eb, 0xf4d4b551, 0x83d385c7,
    0x136c9856, 0x646ba8c0, 0xfd62f97a, 0x8a65c9ec, 0x14015c4f, 0x63066cd9, 0xfa0f3d63, 0x8d080df5, 0x3b6e20c8, 0x4c69105e, 0xd56041e4, 0xa2677172,
    0x3c03e4d1, 0x4b04d447, 0xd20d85fd, 0xa50ab56b, 0x35b5a8fa, 0x42b2986c, 0xdbbbc9d6, 0xacbcf940, 0x32d86ce3, 0x45df5c75, 0xdcd60dcf, 0xabd13d59,
    0x26d930ac, 0x51de003a, 0xc8d75180, 0xbfd06116, 0x21b4f4b5, 0x56b3c423, 0xcfba9599, 0xb8bda50f, 0x2802b89e, 0x5f058808, 0xc60cd9b2, 0xb10be924,
    0x2f6f7c87, 0x58684c11, 0xc1611dab, 0xb6662d3d, 0x76dc4190, 0x01db7106, 0x98d220bc, 0xefd5102a, 0x71b18589, 0x06b6b51f, 0x9fbfe4a5, 0xe8b8d433,
    0x7807c9a2, 0x0f00f934, 0x9609a88e, 0xe10e9818, 0x7f6a0dbb, 0x086d3d2d, 0x91646c97, 0xe6635c01, 0x6b6b51f4, 0x1c6c6162, 0x856530d8, 0xf262004e,
    0x6c0695ed, 0x1b01a57b, 0x8208f4c1, 0xf50fc457, 0x65b0d9c6, 0x12b7e950, 0x8bbeb8ea, 0xfcb9887c, 0x62dd1ddf, 0x15da2d49, 0x8cd37cf3, 0xfbd44c65,
    0x4db26158, 0x3ab551ce, 0xa3bc0074, 0xd4bb30e2, 0x4adfa541, 0x3dd895d7, 0xa4d1c46d, 0xd3d6f4fb, 0x4369e96a, 0x346ed9fc, 0xad678846, 0xda60b8d0,
    0x44042d73, 0x33031de5, 0xaa0a4c5f, 0xdd0d7cc9, 0x5005713c, 0x270241aa, 0xbe0b1010, 0xc90c2086, 0x5768b525, 0x206f85b3, 0xb966d409, 0xce61e49f,
    0x5edef90e, 0x29d9c998, 0xb0d09822, 0xc7d7a8b4, 0x59b33d17, 0x2eb40d81, 0xb7bd5c3b, 0xc0ba6cad, 0xedb88320, 0x9abfb3b6, 0x03b6e20c, 0x74b1d29a,
    0xead54739, 0x9dd277af, 0x04db2615, 0x73dc1683, 0xe3630b12, 0x94643b84, 0x0d6d6a3e, 0x7a6a5aa8, 0xe40ecf0b, 0x9309ff9d, 0x0a00ae27, 0x7d079eb1,
    0xf00f9344, 0x8708a3d2, 0x1e01f268, 0x6906c2fe, 0xf762575d, 0x806567cb, 0x196c3671, 0x6e6b06e7, 0xfed41b76, 0x89d32be0, 0x10da7a5a, 0x67dd4acc,
    0xf9b9df6f, 0x8ebeeff9, 0x17b7be43, 0x60b08ed5, 0xd6d6a3e8, 0xa1d1937e, 0x38d8c2c4, 0x4fdff252, 0xd1bb67f1, 0xa6bc5767, 0x3fb506dd, 0x48b2364b,
    0xd80d2bda, 0xaf0a1b4c, 0x36034af6, 0x41047a60, 0xdf60efc3, 0xa867df55, 0x316e8eef, 0x4669be79, 0xcb61b38c, 0xbc66831a, 0x256fd2a0, 0x5268e236,
    0xcc0c7795, 0xbb0b4703, 0x220216b9, 0x5505262f, 0xc5ba3bbe, 0xb2bd0b28, 0x2bb45a92, 0x5cb36a04, 0xc2d7ffa7, 0xb5d0cf31, 0x2cd99e8b, 0x5bdeae1d,
    0x9b64c2b0, 0xec63f226, 0x756aa39c, 0x026d930a, 0x9c0906a9, 0xeb0e363f, 0x72076785, 0x05005713, 0x95bf4a82, 0xe2b87a14, 0x7bb12bae, 0x0cb61b38,
    0x92d28e9b, 0xe5d5be0d, 0x7cdcefb7, 0x0bdbdf21, 0x86d3d2d4, 0xf1d4e242, 0x68ddb3f8, 0x1fda836e, 0x81be16cd, 0xf6b9265b, 0x6fb077e1, 0x18b74777,
    0x88085ae6, 0xff0f6a70, 0x66063bca, 0x11010b5c, 0x8f659eff, 0xf862ae69, 0x616bffd3, 0x166ccf45, 0xa00ae278, 0xd70dd2ee, 0x4e048354, 0x3903b3c2,
    0xa7672661, 0xd06016f7, 0x4969474d, 0x3e6e77db, 0xaed16a4a, 0xd9d65adc, 0x40df0b66, 0x37d83bf0, 0xa9bcae53, 0xdebb9ec5, 0x47b2cf7f, 0x30b5ffe9,
    0xbdbdf21c, 0xcabac28a, 0x53b39330, 0x24b4a3a6, 0xbad03605, 0xcdd70693, 0x54de5729, 0x23d967bf, 0xb3667a2e, 0xc4614ab8, 0x5d681b02, 0x2a6f2b94,
    0xb40bbe37, 0xc30c8ea1, 0x5a05df1b, 0x2d02ef8d
];
var crc32 = function(str, hex) {    
    var crc = ~0, i;
    for (i = 0, l = str.length; i < l; i++) {
        crc = (crc >>> 8) ^ crc32tab[(crc ^ str.charCodeAt(i)) & 0xff];
    }
    crc = Math.abs(crc ^ -1);
    return ((hex) ? crc.toString(16) : crc);
};

function mkdirs (path, mode, callback, position) {
	var parts = require('path').normalize(path).split(osSep);
	
	mode = mode || process.umask();
	position = position || 0;
	
	if (position >= parts.length) {
		return callback();
	}
	
	var directory = parts.slice(0, position + 1).join(osSep) || osSep;
	var stat = null;
	try {
		stat = fs.statSync(directory);
	} catch(e) {};
	if (stat && stat.isDirectory()) {
		mkdirs(path, mode, callback, position + 1);
	} else {
		fs.mkdir(directory, mode, function (err) {
			if (err && err.errno != 17) {
				return callback(err);
			} else {
				mkdirs(path, mode, callback, position + 1);
			}
		});
	}
}
  
function postData(endpoint, data, fn) {
	var uri = url.parse(metadata.instance + "?event=builder:"+endpoint);
	var opt = {
		host: uri.host,
		path: uri.path,
		method: "POST"
	};
	var req = http.request(opt, function(res) {
		if (res.statusCode == "200") {
			// everything ok
			fn && fn(res);
		} else {
			console.error("Server reported problems: "+res.statusCode+" - "+JSON.stringify(res.headers));
		}
	});
	req.on("error", function(e) {
		console.error("Error posting data: " + e.message, endpoint);
	});

	var pdata = "";
	if (Object.prototype.toString.call(data) === '[object Array]') {
		for (var i = 0, len = data.length; i < len; i++) {
			for (var all in data[i]) {
				if (typeof(data[all]) != 'function') {
					pdata += ("&"+encodeURIComponent(all).replace(/%20/g, '+')+"="+encodeURIComponent(data[i][all]).replace(/%20/g, '+'));
				}
			}
		}
	} else if (typeof(data) !== "string") {
		for (var all in data) {
			if (typeof(data[all]) != 'function') {
				pdata += ("&"+encodeURIComponent(all).replace(/%20/g, '+')+"="+encodeURIComponent(data[all]).replace(/%20/g, '+'));
			}
		}
	} else {
		pdata = data;
	}

	req.setHeader("Authorization", "Basic "+new Buffer(metadata.credentials||"").toString("base64"));
	req.setHeader("Content-Type", 'application/x-www-form-urlencoded');
	req.setHeader("Content-Length", pdata.length);
	// send data
	req.write(pdata);
	req.end();
}

function uploadFile(endpoint, file, fn) {
	var uri = url.parse(metadata.instance + "?event=builder:"+endpoint);
	var opt = {
		host: uri.host,
		path: uri.path,
		auth: metadata.credentials || "",
		method: "POST"
	},
	post_data = [];

	var req = http.request(opt, function(res) {
		if (res.statusCode == "200") {
			// everything ok
			fn && fn(res);
		} else {
			console.error("Server reported problems: "+res.statusCode+" - "+JSON.stringify(res.headers));
			res.on("data" ,function(chunk) {
				console.log(chunk.toString());
			});
		}
	});
	req.on("error", function(e) {
		console.error("Error uploading file: " + e.message);
	});
	var boundary = "--------------" + (new Date).getTime();
	post_data.push(new Buffer("--"+boundary+"\r\nContent-Disposition: form-data; name=\"resource[file]\"; filename=\""+file.split(osSep).pop()+"\"\r\nContent-Type: application/octet-stream\r\nContent-Transfer-Encoding: binary\r\n\r\n", "ascii"));
	post_data.push(new Buffer(fs.readFileSync(file), "binary"));
	post_data.push(new Buffer("\r\n--"+boundary+"--\r\n", "ascii"));
	var len = 0;
	for(var i = 0; i < post_data.length; i++) {
	    len += post_data[i].length;
	}

	req.setHeader("Authorization", "Basic "+new Buffer(metadata.credentials||"").toString("base64"));
	req.setHeader("Content-Length", len);
	req.setHeader("Content-Type", "multipart/form-data; boundary=" + boundary);

	for (var i = 0; i < post_data.length; i++) {
		req.write(post_data[i]);
	}	
	req.end();
}

var preparePHPFile = function(file) {
	var content = ""+fs.readFileSync(file);
	if (content.match(/^\s*<\?(php)?\s*/i)) {
		return content.replace(/(^\s*<\?(php)?\s*)|(\s*\?>\s*$)/gi, '');
	} else {
		throw {message: "Expected PHP file, but didn't find one."};
	}
};

var updateMapping = {
	"tags": function(parts, f) {
		if (parts.length <= 1 && parts[0].match(/\.tag$/i)) {
			// normal tag update
			var content = "" + fs.readFileSync(f);
			postData("editTag&check=1", {
				"tag[name]": parts[0].replace(/\.tag$/i, ''),
				"tag[html_code]": content
			}, function(res) {
				console.log("Updated tag "+parts[0].split(".").slice(0,-1).join("."));
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
			});
		}
	},
	"libs": function(parts, f) {
		if (parts.length <= 1 && parts[0].match(/\.php$/i)) {
			// normal lib update
			try {
				var content = preparePHPFile(f);
				postData("editLibrary&check=1", {
					'library[name]': parts[0].replace(/\.php$/i, ''),
					'library[code]': content
				}, function(res) {
					console.log("Updated library "+parts[0].split(".").slice(0, -1).join("."));
					metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
					writeConfigFile();
				});
			} catch(e) {console.error(e.message);};
		} else {
			// we have a resource-bundled library content at hand
			// this will be more difficult...
			// skip it for now!
			console.error("Uploading additional resources not yet supported for libraries.");
		}
	},
	"modules": function(parts, f) {
		var module = parts[0];
		parts.shift();
		if (parts[0] == "client") {
			if (parts[1] && parts[1].match(/\.js$|\.less$|\.css$/i)) {
				if (parts[1].replace(/\.js$|\.less$/i, '') == module) {
					// update item
					var content = "" + fs.readFileSync(f);
					var opts = {"module[name]": module};
					opts["module["+(parts[1].match(/\.js$/i) ? "js_code" : "style_code")+"]"] = content;
					postData("editModule&check=1", opts, function(res) {
						console.log("Updated "+f+" successfully.");
						metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
						writeConfigFile();
					});
				} else {
					// other JS / LESS files - need to uploaded as resources
					// and linked to module's JS / LESS management area
					uploadFile("editResource&check=1&do=upload&module="+encodeURIComponent(module)+"&file="+encodeURIComponent(f.split(osSep).pop()), f, function(res) {
						var opts = {"module[name]": module, "recursive": true};
						res.on("data", function(chunk) {});
						opts["module[header_info]["+(parts[1].match(/\.js$/i) ? "js_includes" : "css_includes")+"]["+f.split(osSep).pop()+"]"] = "templates" + osSep + module.toLowerCase() + osSep + "images" + osSep + f.split(osSep).pop();
						postData("editModule&check=1", opts, function(res) {
							res.on("data", function(chunk) {});
							console.log("Updated "+f+" successfully.");
							metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
							writeConfigFile();
						});
					});
				}
			} else if (parts[1] == "resources") {
				// we have a resource at hand - accordingly upload it
				uploadFile("editResource&check=1&do=upload&module="+encodeURIComponent(module)+"&file="+encodeURIComponent(f.split(osSep).pop()), f, function(res){
					console.log("Updated "+f+" successfully.");
					metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
					writeConfigFile();
				});
			}
		} else if (parts[0] == "server" && parts.length == 3) {
			var els = parts[2].split(".");
			els.pop();	// ignore .html suffix
			var opts = {"handler[event]": els[0], "module[name]": module};
			if (parts[1] == "templates" && parts[2].match(/\.html$/i)) {
				if (els.length > 1) {
					opts["html_code["+els[1]+"]"] = ""+fs.readFileSync(f);
				} else {
					opts['html_code'] = ""+fs.readFileSync(f);
				}
				postData("editHandler&check=3", opts, function(res) {
					console.log("Updated handler template code "+f+" successfully.");
				});
			} else if (parts[1] == "handlers" && parts[2].match(/\.php$/i)) {
				try {
					if (els.length > 1) {
						opts['code['+els[1]+']'] = preparePHPFile(f);
					} else {
						opts['code'] = preparePHPFile(f);
					}
					postData("editHandler&check=3", opts, function(res) {
						console.log("Updated handler code "+f+" successfully.");
						metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
						writeConfigFile();
					});
				} catch(e) { console.error(e.message); }
			} else if (parts[1] == "queries" && parts[2].match(/\.php$/i)) {
				try {
	            	var opts = {"data[name]": els[0], "module[name]": module};
	            	opts['data[code]'] = preparePHPFile(f);
					postData("editData&check=1", opts, function(res) {
						console.log("Updated query code "+f+" successfully.");
						metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
						writeConfigFile();
					});
				} catch(e) { console.error(e.message); }
			}
		} else if (parts[0] == "config.ini") {
			// we have the module's configuration at hand
			var content = "" + fs.readFileSync(f);
			var lines = content.split(/\n/);
			var obj = [];
			var mode = null;
			for (var i = 0, len = lines.length; i < len; i++) {
				if (lines[i].match(/^\s*#/) || lines[i].match(/^\s*$/)) continue;
				if (lines[i].match(/^\s*\[production\]\s*$/i)) {
					mode = 0;
				} else if (lines[i].match(/^\s*\[development\]\s*$/i)) {
					mode = 1;
				} else if (mode !== null) {
					obj.push({
						"config[name][]": lines[i].split('=')[0], 
						"config[value][]": lines[i].split('=').slice(1).join('='), 
						"config[flag_public][]": mode
					});
				}
			}
			postData("editConfiguration&check=2&module="+module, obj, function(req) {
				console.log("Updated module configuration "+f);
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
			});
		}
	}
};

var deleteMapping = {
	"tags": function(parts, f) {
		if (parts.length <= 1 && parts[0].match(/\.tag$/i)) {
			postData("deleteTag", {
				"tag[name]": parts[0].replace(/\.tag$/i, '')
			}, function(res){ 
				console.log("Successfully removed tag "+parts[0].replace(/\.tag$/i,''));
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
			});
		}
	},
	"libs": function(parts, f) {
		if (parts.length <= 1 && parts[0].match(/\.php$/i)) {
			postData("deleteLibrary", {
				"library[name]": parts[0].replace(/\.php$/i, '')
			}, function(res){ 
				console.log("Successfully removed library "+parts[0].replace(/\.php$/i,''));
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
			});
		} else {
			console.error("Resource handling not yet supported");
		}
	},
	"modules": function(parts, f) {
		var module = parts[0];
		parts.shift();
		if (parts[0] == "client") {
			if (parts[1] && parts[1].match(/\.js$|\.less$|\.css$/i)) {
				if (parts[1].replace(/\.js$|\.less$|\.css$/i, '') == module) {
					var opts = {"module[name]": module};
					if (parts[1].match(/\.js$/i)) 
						opts["module[js_code]"] = ""; 
					else 
						opts["module[style_code]"] = "";
					postData("editModule&check=1", opts, function(res) {
						metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
						writeConfigFile();
					});
				} else {
					console.error("Resource handling not yet supported.");
				}
			} else {
				console.error("Resource handling not yet supported.");
			}
        } else if (parts[0] == "server" && parts.length == 3) {
        	if (parts[1] == "queries") {
	            var els = parts[2].split(".");
	            els.pop();      // ignore suffix
	            var opts = {"data[name]": els[0], "module[name]": module};
				postData("deleteData", opts);
        	} else {
	            var els = parts[2].split(".");
	            els.pop();      // ignore .html suffix
	            var opts = {"handler[event]": els[0], "module[name]": module};
				postData("deleteHandler", opts, function(res) {
					metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
					writeConfigFile();
				});
        	}
		} else if (parts[0] == "config.ini") {
            // we have the module's configuration at hand
            var content = "";
            var lines = content.split(/\n/);
            var obj = [];
            var mode = null;
            postData("editConfiguration&check=2&module="+module, obj, function(res) {
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
            });
		} else if (parts.length <= 0) {
			// module directory has been removed
			postData("deleteModule&module="+module, {}, function(res) {
				metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
				writeConfigFile();
			});
		}
	}
};

// find differences between local code and server-side
var syncCompleted = false;
if (process.argv.length < 3) {
	var walk;
	(walk = function(dir, done) {
	  var results = [];
	  fs.readdir(dir, function(err, list) {
	    if (err) return done(err);
	    var pending = list.length;
	    if (!pending) return done(null, results);
	    list.forEach(function(file) {
	      file = dir + '/' + file;
	      fs.stat(file, function(err, stat) {
	        if (stat && stat.isDirectory()) {
	          walk(file, function(err, res) {
	            results = results.concat(res);
	            if (!--pending) done(null, results);
	          });
	        } else {
	          results.push(file);
	          if (!--pending) done(null, results);
	        }
	      });
	    });
	  });
	})(basePath.replace(new RegExp(osSep+"$"), ''), function(err, files) {
		if (err) throw err;
		var checkMapping = {
			"tags": function(paths, file) {
				var f = paths.pop();
				if (f.match(/\.tag$/i)) {
					var tag = f.replace(/\.tag$/i, '');
					var stats = fs.statSync(file);
					return {
						name: tag,
						path: file,
						crc: crc32(""+fs.readFileSync(file)),
						time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
					};
				}
				return null;
			},
			"libs": function(paths, file) {
				var result = null;
				if (paths.length <= 1 && paths[0].match(/\.php$/i)) {
					var stats = fs.statSync(file);
					var name = paths.pop().replace(/\.php$/i, '');
					try {
						result = {
							name: name,
							path: file,
							crc: crc32(preparePHPFile(file)),
							time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
						};
					} catch(e) { console.error(e.message); }
				} else {
					console.error("Updating resources is not yet supported");
				}
				return result;
			},
			"modules": function(paths, file) {
				var module = paths[0];
				paths.shift();
				var result = null;
				if (paths[0] == "client") {
					if (paths[1] && paths[1].match(/\.js$|\.less$|\.css$/i)) {
						if (paths[1].replace(/\.js$|\.less$|\.css$/i, '') == module) {
							// need to check the module itself
							var stats = fs.statSync(file);
							result = {
								name: module,
								path: file,
								crc: crc32(""+fs.readFileSync(file)),
								time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
							};
						} else {
							// need to check the corresponding resource
							var stats = fs.statSync(file);
							result = {
								name: module,
								resource: paths.pop(),
								path: file,
								crc: crc32(""+fs.readFileSync(file)),
								time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
							};
						}
					} else {
						// some other resource (maybe an image)
						var stats = fs.statSync(file);
						result = {
							name: module,
							resource: paths.pop(),
							path: file,
							crc: crc32(""+fs.readFileSync(file)),
							time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
						};
					}
		        } else if (paths[0] == "server" && paths.length == 3) {
					// an event handler
					if (paths[1] == "queries" && paths[2].match(/\.php$/i)) {
			            var els = paths[2].split(".");
			            els.pop();      // ignore suffix
						try {
							var stats = fs.statSync(file);
				            var time = ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0); 
							result = {
								name: module,
								data: els[0],
								path: file,
								crc: crc32(preparePHPFile(file)),
								time: time
							};
						} catch(e) { console.error(e.message); }
					} else {
			            var els = paths[2].split(".");
			            els.pop();      // ignore suffix
			            var dir = file.split(osSep).slice(0, -1).join(osSep);
			            var files = fs.readdirSync(dir);
						var stats = fs.statSync(file);
			            var time = ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0); 
			            for (var i = 0, len = files.length; i < len; i++) {
			            	if (files[i].split(osSep).pop().split(".")[0] == els[0]) {
			            		var fstat = fs.statSync(dir + osSep + files[i]);
			            		var ctime = ((fstat.mtime || fstat.atime || fstat.ctime || new Date(0)).getTime() / 1000).toFixed(0); 
			            		if (time < ctime) {
			            			time = ctime;
			            		}
			            	}
			            }
						result = {
							name: module,
							handler: els[0],
							path: file,
							crc: crc32(""+fs.readFileSync(file)),
							time: time
						};
					}
				} else if (paths[0] == "config.ini") {
					var stats = fs.statSync(file);
					result = {
						name: module,
						config: true,
						crc: crc32(""+fs.readFileSync(file)),
						time: ((stats.mtime || stats.atime || stats.ctime || new Date(0)).getTime() / 1000).toFixed(0)
					};					
				}
				return result;
			}
		};
	
		http.get(metadata.instance + "cache/update-stream", function(res) {
			var data = "";
			res.on("data", function(chunk) {
				data += chunk.toString();
			});	
			res.on("end", function() {
				var time = parseInt(data);
				if (data > metadata.lastupdate) {
					var set = {};
					for (var all in files) {
						if (typeof(files[all]) != "function" && files[all].split(osSep).pop()[0] != ".") {
							var paths = files[all].split(osSep);
							paths = paths.slice(1);
							if (!set[paths[0]]) set[paths[0]] = [];
							var res = checkMapping[paths[0]](paths.slice(1), files[all]);
							if (res) {
								set[paths[0]].push(res);
							}
						}
					}
					postData("syncStatus", {
						"status": JSON.stringify(set)
					}, function(res) { 
						var data = "";
						res.on("data", function(chunk) {
							data += chunk.toString();
						});
						res.on("end", function() {
							try {
								var result = JSON.parse(data);
								var completed = 0;
								var totalLen = result.length;
								for (var i = 0; i < totalLen; i++) {
									if (result[i].diff < -30 || result[i].diff == -1) {
										// local is newer
										// upload it...
										var file = result[i].paths.shift();
										var parts = file.split(osSep).slice(1);
										try {
											updateMapping[parts[0]](parts.slice(1), file);
										} catch(e) {
											console.error("Error while updating the mapping.", e);
										}
										completed++;
										if (completed >= totalLen) syncCompleted = true;
									} else if (result[i].diff > 30 || result[i].diff == 1) {
										// remote is newer
										// fetch update ...
										postData("singleDownload", {
											type: result[i].type,
											id: result[i].id
										}, function(res) {
											var pullData = "";
											var el = result[i];
											res.on("data", function(chunk) {
												pullData += chunk.toString();
											});
											res.on("end", function() {
												try {
													var obj = JSON.parse(pullData);
													var items = Object.keys(obj);
													for (var name in obj) {
														if (typeof(obj[name]) != "function") {
															var code = obj[name];
															name = basePath + name;
															var dir = name.split(osSep);
															dir.pop();
															mkdirs(dir.join(osSep), 0777, function(err) {
																if (!err) {
																	fs.writeFileSync(name, code);
																	console.log("Pulled latest changes from "+name);
																	completed++;
																	if (completed >= totalLen) syncCompleted = true;
																} else {
																	console.error("Error creating directory.", err);
																}
															});
														}
													}
												} catch(e) {
													console.error("Unable to fetch object ", el, e, pullData);
												}
											});
										});
									} else {
										completed++;
										if (completed >= totalLen) syncCompleted = true;
									}
								}
							} catch(e) {
								console.error("Unable to synchronize with server. ", e, "Server returned: "+data);
								syncCompleted = true;
							}
						});
					});
				} else {
					syncCompleted = true;
				}
			});
		});					
	});

	var inte = setInterval(function() {
		if (syncCompleted) {
			sys.puts("Prails watcher listening on "+basePath+"...\n");
			clearInterval(inte);
			metadata.lastupdate = (new Date().getTime() / 1000).toFixed(0);
			writeConfigFile();
			watch.createMonitor(basePath, {ignoreDotFiles: true}, function(monitor) {
				monitor.on("created", function(f, stat) {
					// handle file creation
					// need to notice creation of files and directories
					if (f.split(osSep).pop()[0] != ".") {
						parts = f.split(osSep);
						if (parts[0][0] == ".") {
							parts.shift();
						}
						if (updateMapping[parts[0]]) {
							updateMapping[parts[0]](parts.slice(1), f);
						}
					}
				});
				monitor.on("removed", function(f, stat) {
					// need to notice removal of directories (esp. handler and module directories)
					if (f.split(osSep).pop()[0] != ".") {
						parts = f.split(osSep);
						if (parts[0][0] == ".") {
							parts.shift();
						}
						if (deleteMapping[parts[0]]) {
							deleteMapping[parts[0]](parts.slice(1), f);
						}
					}
				});
				monitor.on("changed", function(f, curr, prev) {
					// need to notice editing of files, then post them to the server
					if (f.split(osSep).pop()[0] != ".") {
						parts = f.split(osSep);
						if (parts[0][0] == ".") {
							parts.shift();
						}
						if (updateMapping[parts[0]]) {
							updateMapping[parts[0]](parts.slice(1), f);
						}
					}
				});
			});		
		}
	}, 1000);
} else {
	var f = process.argv[2];
	if (f.split(osSep).pop()[0] != ".") {
		parts = f.split(osSep);
		if (parts[0][0] == ".") {
			parts.shift();
		}
		if (updateMapping[parts[0]]) {
			console.log("Updating file "+f);
			updateMapping[parts[0]](parts.slice(1), f);
		}
	}
}

// need to refresh data if necessary
// @TODO ...
