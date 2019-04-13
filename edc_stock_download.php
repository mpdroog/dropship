<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/core/init.php";
const EDC_URL = "http://api.edc.nl/xml/eg_xml_feed_stock.xml";

function edc_zip($fd) {
    $ch = curl_init(EDC_URL);
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

$fname = __DIR__ . "/edc_stock.xml";
$fd = fopen($fname, "w");
$res = edc_zip($fd);
fclose($fd);
if ($res === true) {
    echo sprintf("Written to %s\n", $fname);
}
