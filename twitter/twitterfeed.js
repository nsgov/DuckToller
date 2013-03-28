window.DuckToller || (window.DuckToller={});
DuckToller.TwitterFeed = {
	baseURL: null,
	feedURL: null,
	canRun: document.querySelector && window.XMLHttpRequest && (window.XSLTProcessor || window.ActiveXObject),
	run: function() {
		var tags = document.querySelectorAll("*[data-twitterfeed]");
		this.feedURL || (this.feedURL = this.getURL("?feed={feed}"));
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
				params.xslt = this.queue("XSLT", xsltURL);
				this.queue("Feed", feedURL).feedTag(tag, params);
			}
		}
		this.retrieve("XSLT");
	},
	getURL: function(basename) {
		if (!this.baseURL) {
			var t = document.querySelector("script[src$='/twitterfeed.js']");
			if (t) {
				var src = t.getAttribute("src");
				this.baseURL = src.substring(0, src.lastIndexOf('/')+1);
			}
		}
		return basename ? this.baseURL + basename : this.baseURL;
	},
	queue: function(id, url) { // queue an xml file to be loaded, if not already in queue
		var q = this._q[id];
		if (!(url in q.list)) {
			q.list[url] = new DuckToller.TwitterFeed[id](url);
			q.len++;
			console.log(id + "queue += " + url);
		}
		return q.list[url];
	},
	_q: {Feed: {len: 0, list: {}}, XSLT: {len: 0, list: {}}},
	retrieve: function(queueID) {
		var q = this._q[queueID];
		for (var url in q.list)
			this.fetch(url, q);
	},
	fetch: function(url, q) { // load an xml file
		var we = this, it = q.list[url], xdr = url.match(/^https?:/) && window.XDomainRequest,
		    xr = new (xdr||window.XMLHttpRequest)();
		xr.open("GET", url);
		xr.onerror   = function() { we.failed(url, xr.statusText||(xr+" denied")); };
		xr.onabort   = xr.onerror;
		xr.ontimeout = function() { we.failed(url, "Connection timeout"); };
		xr.onload    = function() { it.fetched(xr.responseXML||this.xdrXML(xr)); };
		xr.onloadend = function() { delete q.list[url]; --q.len || we.retrieved(q); };
		xr.send();
	},
	retrieved: function(q) {
		if (q == this._q.XSLT)
			this.retrieve("Feed");
	},
	xdrXML: function(xdr) {  // only expected from IE XDomainRequest
		var x = new ActiveXObject("MSXML2.DOMDocument");
		x.async = x.validateOnParse = false;
		x.loadXML(xdr.responseText);
		return x;
	},
	failed: function(url, msg) {
		window.console && console.log(url + ": " + msg);
	},
	fed: function(feeder) {	// let garbage collector reclaim memory after feeds are done
		delete this.feeders[feeder.url];
		if (--this.remaining < 1)
			DuckToller.TwitterFeed = null;
	},
	XSLT: function(url) {
		this.url = url;
		this.xp = null;
	},
	Feed: function(url) {
		this.url = url; // For each feed URL in the page, there is...
		this.tags = [];	// a list of tag(s) in the page calling this feed.
	}
};
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
DuckToller.TwitterFeed.Feed.prototype = {
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
			}
			t.element.setAttribute("aria-busy", "false");
		}
	},
};

DuckToller.TwitterFeed.canRun ? DuckToller.TwitterFeed.run() : DuckToller.TwitterFeed = null;
