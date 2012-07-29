#!/bin/sh
LINE=$1
if [ "$1" = "" ]; then
	LINE=../
fi
env node ./sync.js $LINE $2
