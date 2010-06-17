/**
 * replaces a <fileselector> tag with an (image) upload area
 * takes two attributes:
 * 	name: name of the file upload
 *  value: current value of the file upload
 *  class: either "url" or "upload"
 *  form: ID of the form element [optional]; if none is given, the first form element of the document is used
 * It's body should contain a preview for the current file / uploaded file
 * 
 * When uploading a file, it's posted to <Form Action URL>&do=upload . The server's response should consist of 3 lines:
 * 	<preview html code>
 *  OK
 *  <file path / name> 
 */
var FileSelector = {
    find: function() {
        var sels = document.getElementsByTagName("FileSelector");
        var fs = Array();
        for (var i = 0; i < sels.length; i++) {
            fs[i] = sels[i];
            try {
            if (document.all && !window.opera) {        // IE workaround (if a tag is unknown, IE splits <tag>[content]</tag> into 3(!!) tags called "<tag>", "[content]", "</tag>")
               var pos = document.body.innerHTML.indexOf(sels[i].outerHTML);
               var pos2 = document.body.innerHTML.indexOf("</FILESELECTOR>", pos);
               var tag = document.body.innerHTML.substring(pos, pos2 + "</FILESELECTOR>".length);
               fs[i] = document.createElement("DIV");
               fs[i].setAttribute("name", sels[i].getAttribute("name"));
               fs[i].setAttribute("value", sels[i].getAttribute("value"));
               fs[i].setAttribute("form", sels[i].getAttribute("form"));
               fs[i].className = sels[i].className;
               fs[i].innerHTML = tag.replace(sels[i].outerHTML, "").replace("</FILESELECTOR>", "");
               sels[i].parentNode.appendChild(fs);
               if (sels[i].nextSibling.nextSibling.tagName == "/FILESELECTOR") {
                  sels[i].parentNode.removeChild(sels[i].nextSibling.nextSibling);
               }
               sels[i].parentNode.removeChild(sels[i].nextSibling);
               sels[i].parentNode.removeChild(sels[i]);
            }
            setTimeout(function(fs, i){FileSelector.transform(fs, i);}, 100, fs[i], i);
            } catch (e) { }
        }
    },

    transform: function(obj, id) {
        var content = "";
        var url = false;
        var upload = false;
        var name = obj.getAttribute("name");
        var form = document.getElementById(obj.getAttribute("form")) || document.getElementsByTagName("FORM")[0];
        var classes = obj.className.split(/ /g);
        content += "<div id='selectedFile"+id+"' style='cursor:pointer;' title='click to change'>"+obj.innerHTML+"</div>";
        content += "<div style='display:none;'>";
        for (var i = 0; i < classes.length; i++) {
            switch (classes[i]) {
                case "url":
                    var value = "";
                    if (obj.getAttribute("value").indexOf(location.host) < 0 && obj.getAttribute("value").indexOf("http://") >= 0) {
                        value = obj.getAttribute("value");
                    }
                    content += "<input type='text' name='"+name+"_"+id+"' value='"+value+"' size='40' onchange='document.getElementById(\"selectedFilePath"+id+"\").value=this.value;'><br/>";
                    url = true;
                    break;
                case "upload":
                    content += "<iframe name='uploadWindow"+id+"' id='uploadWindow"+id+"' width='400' height='40' frameborder='0' scrolling='no'></iframe>";
                    upload = true;
                    break;
            }
        }
        content += "<input type='hidden' name='"+name+"' value='"+obj.getAttribute("value")+"' id='selectedFilePath"+id+"' />";
        content += "</div>";

        var div = document.createElement("DIV");
        div.innerHTML = content;
        div.form = form;
        
        obj.parentNode.replaceChild(div, obj);

        div.getElementsByTagName("DIV")[0].onclick = function() {
            var divs = this.parentNode.getElementsByTagName("DIV");
            if (divs[divs.length - 1].style.display == "none") {
                divs[divs.length - 1].style.display = "";
            } else {
                divs[divs.length - 1].style.display = "none";
            }
        };
        if (upload) setTimeout(function() {FileSelector.addUpload(div, name, id)}, 100);
    },

    addUpload: function(obj, name, id) {
        var lastIFrame = obj.getElementsByTagName("IFRAME")[obj.getElementsByTagName("IFRAME").length - 1];
        var doc = lastIFrame.contentDocument || frames["uploadWindow"+id].document;
        var win = lastIFrame.contentWindow || lastIFrame;
        var form = obj.form;
        var callback = form.getAttribute("callback");
        if (doc != null) {
            var content = "<html><head>";
            content += "<\/head><body><form name='iform' method='post' action='"+form.getAttribute("action")+"&do=upload' callback='"+form.getAttribute("callback")+"' enctype='multipart\/form-data'>";
            content += "<input type='file' name='"+name+"' onchange='upload(self);'><\/form><\/body><\/html>";
            doc.write(content);

            win.upload = function(obj) {
               var par = obj.parent.document;

               // hide old iframe
               var iframes = par.getElementsByName('uploadWindow'+id);
               var iframe = iframes[iframes.length - 1];
               iframe.style.visibility = 'hidden';
               iframe.style.width = '0px';
               iframe.style.height = '0px';

               // create new iframe
               var new_iframe = document.createElement('iframe');
               new_iframe.src = iframe.src;
               new_iframe.name = "uploadWindow"+(id+"-");
               new_iframe.id = "uploadWindow"+(id+"-");
               new_iframe.setAttribute("width", '400');
               new_iframe.setAttribute("height", '40');
               new_iframe.frameBorder = '0';
               new_iframe.setAttribute("scrolling", 'no');
               iframe.parentNode.appendChild(new_iframe);
               
               iframe.parentNode.form = obj.document.getElementsByTagName("FORM")[0];

               FileSelector.addUpload(iframe.parentNode, name, id+"-");
/*
               // add image progress
               var image = par.getElementById('targetImage');
               var new_img = par.createElement('img');
               new_img.src = 'templates/main/lytebox/images/loading.gif';
               new_img.className = 'load';
               image.appendChild(new_img);
//*/
               FileSelector.checkForUploaded(obj, id, callback);
               // send
               setTimeout(function(){obj.document.getElementsByTagName("FORM")[0].submit();},100);
            }
        }
    },

    checkForUploaded: function(obj, id, callback) {
        var pos = -1;
        if (document.all && !window.opera)      // to workaround the regexp problem in IE (it detects EOL as normal white space)
            pos = obj.document.body.innerHTML.search(/.OK./);
        else
            pos = obj.document.body.innerHTML.indexOf("\nOK\n");
        if (pos < 0) {
            setTimeout(function(){FileSelector.checkForUploaded(obj, id, callback);}, 500);
        } else {
        	id = (""+id).replace(/-/g, "");
            document.getElementById("selectedFile"+id).innerHTML = obj.document.body.innerHTML.substring(0, obj.document.body.innerHTML.indexOf("OK") - 1);
            document.getElementById("selectedFilePath"+id).value=obj.document.body.innerHTML.substring(obj.document.body.innerHTML.indexOf("OK")+3);
            try { 
            	eval(callback);
            } catch (e){};
        }
    }
};

addLoadEvent(FileSelector.find);