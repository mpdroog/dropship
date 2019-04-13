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

$fname = __DIR__ . "/edc_stock.xml";
$fd = fopen($fname, "w");
list($headers, $res) = edc_zip($fd);
fclose($fd);

$lm = strtotime($headers["last-modified"][0]);
if (! touch($fname, $lm, $lm)) {
    user_error(sprintf("touch($fname)=%s failed", $lm));
}
if ($res === true) {
    echo sprintf("Written to %s\n", $fname);
}
