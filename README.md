Fully-automated EDC > Bol sync
===============

```bash
apt-get install php7.0-curl php7.0-xml php7.0-sqlite
sqlite3 db.sqlite < db_prod.sql
```

/etc/cron.d/dropship
```
MAILTO=rootdev@gmail.com
# daily
5 0 * * * www-data /usr/bin/php /var/www/dropship/edc_download.php && php /var/www/dropship/edc_read.php
# hourly
0 */1 * * * root /var/www/dropship/perm.sh
3 */1 * * * www-data /usr/bin/php /var/www/dropship/bol_download.php && php /var/www/dropship/bol_read.php
15 */1 * * * www-data /usr/bin/php /var/www/dropship/edc_stock_download.php && php /var/www/dropship/edc_stock_read.php
17 */1 * * * www-data /usr/bin/php /var/www/dropship/bol_upload.php
# Every 10mins
*/10 * * * * www-data /usr/bin/php /var/www/dropship/bol_send_edc.php
```
