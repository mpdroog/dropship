#!/bin/bash
# Fix permissions when scripts where ran as root
cd /var/www/dropship
chown www-data:www-data -R /var/www/dropship/cache
chown www-data:www-data -R cache/*.*
