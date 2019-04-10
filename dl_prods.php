<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
const EDC_URL = "http://api.edc.nl/%s?key=35t55w94ec2833998860r3e5626eet1c&sort=xml&type=zip&lang=nl&version=2015";

function edc_zip($url, $fd) {
    $ch = curl_init(sprintf(EDC_URL, $url));
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

$fname = __DIR__ . "/feed_prod.zip";
$fd = fopen($fname, "w");
$res = edc_zip("/b2b_feed.php", $fd);
fclose($fd);
var_dump($res);

echo spintf("Downloaded to %s\n", $fname);
