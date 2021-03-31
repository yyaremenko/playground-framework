#!/bin/bash

# clean up (remove this exact file at once)
rm /root/run.sh

composer install --no-interaction
composer dump-autoload



# DO NOT run with --daemonize, else the container will stop
php-fpm