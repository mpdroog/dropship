#!/bin/bash
# Fix permissions when scripts where ran as root
cd /var/www/dropship
chown www-data:www-data *.xml *.sqlite *.zip *.csv *.json

