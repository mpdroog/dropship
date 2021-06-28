Fully-automated EDC/Bigbuy to Bol sync
===============
A collection of PHP-scripts that use a local SQLite database to synchronize EDC and/or BigBuy to Bol.

EDC=ErotischeGroothandel https://www.one-dc.com/nl/

BigBuy=https://www.bigbuy.eu/

Bol=https://www.bol.com/


```bash
apt-get install php7.2-curl php7.2-xml php7.2-sqlite php7.2-zip
sqlite3 db.sqlite < db_prod.sql
```

/etc/cron.d/dropship
```
MAILTO=rootdev@gmail.com
# daily
0 0 * * * www-data /usr/bin/php /var/www/dropship/edc_download.php && php /var/www/dropship/edc_read.php
# hourly
1 */1 * * * root /var/www/dropship/perm.sh
3 */1 * * * www-data /usr/bin/php /var/www/dropship/bol_download.php && php /var/www/dropship/bol_read.php
1 */6 * * * www-data /usr/bin/php /var/www/dropship/edc_discount.php
22 */1 * * * www-data /usr/bin/php /var/www/dropship/edc_prepaid.php

15 */1 * * * www-data /usr/bin/php /var/www/dropship/edc_stock_download.php && php /var/www/dropship/edc_stock_read.php
17 */1 * * * www-data /usr/bin/php /var/www/dropship/bol_upload.php

# 10min
*/10 * * * * www-data /usr/bin/php /var/www/dropship/bol_send_edc.php -w

#1 */1 * * * root /var/www/dropship/bigbuy/perm.sh
#25 */1 * * * www-data /usr/bin/php /var/www/dropship/bigbuy/bb_download.php && /usr/bin/php /var/www/dropship/bigbuy/bb_read.php && /usr/bin/php /var/www/dropship/bigbuy/bb_shipping.php
#29 */1 * * * www-data /usr/bin/php /var/www/dropship/bigbuy/bol_read.php
#29 */1 * * * www-data /usr/bin/php /var/www/dropship/bigbuy/calc_pricing.php
#33 */1 * * * www-data /usr/bin/php /var/www/dropship/bigbuy/bol_upload.php -w
```
