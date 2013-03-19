FETCH:=../lib/fetch.sh
FILES:=everything-spritev2.png
CACHE:=../cache/twitter
FEED_CACHE:=$(CACHE)/feed
AVATAR_CACHE:=$(CACHE)/avatar
.PHONEY: twitter clean cleancache removecache

twitter: $(FILES) $(FEED_CACHE) $(AVATAR_CACHE)

%.png:
	sh $(FETCH) . https://si0.twimg.com/images/dev/cms/intents/icons/sprites/$@

$(CACHE):
	[ -d "$@" ] || mkdir "$@"

$(FEED_CACHE) $(AVATAR_CACHE): $(CACHE)
	[ -d "$@" ] || mkdir "$@"
	chmod 777 "$@"

clean: cleancache
	rm -f $(FILES)

removecache:
	rm -fr "$(FEED_CACHE)" "$(AVATAR_CACHE)"

cleancache: removecache $(FEED_CACHE) $(AVATAR_CACHE)
