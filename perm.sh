#!/bin/bash
# Fix permissions when scripts where ran as root
cd /var/www/dropship
chown www-data:www-data -R cache
