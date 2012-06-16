var IEHacks = {
    init: function() {
        IEHacks.breakClickToActivate();
        if (parseFloat(navigator.userAgent.substring(navigator.userAgent.indexOf('MSIE')+("MSIE".length)+1)) < 7) {
            IEHacks.applyPNGFilter();
        }
    },

    breakClickToActivate: function() {
        obj=document.getElementsByTagName('object');
        for (var i=0; i<obj.length; i++)
            obj[i].outerHTML=obj[i].outerHTML;
    },

    applyPNGFilter: function() {
        var images = document.getElementsByTagName("IMG");
        for (var i = 0; i < images.length; i++) {
            if (images[i].src.search(/\.png$/i) > -1) {
//            	alert(images[i].src);
                var imgID = (images[i].id) ? "id='" + images[i].id + "' " : "";
                var imgClass = (images[i].className) ? "class='" + images[i].className + "' " : "";
                var imgTitle = (images[i].title) ? "title='" + images[i].title + "' " : "title='" + images[i].alt + "' ";
                var imgStyle = "display:inline-block;" + images[i].style.cssText;
                var imgClick = "";
                if (images[i].align == "left") imgStyle = "float:left;" + imgStyle;
                if (images[i].align == "right") imgStyle = "float:right;" + imgStyle;
                if (images[i].parentElement.href) imgStyle = "cursor:hand;" + imgStyle;
                var clickHandler = images[i].getAttribute("onclick");
                if (clickHandler != null || typeof images[i].onclick == "function") {
                    continue;
                }
                var strNewHTML = "<span " + imgID + imgClass + imgTitle
                    + " style=\"" + "width:" + images[i].width + "px; height:" + images[i].height + "px;" + imgStyle + ";"
                    + "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
                    + "(src='" + images[i].src + "', sizingMethod='scale');\"" + imgClick + "></span>";
                images[i].outerHTML = strNewHTML;
            }
        }
    }
};

if (navigator.userAgent.indexOf("MSIE") >= 0) {
    addLoadEvent(IEHacks.init);
}
