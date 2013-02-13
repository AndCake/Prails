PVER=`cat version`
PREFIX=prails-$(PVER)
PBZ=$(PREFIX).tar.bz2
PGZ=$(PREFIX).tar.gz
PIN=$(PREFIX)-installer.php

help:
	@echo "Available make commands:"
	@echo "\tclean		# cleans the project from any previous build results"
	@echo "\tbuild		# creates all files necessary for distribution"
	@echo "\ttest		# will execute all test cases"
	@echo "\tall		# will clean, build and test the project"

all: clean build test

clean:
	@rm -f prails-$(PVER)-installer.php
	@rm -f prails-$(PVER).tar.gz
	@rm -f prails-$(PVER).tar.bz2

build: clean
	@cp -R src prails
	@tar cvjf sync.tar.bz2 sync
	@mv sync.tar.bz2 prails/
	@tar cvjf $(PBZ) prails
	@tar cvzf $(PGZ) prails
	@rm -rf prails
	@cp setup.php $(PIN)
	@./urlencode.php $(PBZ) >> $(PIN)

test:
	@cd src/ && php ../test/alltests.php

.PHONY:	test build clean help