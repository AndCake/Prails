#!/bin/sh
PVER=`cat version`
rm -rf prails
cp -R src prails
tar cvjf sync.tar.bz2 sync
mv sync.tar.bz2 prails/
tar cvjf prails-$PVER.tar.bz2 prails
tar cvzf prails-$PVER.tar.gz prails
cp setup.php prails-$PVER-installer.php
./urlencode.php prails-$PVER.tar.bz2 >> prails-$PVER-installer.php
