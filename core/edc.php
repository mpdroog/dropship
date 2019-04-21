<?php
const EDC_URL_PRODS = "http://api.edc.nl/b2b_feed.php?key=35t55w94ec2833998860r3e5626eet1c&sort=xml&type=zip&lang=nl&version=2015";
const EDC_URL_DISCOUNT = "https://www.erotischegroothandel.nl/download/discountoverview.csv?apikey=35t55w94ec2833998860r3e5626eet1c";
const EDC_URL_STOCK = "http://api.edc.nl/xml/eg_xml_feed_stock.xml";

function edc_zip($url, $fd) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FILE, $fd);

    $res_headers = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$res_headers) {
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

    $res = curl_exec($ch);
    if ($res === false) {
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return [$res_headers, $res];
}


