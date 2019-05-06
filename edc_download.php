<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/edc.php";

$fname = __DIR__ . "/edc_prods.zip";
$fd = fopen($fname, "w");
list($headers, $res) = edc_zip(EDC_URL_PRODS, $fd);
fclose($fd);
if ($res !== true) {
    user_error("edc_prods fail");
}

if (VERBOSE)  echo sprintf("Feed written to %s\n", $fname);

/*$fname = __DIR__ . "/edc_discount.csv";
$fd = fopen($fname, "w");
list($headers, $res) = edc_zip(EDC_URL_DISCOUNT, $fd);
fclose($fd);
if ($res !== true) {
    user_error("edc_discount fail");
}*/
echo "TODO: edc_discount automate\n";

if (VERBOSE) echo sprintf("Feed written to %s\n", $fname);

