window.DuckToller || (window.DuckToller={});
DuckToller.TwitterFeed = {
	URL: null,
	canRun: document.querySelectorAll && window.XMLHttpRequest,
	run: function() {
		var tags = document.querySelectorAll("*[data-twitterfeed]");
		this.URL || this.getURL();
		for (var i = tags.length, tag, m; (i--) && (tag = tags[i]);) {
			var tf = tag.getAttribute("data-twitterfeed").split(';');
			var params = {};
			for (var j = tf.length; --j;) {
				var p = tf[j].match(/\s*(\w+)\s*=\s*(\S+)\s*/);
				if (p) params[p[1]] = p[2];
			}
			if ((m = tf[0].match(/^([@#])(\w+)$/))) {
				var url = this.URL.replace('{feed}', escape(m[0]));
				if (!(url in this.feeders)) {
					this.feeders[url] = new DuckToller.TwitterFeed.Feeder(url);
					this.remaining++;
				}
				this.feeders[url].feedTag(tag, params)
			}
		}
		for (var f in this.feeders)
			this.feeders[f].load();
	},
	getURL: function() {
		var t = document.querySelector("script[src$='/twitterfeed.js']");
		if (t) {
			var src = t.getAttribute("src");
			this.URL = src.substring(0, src.lastIndexOf('/')+1) + '?feed={feed}';
		}
	},
	fed: function(feeder) {	// let garbage collector reclaim memory after feeds are done
		delete this.feeders[feeder.url];
		if (--this.remaining < 1)
			DuckToller.TwitterFeed = null;
	},
	Loader: window.XDomainRequest || window.XMLHttpRequest,	// How to load a feed URL
	Feeder: function(url) {		// For each feed URL, fetch tweets & put into document
		this.url = url;
		this.tags = [];
		this.max = 0;
	},
	feeders: [],
	remaining: 0
};
DuckToller.TwitterFeed.Feeder.prototype = {
	feedTag: function(tag, params) {
		if (!params.max) params.max = 0;
		this.max = Math.max(this.max, params.max-0);
		this.tags.push({tag: tag, params: params});
		tag.setAttribute("aria-busy", "true");
	},
	load: function() {
		var it = this, cors = this.cors = new DuckToller.TwitterFeed.Loader();
		var qs = (this.url.indexOf('?') > -1) ? '&' : '?';
		if (this.max)
			qs += 'max=' + this.max;
		cors.open("GET", this.url+qs);
		cors.onerror   = function() { it.failed(cors.statusText||"Loading failed. :("); };
		cors.ontimeout = function() { it.failed("Connection timeout."); };
		cors.onload    = function() { it.loaded(cors.responseXML); };
		cors.send();
	},
	loaded: function(atom) {
		var xmlns = 'http://www.w3.org/2005/Atom';
		var entrylist = atom.getElementsByTagNameNS(xmlns, 'entry'), html = [];
		var total = entrylist.length;
		if (this.max)
			total = Math.min(total, this.max);
		for (var i = 0; i < total; i++) {
			var content = entrylist[i].getElementsByTagNameNS(xmlns, 'content');
			html[i] = content.length ? String(content[0].lastChild.nodeValue) : '-';
		}
		entrylist = null;
		for (var i=this.tags.length, t; i && (t=this.tags[--i]); ) {
			if ((t.params.max > 0) && (t.params.max < total))
				t.tag.innerHTML = html.slice(0, t.params.max).join('');
			else
				t.tag.innerHTML = html.join('');
		}
		this.done();
	},
	failed: function(errmsg) {
		window.console && console.log("DuckToller.TwitterFeed.Feeder["+this.url+"]: " + errmsg);
		this.done();
	},
	done: function() {
		for (var i=this.tags.length; i--; this.tags[i].tag.setAttribute("aria-busy", "false"));
		this.url = this.tags = this.cors = null;
		DuckToller.TwitterFeed.fed(this);
	}
};

DuckToller.TwitterFeed.canRun ? DuckToller.TwitterFeed.run() : DuckToller.TwitterFeed = null;
