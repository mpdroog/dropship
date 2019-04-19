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
    $code = curl_getinfo($session, CURLINFO_HTTP_CODE);
    $res_headers["status"] = $code;
    curl_close($session);
    $results = json_decode($response, true);
    if ($code === "429") {
        user_error("Error, ratelimit.");
    }
    return [$results, $res_headers];
}
function ratelimit(array $head) {
    if (! isset($head["x-ratelimit-remaining"])) {
        return;
    }
    if ($head["x-ratelimit-remaining"][0] === "1") {
        $retry = intval($head["x-ratelimit-reset"][0]) - time();
        if ($retry < 10) {
            $retry = 10;
        }
        echo sprintf("[ratelimit] sleep %d sec..", $retry);
        sleep($retry);
    }
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

$now = time();
$del = 0;
// 0.Del prods in bol_del where tm_synced is null
foreach ($db->getAll("select bol_id from bol_del where tm_synced is null") as $prod) {
    list($res, $head) = bol_http("DELETE", "/offers/".$prod["bol_id"], []);
    if (! in_array($res["status"], ["PENDING", "SUCCESS"])) {
        var_dump($res);
        user_error("DELETE offer err.");
    }
    $db->exec("UPDATE `bol_del` SET `tm_synced` = ? WHERE `bol_id` = ?", [$now, $prod["bol_id"]]);
    echo sprintf("bol_del %s\n", $prod["bol_id"]);
    $del++;
    ratelimit($head);
}

$added = 0;
// 1.Sync prods not in Bol
/*foreach ($db->getAll("select id, ean, title, calc_price_bol, price_me, price, stock from prods where bol_id is null and bol_pending is null") as $prod) {
    $price = $prod["calc_price_bol"];
    list($res, $head) = bol_http("POST", "/offers/", [
        "ean" => $prod["ean"],
        "condition" => [
            "name" => "NEW"
        ],
        "referenceCode" => $prod["id"],
        "onHoldByRetailer" => false,
        "unknownProductTitle" => $prod["title"],
        "pricing" => [
            "bundlePrices" => [[
                "quantity" => "1",
                "price" => $price
            ]]
        ],
        "stock" => [
            "amount" => $prod["stock"],
            "managedByRetailer" => true
        ],
        "fulfilment" => [
            "type" => "FBR",
            "deliveryCode" => "24uurs-20" // EDC=24uurs-23 but this change gives more space?
        ]
    ]);
    if ($head["status"] !== 202) {
        var_dump($res);
        ratelimit($head);
        continue;
    }
    $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
    }
    echo sprintf("bol_add %s\n", $prod["ean"]);
    $added++;
    ratelimit($head);
}*/

// 2.Sync prods that have a different stock amount or price compared to bol
$update = 0;
foreach ($db->getAll("select bol_id, id, ean, title, price, stock from prods where bol_id is not null and (bol_stock != stock)") as $prod) {
    $bol_id = $prod["bol_id"];
    if (intval($prod["stock"]) >= 1000) {
        $prod["stock"] = "999"; // limit to 999
    }
    // Always lower stock by 5 so we are on the save side
    $prod["stock"] = bcsub($prod["stock"], "5", 0);
    if ($prod["stock"] < 0) $prod["stock"] = "0";

    list($res, $head) = bol_http("PUT", "/offers/$bol_id/stock", [
        "amount" => $prod["stock"],
        "managedByRetailer" => true
    ]);
    if ($head["status"] !== 202) {
        var_dump($res);
        continue;
    }
    $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
    }
    echo sprintf("bol_update %s\n", $prod["ean"]);
    $update++;
    ratelimit($head);
}
foreach ($db->getAll("select bol_id, id, ean, title, price, stock from prods where bol_id is not null and (bol_price != calc_price_bol)") as $prod) {
    $bol_id = $prod["bol_id"];

    $bundle = [[
        "quantity" => 1,
        "price" => $prod["calc_price_bol"]
    ]];
    list($res, $head) = bol_http("PUT", "/offers/$bol_id/price", [
        "pricing" => ["bundlePrices" => $bundle]
    ]);
    if ($head["status"] !== 202) {
        var_dump($res);
        continue;
    }
    $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
    }
    echo sprintf("bol_update %s\n", $prod["ean"]);
    $update++;
    ratelimit($head);
}

echo "del=$del\n";
echo "added=$added\n";
echo "update=$update\n";

