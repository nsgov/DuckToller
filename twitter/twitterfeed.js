window.DuckToller || (window.DuckToller={});
DuckToller.Retriever = function(ducktype, receiver) {
	this.ducktype = ducktype;
	this.receiver = receiver;
};
DuckToller.TwitterFeed = {
	baseURL: null,
	feedURL: null,
	canRun: document.querySelector && window.XMLHttpRequest && (window.XSLTProcessor || window.ActiveXObject),
	run: function() {
		var tags = document.querySelectorAll("*[data-twitterfeed]");
		this.feedURL || (this.feedURL = this.getURL("?feed={feed}"));
		this.queue = {
			xslt: new DuckToller.Retriever(DuckToller.TwitterFeed.XSLT, this),
			atom: new DuckToller.Retriever(DuckToller.TwitterFeed.Atom, this)
		};
		for (var i = tags.length, tag, m; (i--) && (tag = tags[i]);) {
			var tf = tag.getAttribute("data-twitterfeed").split(';');
			var params = {};
			for (var j = tf.length; --j;) {
				var p = tf[j].match(/\s*(\w+)\s*=\s*(\S+)\s*/);
				if (p) params[p[1]] = p[2];
			}
			if ((m = tf[0].match(/^([@#])(\w+)$/))) {
				var feedURL = this.feedURL.replace('{feed}', escape(m[0])),
				    xsltURL = ('xslt' in params) ? params.xslt : this.getURL('twitterfeed.xslt');
				params.xslt = this.queue.xslt.add(xsltURL);
				this.queue.atom.add(feedURL).feedTag(tag, params);
			}
		}
		this.queue.xslt.retrieve();
	},
	received: function(q) {
		(q==this.queue.xslt) ? this.queue.atom.retrieve() : this.fed();
	},
	getURL: function(basename) {
		if (!this.baseURL) {
			var t = document.querySelector("script[src$='/twitterfeed.js']");
			if (t) {
				var src = t.getAttribute("src");
				this.baseURL = src.substring(0, src.lastIndexOf('/')+1);
			} else console.log("Grrr, Couldn't find script tag, " + document.body.lastChild.previousSibling.tagName);
		}
		return basename ? this.baseURL + basename : this.baseURL;
	},
	fed: function() {	// let garbage collector reclaim memory after feeds are done
		for (var q in this.queue) delete this.queue[q];
		for (var i in this) delete this[i];
		delete this.feeders[feeder.url];
		if (--this.remaining < 1)
			DuckToller.TwitterFeed = null;
	},
	XSLT: function(url) {
		this.url = url;
		this.xp = null;
	},
	Atom: function(url) {
		this.url = url; // For each feed URL in the page, there is...
		this.tags = [];	// a list of tag(s) in the page calling this feed.
	}
};
DuckToller.Retriever.prototype = {
	len: 0,
	list: {},
	add: function(url) {
		if (!(url in this.list)) {
			this.list[url] = new this.ducktype(url);
			this.len++;
			console.log("queued " + url);
		}
		return this.list[url];
	},
	retrieve: function() {
		console.log("Retrieving " + this.len + ' item(s) in ' + this.id);
		for (var url in this.list)
			this.fetch(url);
	},
	fetch: function(url) {
		var q = this, xdr = 0 && url.indexOf(':') && window.XDomainRequest,
		    xr = new (xdr||window.XMLHttpRequest)();
		xr.open("GET", url);
		console.log("onload: " + typeof(xr.onload) + ", onreadystatechange: " + typeof(xr.onreadystatechange));
		var it = {
			failed: function() {
				window.console && console.log(url+": "+(xr.statusText||xr+"failed"));
				it.finish();
			},
			fetched: function() {
				q.list[url].fetched(xr.responseXML||this.xdrXML(xr));
				it.finish();
			},
			finish: function() {
				xr = it = null;
				delete q.list[url];
				--q.len || this.receiver && this.receiver.retrieved(q);
			}
		};
		if (typeof(xr.onload)!='undefined') {
			xr.onabort = xr.onerror = xr.ontimeout = it.failed;
			xr.onload = it.fetched;
		} else xr.onreadystatechange = function() {
			if (xr.readyState==4)
				(xr.status==200) ? it.fetched() : it.failed();
		}
		xr.send();
		console.log("Fetching " + url + " with " + xr);
	},
	xdrXML: function(xdr) {  // only expected from IE XDomainRequest
		var x = new ActiveXObject("MSXML2.DOMDocument");
		x.async = x.validateOnParse = false;
		x.loadXML(xdr.responseText);
		return x;
	},
}
DuckToller.TwitterFeed.XSLT.prototype = {
	fetched: function(xsl) {
		if (window.XSLTProcessor) {
			this.xp = new XSLTProcessor();
			this.xp.importStylesheet(xsl);
		} else if (window.ActiveXObject) {
			var x = new ActiveXObject("MSXML2.XSLTemplate");
			var xml = new ActiveXObject("MSXML2.FreeThreadedDOMDocument");
			xml.loadXML(xsl.xml);
			x.stylesheet = xml;
			this.xp = x.createProcessor();
			this.transform = this.transformIE;
		} else this.transform = function() {};
		console.log("Received XSL: " + this.url);
	},
	transform: function(atom, tag) {
		for (var p in tag.params)
			if (typeof(tag.params[p])=="string")
				this.xp.setParameter(null, p, tag.params[p]);
		var t = this.xp.transformToFragment(atom, document);
		console.log(t);
		this.xp.clearParameters();
		tag.element.appendChild(t);
	},
	transformIE: function(atom, tag) {
		for (var p in tag.params)
			if (typeof(tag.params[p])=="string")
				this.xp.addParameter(p, tag.params[p]);
		this.xp.input = atom;
		this.xp.transform();
		tag.element.innerHTML = this.xp.output;
	}
};
DuckToller.TwitterFeed.Atom.prototype = {
	feedTag: function(tag, params) {
		if (!params.max) params.max = 0;
		this.tags.push({element: tag, params: params});
		tag.setAttribute("aria-busy", "true");
	},
	fetched: function(atom) {
		var now = new Date();
		console.log("Received Feed: " + this.url);
		for (var i=this.tags.length, t, a; i && (t=this.tags[--i]); ) {
			t.params.xslt.transform(atom, t);
			for (var times=t.element.querySelectorAll('time'), j=times.length, timetag; j-- && (timetag=times[j]);) {
				var d = new Date(timetag.getAttribute("datetime"));
				if (now.toDateString()==d.toDateString()) { // today
					var h = d.getHours(), ampm = (h > 11) ? 'pm' : 'am';
					var m = d.getMinutes(); if (m < 10) m = '0'+m;
					h %= 12;
					timetag.innerHTML = (h?h:12) + ':' + m + ampm;
				}
				timetag.setAttribute('title', d.toString());
			}
			t.element.setAttribute("aria-busy", "false");
		}
	}
};
if (!window.console) {
	console = {
		init: function() {
			this.t = document.body.appendChild(document.createElement('textarea'));
			this.t.setAttribute('style', 'width: 100%; height: 10em;');
			console.log((new Date()).toString());
		},
		log: function(msg) {this.t.value += msg + "\n";}
	};
	console.init();
}
DuckToller.TwitterFeed.canRun ? DuckToller.TwitterFeed.run() : DuckToller.TwitterFeed = null;
