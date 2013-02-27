#!/bin/sh
cd $1 || exit 1
WGET="`which wget`"
CURL="`which curl`"
if [ -n "$WGET" ]; then
	exec "$WGET" "$2"
elif [ -n "$CURL" ]; then
	exec "$CURL" -\#LO "$2"
else
	echo "Neither wget or curl are available"
	exit 1;
fi
