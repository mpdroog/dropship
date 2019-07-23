<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/edc.php";

$fname = CACHE . "/edc_prods.zip";
$fd = fopen($fname, "w");
list($headers, $res) = edc_zip(EDC_URL_PRODS, $fd);
fclose($fd);
if ($res !== true) {
    user_error("edc_prods fail");
}

if (VERBOSE)  echo sprintf("Feed written to %s\n", $fname);

