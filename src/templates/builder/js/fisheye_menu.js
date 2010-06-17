/**
 * @author roq
 */
var FishEye = Class.create({
	options: null,
	handler: null,
	
	initialize: function(items, options) {
		this.options = Object.extend(FishEye.defaultOptions, options || {});
		this.options.items = items;
		if (!this.options.container) {
			this.options.container = items[0].parentNode;
		}

		this.initMouseOver(document);
		this.handler({clientX: (this.options.position == "right" ? 0 : 1000), clientY: 1000, pageX: 0, pageY: 0});
		
		if (this.options.appear) {
			var startStyle = {
				position:"absolute", 
				display: "block"
			};
			if (this.options.position == "right") {
				startStyle.right = "-"+this.options.small.width+"px";
				startStyle.textAlign="right";
				endStyle = "right:1px;";	
			} else {
				startStyle.left = "-"+this.options.small.width+"px";
				startStyle.textAlign="left";
				endStyle = "left:1px;";	
			}
			this.options.container.setStyle(startStyle);
			this.options.container.morph(endStyle);
		}		

//*
		$$("iframe").each(function(item){
			var obj = {
				me: this,
				item: item,
				offset: item.cumulativeOffset()
			};
			var h = function(event){
				if (this.me.handler == null) return;
				var pointer = {
					'x': event.pageX || (event.clientX + (this.item.contentDocument.documentElement.scrollLeft || this.item.contentDocument.body.scrollLeft)) || 0,
					'y': event.pageY || (event.clientY + (this.item.contentDocument.documentElement.scrollTop || this.item.contentDocument.body.scrollTop)) || 0
				};
				var ev = {
					pageX: event.pageX + this.offset[0],
					pageY: event.pageY + this.offset[1],
					clientX: event.clientX + this.offset[0],
					clientY: event.clientY + this.offset[1]
				};
				this.me.handler(ev);
			}.bind(obj);
			try {
				if(item.contentDocument.addEventListener){
					item.contentDocument.addEventListener("mousemove",h,false);
				} else {
					item.contentDocument.attachEvent("onmousemove",h);
				}
			} catch (e) {console.log(e.message);}
		}.bind(this));//*/
	},
	
	initMouseOver: function(el) {
		el.observe("mousemove", this.handler = function(event) {
			var pointer = {
				'x': event.pageX || (event.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft)) || 0,
				'y' : event.pageY || (event.clientY + (document.documentElement.scrollTop || document.body.scrollTop)) || 0
			};
			var left = 0;
			this.options.items.each(function(item) {
				
				if (!item.act) item.act = {};
				item.act.pos = Position.cumulativeOffset(item);
				item.act.dim = item.getDimensions();
				
				distance = Math.sqrt(Math.pow((item.act.pos[0] + item.act.dim.width / 2) - pointer.x, 2) + Math.pow((item.act.pos[1] + item.act.dim.height / 2) - pointer.y, 2));
				newDim   = (distance - item.act.dim.height / 2) / this.options.proximity;
				
				item.act.height = parseInt(this.options.big.height - newDim * this.options.big.height);
				item.act.width = parseInt(this.options.big.width - newDim * this.options.big.width);
	
				if(item.act.height <= this.options.small.height) {
					item.act.height = this.options.small.height;
					item.act.width = this.options.small.width;
				}
				if(item.act.height >= this.options.big.height) {
					item.act.height = this.options.big.height;
					item.act.width = this.options.big.width;
				}

				var height = item.act.height;
				var width = item.act.width;
				
				item.setStyle({
					'position':'absolute',
					'width':width + 'px',
					'height':height + 'px',
					'top':left+ 'px',
					fontSize: height+"px"
				});
				var pos = (this.options.position == 'left')?'left':'right';
	
				if(pos == 'left')
					item.style.left = '0';
				else
					item.style.right = '0';
				
				var gap = (this.options.gap - this.options.gap * newDim);
				if (gap < this.options.minGap) gap = this.options.minGap;
				if (gap > this.options.gap) gap = this.options.gap;
				left = left + height + gap;
			}.bind(this));
			
			try {
				left = (this.options.container.parentNode.getHeight() - left) / 2 + 'px';
			} catch (e) {};
			this.options.container.setStyle({'top':left,'height':left}); 
			
		}.bindAsEventListener(this));		
	},
	
	dispose: function() {
		document.stopObserving("mousemove", this.handler);
		this.handler = null;
	}
});

FishEye = Object.extend(FishEye, {
	defaultOptions: {
		big: {
			width: 100,
			height: 20
		},
		small: {
			width: 20,
			height: 5
		},
		minGap: 2,
		gap: 10,
		proximity: 150,
		position: "right",
		items: [],
		appear: true,
		container: null		
	}
});
