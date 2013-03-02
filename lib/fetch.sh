#!/bin/sh
cd $1 || exit 1
CURL="`which curl`"
WGET="`which wget`"
if [ -n "$CURL" ]; then
	exec "$CURL" -\#LO "$2"
elif [ -n "$WGET" ]; then
	exec "$WGET" "$2"
else
	echo "Neither curl or wget are available"
	exit 1;
fi
