<?php
const EDC_URL = "http://api.edc.nl/%s?key=35t55w94ec2833998860r3e5626eet1c&sort=xml&type=xml&lang=nl&version=2015";

function edc_http($url) {
    $session = curl_init(sprintf(EDC_URL, $url));
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($session);
    curl_close($session);
    return simple_xml_load_string($response);
}

$res = edc_http("/b2b_feed.php");
var_dump($res);

