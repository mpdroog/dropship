<?php
/**
 * Download full product feed (ZIP) and save as JSON.
 */
require __DIR__ . "/_init.php";
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
/*function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_encode($xml);
}*/

$fname = tempnam("/tmp", "edc-");
$fd = fopen(__DIR__ . "/feed_prod.zip", "w");
var_dump($fname);

$res = edc_zip("/b2b_feed.php", $fd);
var_dump($res);
fclose($fd);

/*$zip = new ZipArchive;
$extractPath = __DIR__;
if($zip->open($fname) != "true"){
 user_error("Error :- Unable to open the Zip File");
}
$zip->extractTo($extractPath);
$zip->close();

file_put_contents(__DIR__ . "/b2b_feed.json", file_get_contents(xml(__DIR__ . "/eg_xml_feed_2015_nl.xml")));*/
