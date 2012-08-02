#!/bin/sh
VER=v0.8.4
if [ -x `type -P node || { echo "nothere"; }` ]; then
	echo "Starting up Prails synchronization..."
else
	echo "NodeJS is not yet installed. Downloading the latest version..."
	if [ -x `type -P wget || { echo "nothere"; }` ]; then
		wget 'http://nodejs.org/dist/${VER}/node-${VER}.tar.gz'
	else
		if [ -x `type -P curl || { echo "nothere"; }` ]; then
			curl 'http://nodejs.org/dist/${VER}/node-${VER}.tar.gz' > node-${VAR}.tar.gz
		else
			echo "Unable to find neither wget nor curl. Please install NodeJS manually."
			echo "To do this, please visit http://nodejs.org/dist/${VER}/node-${VER}.tar.gz";
			read
			exit 1;
		fi
	fi
	tar xvzf node-${VER}.tar.gz
	rm -rf node-${VER}.tar.gz
	read
fi
LINE=$1
if [ "$1" = "" ]; then
	LINE="-b ${0%%"sync.sh"}../"
fi
env node ./sync.js $LINE