<?php

const BIGBUY_URL = "https://api.bigbuy.eu";
const BIGBUY_KEY = "NjZlNTJjNTliODg2ODk5Y2JmZjk4OGI4M2Q1MTRhNjk1YWNhNzQxYmI1YjJlYmZmZTI0NTI4ZWNlMDY0NmU5MQ";

function bb_json($url, array $args) {
    $ch = curl_init(BIGBUY_URL . $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    //curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY),
        'Content-Type: application/json',
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

    $res = curl_exec($ch);
    if ($res === false) {
        var_dump($res);
        user_error(curl_error($ch));
    }
    curl_close($ch);

    if ($res === false) {
        user_error('curl_exec fail');
    }

    $json = json_decode($res, true);
    if (! is_array($json)) {
        print_r($res);
        user_error("bb_json fail");
    }
    return $json;
}
