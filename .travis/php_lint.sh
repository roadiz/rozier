#!/bin/sh -x
bin/phpcs --report=full --report-file=./report.txt -p ./ || exit 1;
bin/roadiz lint:twig || exit 1;
bin/roadiz lint:twig src/Resources/views || exit 1;
