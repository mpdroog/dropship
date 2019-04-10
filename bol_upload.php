<?php
/*
 * Read SQLite and find everything we need to send.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
const API_URL = "https://api.bol.com/retailer";
const API_CLIENTID = "8338f293-a8a0-4d6c-b660-7d77d76002cb";
const API_SECRET = "aMPKgg6tsz_5fvRQbNweO4ejCaSdOI_cVb698D5YwfMy1GeAvm94YGeAD1JRjmI_eGKk0s2bRXc59NECLcrKSw";
const API_USER = "sync";

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
    return $j["access_token"];
}

function bol_http($method, $url, $bearer, $d = []) {
    $session = curl_init(API_URL . $url);
    $headers = ['Accept: application/vnd.retailer.v3+json', sprintf('Authorization: Bearer %s', $bearer)];
    if (count($d) > 0) {
        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($d));
        $headers[] = 'Content-Type: application/vnd.retailer.v3+json';
    }

    $res_headers = [];
    curl_setopt($session, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$res_headers) {
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


    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($session);
    curl_close($session);
    $results = json_decode($response, true);
    return [$results, $res_headers];
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$token = bol_bearer();

// TODO: Send everything that changed locally compared to remote?
$now = time();

// 0.Del  prods in bol_del where tm_synced is null
foreach ($db->getAll("select bol_id from bol_del where tm_synced is null") as $prod) {
    list($res, $head) = bol_http("DELETE", "/offers/".$prod["bol_id"], $token, []);
    if (! in_array($res["status"], ["PENDING", "SUCCESS"])) {
        var_dump($res);
        user_error("DELETE offer err.");
    }
    $db->exec("UPDATE `bol_del` SET `tm_synced` = ? WHERE `bol_id` = ?", [$now, $prod["bol_id"]]);
    echo sprintf("bol_del %s\n", $prod["bol_id"]);

    if (! isset($head["x-ratelimit-remaining"])) {
        var_dump($head);
        var_dump($res);
        user_error("Unexpected response.");
    }
    if ($head["x-ratelimit-remaining"][0] === "1") {
        $retry = intval($head["x-ratelimit-reset"][0]) - time();
        echo sprintf("[ratelimit] sleep %d sec..", $retry);
        sleep($retry);
    }
}

// 1.Sync prods not in Bol
// 2.Sync prods newer than lastrun.syncdate
// 3.Save lastrun.syncdate

