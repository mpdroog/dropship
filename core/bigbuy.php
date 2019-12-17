<?php

const BIGBUY_URL = "https://api.bigbuy.eu";
const BIGBUY_KEY = "NjZlNTJjNTliODg2ODk5Y2JmZjk4OGI4M2Q1MTRhNjk1YWNhNzQxYmI1YjJlYmZmZTI0NTI4ZWNlMDY0NmU5MQ";

function bb_json($url, array $args) {
    $ch = curl_init(BIGBUY_URL . $url);
    $ok = 1;
    $ok &= curl_setopt($ch, CURLOPT_POST, true);
    $ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
    $ok &= curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    $ok &= curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $ok &= curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    $ok &= curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $ok &= curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY),
        'Content-Type: application/json',
        "Accept: application/json"
    ]);
    $ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($ok !== 1) {
        user_error("curl_setopt failed");
    }

    $res = curl_exec($ch);
    if ($res === false) {
        var_dump($res);
        user_error(curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

function bb_json_header($url, array $args) {
    $ch = curl_init(BIGBUY_URL . $url);
    $ok = 1;
    $ok &= curl_setopt($ch, CURLOPT_POST, true);
    $ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
    $ok &= curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    $ok &= curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $ok &= curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    $ok &= curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $ok &= curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY),
        'Content-Type: application/json',
        "Accept: application/json"
    ]);
    $ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $res_headers = [];
    $ok &= curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$res_headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
          return $len;
        $name = strtolower(trim($header[0]));
        if (!array_key_exists($name, $res_headers))
          $res_headers[$name] = [trim($header[1])];
        else
          $res_headers[$name][] = trim($header[1]);
        return $len;
    });
    if ($ok !== 1) {
        user_error("curl_setopt failed");
    }

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
    return [$res_headers, $json];
}
