var TweetCache = {
	URL: location.protocol+"//10.10.13.34/~nordludp/DuckToller/twitter/?feed=",
	run: function() {
		var tags = document.querySelectorAll("*[data-twitterfeed]");
		for (var i = tags.length, tag, m; (i--) && (tag = tags[i]);) {
			var tf = tag.getAttribute("data-twitterfeed").split(';');
			var params = {};
			for (var j = tf.length; --j;) {
				var p = tf[j].match("\s*(\w+)\s*=\s(\S+)\s*");
				if (p) params[p[1]] = p[2];
			}
			if ((m = tf[0].match(/^([@#])(\w+)$/))) {
				var url = this.URL + escape(m[0]);
				if (!(url in this.feeders)) {
					this.feeders[url] = new TweetCache.Feeder(url);
					this.remaining++;
				}
				this.feeders[url].feedTag(tag, params)
			}
		}
		for (var f in this.feeders)
			this.feeders[f].load();
	},
	fed: function(feeder) {	// let garbage collector reclaim memory after feeds are done
		delete this.feeders[feeder.url];
		if (--this.remaining < 1)
			TweetCache = null;
	},
	Loader: window.XDomainRequest || window.XMLHttpRequest,	// How to load a feed URL
	Feeder: function(url) {		// For each feed URL, fetch tweets & put into document
		this.url = url;
		this.tags = [];
		this.max = 0;
	},
	feeders: [],
	remaining: 0,
	canRun: document.querySelectorAll && window.XMLHttpRequest
};
TweetCache.Feeder.prototype = {
	feedTag: function(tag, params) {
		if (!params.max) params.max = 0;
		this.max = Math.max(this.max, params.max-0);
		this.tags.push({tag: tag, params: params});
		tag.setAttribute("aria-busy", "true");
	},
	load: function() {
		var it = this, cors = this.cors = new TweetCache.Loader(), qs = '';
		if (this.max)
			qs = '?max=' + max;
		cors.open("GET", this.url+qs);
		cors.onerror   = function() { it.failed(cors.statusText||"Loading failed. :("); };
		cors.ontimeout = function() { it.failed("Connection timeout."); };
		cors.onload    = function() { it.loaded(cors.responseXML); };
		cors.send();
	},
	loaded: function(atom) {
		var xmlns = 'http://www.w3.org/2005/Atom';
		var entrylist = atom.getElementsByTagNameNS(xmlns, 'entry'), entries = [];
		var total = entrylist.length;
		if (this.max)
			total = Math.min(total, this.max);
		for (var i = 0; i < total; entries[i] = entrylist[i]);
		entrylist = null;
		for (var i=this.tags.length, t; i && (t=this.tags[--i]); ) {
			this.tags[i].tag.innerHTML = html;
		}
		var max = entries.length;
		var html = '';
		for (var i=0; i < max; i++) {
			var content = entries[i].getElementsByTagNameNS(xmlns, 'content');
			if (content.length==1)
			html += content[0].lastChild.nodeValue;
		}
		for (var i=this.tags.length; i--; this.tags[i].innerHTML = html);
		this.done();
	},
	failed: function(errmsg) {
		window.console && console.log("TweetCache.Feeder["+this.url+"]: " + errmsg);
		this.done();
	},
	done: function() {
		for (var i=this.tags.length; i--; this.tags[i].setAttribute("aria-busy", "false"));
		this.url = this.tags = this.cors = null;
		TweetCache.fed(this);
	}
};

TweetCache.canRun ? TweetCache.run() : TweetCache = null;
