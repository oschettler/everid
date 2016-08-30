#
install:
	composer install
	virtualenv env
	source env/bin/activate
	pip install html2text

export:
	php artisan -v enwrite:export --auth='XXX' --all	

debug:
	env PHP_IDE_CONFIG="serverName=wildcard" XDEBUG_CONFIG=1 php artisan -v enwrite:export --auth='XXX' --notebook=Chosenreich

.PHONY: install export debug
