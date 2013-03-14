FETCH:=../lib/fetch.sh
FILES:=blue_bird_16.png everything-spritev2.png
.PHONEY: twitter clean cleancache

twitter: $(FILES)

%.png:
	sh $(FETCH) . https://si0.twimg.com/images/dev/cms/intents/icons/sprites/$@

clean: cleancache
	rm -f $(FILES)

cleancache:
	rm -fr avatar feed
	mkdir avatar && mkdir feed
	chmod 777 avatar feed
