Fully-automated EDC/Bigbuy to Bol sync
===============
A collection of PHP-scripts that use a local SQLite database to synchronize EDC and/or BigBuy to Bol.

If you are interested in this tooling but need some help with it? You can contact me.

EDC=ErotischeGroothandel https://www.one-dc.com/nl/

BigBuy=https://www.bigbuy.eu/

Bol=https://www.bol.com/

Dependencies+basic DB init
```bash
apt-get install php7.2-curl php7.2-xml php7.2-sqlite php7.2-zip
sqlite3 db.sqlite < default.sql
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

FAQ
==============

How is edc_discount.csv set?

Using the Vigilo-renderapi service to read it from the EDC-website.
We use this 'hacky' solution because you need to be logged in in order to get the discounts specific to your account (yes even with the API-key specified when this tooling was written).

How are products filtered you want/don't want on Bol?

I've built this code to filter out (blacklist) products that
match on title:
https://github.com/mpdroog/dropship/blob/8a53515f2e46515177687f604ef33032b0f3da66/filter.php#L46

How is the Bol-price calculated and where?

I've hardcoded it: when I read the products from the XML-ZIP I calculate the price and store it in the SQLite DB:
https://github.com/mpdroog/dropship/blob/master/edc_read.php#L176

```
1- I'm using the B2B price for certain brands (as they want a minimum price)
2- In all other cases I use the cost price + TAX + shipping cost + MAX( (+10eur + bol-cost + 1eur) OR (10% profit + bol-cost + 1eur) )
```

What ResellerAPI of Bol is used? V3

Where are the Bol-creds configured? https://github.com/mpdroog/dropship/blob/master/core/bol.php#L3

Where are the EDC-creds configured? https://github.com/mpdroog/dropship/blob/master/core/edc.php#L2

Where are the BigBuy-creds configured? https://github.com/mpdroog/dropship/blob/master/core/bigbuy.php#L4

Can this project also sync orders from Bol back to EDC/BigBuy?
Yes! But this code is inside another repository, you can find it here:
https://github.com/mpdroog/dropship.rootdev/blob/master/action/cmp/edc/index.php
