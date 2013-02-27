
ALL:=twitteroauth
.PHONEY:=all clean

TWITTEROAUTH_URL:=https://github.com/abraham/twitteroauth/raw/master

all: $(ALL)

clean:
	rm -fr $(ALL)

twitteroauth:
	[ -d $@ ] || mkdir $@
	sh fetch.sh $@ $(TWITTEROAUTH_URL)/LICENSE
	sh fetch.sh $@ $(TWITTEROAUTH_URL)/twitteroauth/OAuth.php
	sh fetch.sh $@ $(TWITTEROAUTH_URL)/twitteroauth/twitteroauth.php

