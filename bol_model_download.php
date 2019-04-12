<?php
/*
 * Download BOL datamodel to know how to upload.
 */
require __DIR__ . "/core/init.php";
const API_URL = "https://api.bol.com/retailer";
const API_CLIENTID = "8338f293-a8a0-4d6c-b660-7d77d76002cb";
const API_SECRET = "aMPKgg6tsz_5fvRQbNweO4ejCaSdOI_cVb698D5YwfMy1GeAvm94YGeAD1JRjmI_eGKk0s2bRXc59NECLcrKSw";
const API_USER = "sync";

$arg_exportid = null;
if (count($_SERVER["argv"]) === 2) {
    $arg_exportid = $_SERVER["argv"][1];
    if (! is_numeric($arg_exportid)) {
        exit("ERR: argument should be exportid");
    }
}
if (count($_SERVER["argv"]) > 2) {
    exit("ERR: argument should be exportid");
}

function bol_zip($method, $url, $fd) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_FILE, $fd);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);
    if ($res === false) {
        var_dump($url);
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

// https://mailing.bol.com/content/Datamodel.xml
$fname = __DIR__ . "/bol_datamodel.xml";
$fd = fopen($fname, "w");
$res = bol_zip("GET", "https://mailing.bol.com/content/Datamodel.xml", $fd);
var_dump($res);
fclose($fd);

if ($res === true) {
    echo sprintf("Written to %s\n", $fname);
}
