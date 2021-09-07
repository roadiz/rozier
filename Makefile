
test:
	php -d "memory_limit=-1" vendor/bin/phpcs --report=full --report-file=./report.txt -p ./
	php -d "memory_limit=-1" vendor/bin/phpstan analyse -c phpstan.neon
#	php -d "memory_limit=-1" vendor/bin/roadiz lint:twig
#	php -d "memory_limit=-1" vendor/bin/roadiz lint:twig themes/Rozier/Resources/views

