@echo off
IF (%1)==() GOTO empty4
nodejs\node sync.js %1
GOTO end
:empty4
nodejs\node sync.js -b ../
:end
