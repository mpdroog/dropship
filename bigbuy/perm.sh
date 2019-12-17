#!/bin/bash
# Fix permissions when scripts where ran as root
cd /var/www/dropship/bigbuy
chown www-data:www-data -R /var/www/dropship/bigbuy/cache
chown www-data:www-data /var/www/dropship/bigbuy
chown www-data:www-data /var/www/dropship/bigbuy/db.sqlite
