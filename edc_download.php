<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
const EDC_URL_PRODS = "http://api.edc.nl/b2b_feed.php?key=35t55w94ec2833998860r3e5626eet1c&sort=xml&type=zip&lang=nl&version=2015";
const EDC_URL_DISCOUNT = "https://www.erotischegroothandel.nl/download/discountoverview.csv?apikey=35t55w94ec2833998860r3e5626eet1c";

function edc_zip($url, $fd) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FILE, $fd);

    $res = curl_exec($ch);
    if ($res === false) {
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

$fname = __DIR__ . "/edc_prods.zip";
$fd = fopen($fname, "w");
$res = edc_zip(EDC_URL_PRODS, $fd);
fclose($fd);
if ($res === true) {
    echo sprintf("Feed written to %s\n", $fname);
}

$fname = __DIR__ . "/edc_discount.csv";
$fd = fopen($fname, "w");
$res = edc_zip(EDC_URL_DISCOUNT, $fd);
fclose($fd);
if ($res === true) {
    echo sprintf("Feed written to %s\n", $fname);
}
