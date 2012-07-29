#!/bin/sh
#
# Mac OS X style command to be run directly from Finder
#

if [ -x `type -P node || { echo "nothere"; }` ]; then
	echo "Starting up Prails synchronization..."
else
	echo "NodeJS is not yet installed. Downloading the latest version..."
	wget 'http://nodejs.org/dist/v0.8.4/node-v0.8.4.pkg'
	open -W node-v0.8.4.pkg
	rm -rf node-v0.8.4.pkg
fi
LINE=$1
if [ "$1" = "" ]; then
	node ${0%%"sync.command"}sync.js
else
	node ${0%%"sync.command"}sync.js $LINE
fi
