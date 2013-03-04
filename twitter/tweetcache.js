var TweetCache = {
	URL: location.protocol+"//10.10.13.34/~nordludp/DuckToller/twitter/?feed=",
	run: function() {
		var tags = document.querySelectorAll("*[data-twitterfeed]");
		for (var i = tags.length, tag, m; (i--) && (tag = tags[i]);) {
			if ((m = tag.getAttribute("data-twitterfeed").match(/^([@#])(\w+)$/))) {
				var url = this.URL + m[0];
				if (!(url in this.feeders)) {
					this.feeders[url] = new TweetCache.Feeder(url);
					this.remaining++;
				}
				this.feeders[url].feedTag(tag)
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
	},
	feeders: [],
	remaining: 0,
	canRun: document.querySelectorAll && window.XMLHttpRequest
};
TweetCache.Feeder.prototype = {
	feedTag: function(tag) {
		this.tags.push(tag);
		tag.setAttribute("aria-busy", "true");
	},
	load: function() {
		var it = this, cors = this.cors = new TweetCache.Loader();
		cors.open("GET", this.url);
		cors.onerror   = function() { it.failed(cors.statusText||"Loading failed. :("); };
		cors.ontimeout = function() { it.failed("Connection timeout."); };
		cors.onload    = function() { it.loaded(cors.responseXML); };
		cors.send();
	},
	loaded: function(atom) {
		var xmlns = 'http://www.w3.org/2005/Atom';
		var entries = atom.getElementsByTagNameNS(xmlns, 'entry');
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
