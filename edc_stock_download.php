<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/edc.php";

$fname = CACHE . "/edc_stock.xml";
$fd = fopen($fname, "w");
list($headers, $res) = edc_zip(EDC_URL_STOCK, $fd);
fclose($fd);

$lm = strtotime($headers["last-modified"][0]);
if (! touch($fname, $lm, $lm)) {
    user_error(sprintf("touch($fname)=%s failed", $lm));
}
if ($res !== true) {
    user_error("edc_stock fail");
}
if (VERBOSE) echo sprintf("Written to %s\n", $fname);

