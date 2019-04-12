<?php
/*
 * Download offer export file (CSV) and save to bol_offers.csv
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

function bol_bearer() {
    $session = curl_init("https://login.bol.com/token?grant_type=client_credentials");
    curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($session, CURLOPT_USERPWD, API_CLIENTID . ':' . API_SECRET);
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($session);
    curl_close($session);
    $j = json_decode($res, true);
    if (! is_array($j)) {
        user_error("http_bearer: invalid res=$res");
    }
    if ($j["token_type"] !== "Bearer") {
        user_error("http_bearer: unsupported token type=" . $j["token_type"]);
    }
    if ($j["scope"] !== "RETAILER") {
        var_dump($j);
        user_error("http_bearer: account does not have retailer role but role=" . $j["scope"]);
    }

    $exp = $j["expires_in"];
    return [
        "expire" => strtotime("+$exp sec"),
        "bearer" => $j["access_token"]
    ];
}

function bol_http($method, $url, $d = []) {
    global $token;
    if (! is_array($token) || $token["expire"] < time()) {
        // Lazy auto-request new token
        $token = bol_bearer();
    }

    $bearer = $token["bearer"];
    $session = curl_init(API_URL . $url);
    $headers = ['Accept: application/vnd.retailer.v3+json', sprintf('Authorization: Bearer %s', $bearer)];
    if (count($d) > 0) {
        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($d));
        $headers[] = 'Content-Type: application/vnd.retailer.v3+json';
    }

    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($session);
    curl_close($session);
    $results = json_decode($response, true);
    return $results;
}
function bol_zip($method, $url, $fd) {
    global $token;
    if (! is_array($token) || $token["expire"] < time()) {
        // Lazy auto-request new token
        $token = bol_bearer();
    }

    $bearer = $token["bearer"];
    $ch = curl_init(API_URL . $url);
    $headers = ['Accept: application/vnd.retailer.v3+csv', sprintf('Authorization: Bearer %s', $bearer)];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

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

// https://api.bol.com/retailer/public/redoc/v3#operation/post-offer-export
// Not interesting? https://developers.bol.com/documentatie/ftp/content-ftps-handleiding/
$token = bol_bearer();

$url = null;
if ($arg_exportid !== null) {
    $exportid = $arg_exportid;
} else {
    $res = bol_http("POST", "/offers/export", ["format" => "CSV"]);
    var_dump($res);
    if ($res["status"] !== "PENDING") {
        var_dump($res);
        user_error("Failed sending request for export.");
    }

    $exportid = $res["id"];
}

// Wait for being processed
$url = "/process-status/$exportid";
$uuid = null;
while (true) {
    $res = bol_http("GET", $url, []);
    var_dump($res);
    if ($res["status"] === "SUCCESS") {
        $uuid = $res["entityId"];
        break;
    }
    if ($res["status"] !== "PENDING") {
        user_error("Unprocessible status=" . $res["status"]);
    }
    sleep(15); // 15sec delay
}

$fname = __DIR__ . "/bol_offers.csv";
$fd = fopen($fname, "w");
$res = bol_zip("GET", "/offers/export/$uuid", $fd);
var_dump($res);
fclose($fd);
if ($res === true) {
    echo sprintf("Written to %s\n", $fname);
}
