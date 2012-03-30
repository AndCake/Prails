/**
 * 
 */

(function(){
	function getNodes(prop, val, meth, nd, useSelf ){
		var r=[], any= getNodes[val]===true;
		nd=nd||document.documentElement;
		if(nd.constructor===Array){nd={childNodes:nd};}
		for(var cn=nd.childNodes, i=0, mx=cn.length;i<mx;i++){
			var it=cn[i];
			if(it.childNodes.length && !useSelf){r=r.concat(getNodes(prop,val,meth,it,useSelf ));}
			if( any ? it[prop] : (it[prop]!==undefined && (meth ? ""[meth] && String(it[prop])[meth](val) : it[prop]==val))){
				r[r.length]=it; 
			}
		}//nxt
		
		return r;
	};
	getNodes[null]=true; 
	getNodes[undefined]=true;
	
	var contents = 0;
	var comments = getNodes("nodeType", 8);
	$A(comments).each(function(item) {
		if (item.data.indexOf("[LANG:") == 0) {
			item.parentNode.insertBefore(new Element('span', {"class": 'langhelper'}).observe('mouseover',function(){
				var pos = this.cumulativeOffset();
				var tt = new Element("div", {'class': 'langtooltip'}).update(item.data.replace('[LANG:', '').replace(']', ''));
				document.body.appendChild(tt);
				this.tt = tt;
				tt.style.left = pos.left+"px";
				tt.style.top = pos.top+"px";
			}).observe("mouseout", function(){
				if (this.tt) {
					this.tt.remove();
					this.tt = null;
				}
			}).observe("click", function() {
				window.open('?event=builder:home&open_nav=qwbuilder_langsPanel&open_tree='+item.data.replace('[LANG:', '').replace(']', ''), 'prails');
			}), item);
			contents++;
		}
	});
	if (contents == 0 && location.href.indexOf("static/") >= 0) { 
		var path = location.href.substr(location.href.indexOf("static/") + "static/".length).replace(/\.html(.*)?$/i, "");
		window.open("?event=builder:home&open_nav=qwbuilder_langsPanel&open_tree="+path, "prails");
	}
})();
