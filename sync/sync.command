#!/bin/sh
#
# Mac OS X style command to be run directly from Finder
#
VER=v0.8.4
if [ -x `type -P node || { echo "nothere"; }` ]; then
	echo "Starting up Prails synchronization..."
else
	echo "NodeJS is not yet installed. Downloading the latest version..."
	if [ -x `type -P wget || { echo "nothere"; }` ]; then
		wget 'http://nodejs.org/dist/${VER}/node-${VER}.pkg'
	else
		if [ -x `type -P curl || { echo "nothere"; }` ]; then
			curl 'http://nodejs.org/dist/${VER}/node-${VER}.pkg' > node-${VER}.pkg
		else
			echo "Unable to find neither wget nor curl. Please install NodeJS manually."
			echo "To do this, please visit http://nodejs.org/dist/${VER}/node-${VER}.pkg";
			read
			exit 1;
		fi
	fi
	open -W node-${VER}.pkg
	rm -rf node-${VER}.pkg
	read
fi
LINE=$1
if [ "$1" = "" ]; then
	node ${0%%"sync.command"}sync.js -b ${0%%"sync.command"}../
else
	node ${0%%"sync.command"}sync.js $LINE
fi