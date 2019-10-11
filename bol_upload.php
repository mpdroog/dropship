<?php
/*
 * Read SQLite and find everything we need to send.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/bol.php";
require __DIR__ . "/core/strings.php";

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

$now = time();
$del = 0;
// 0.Del prods in bol_del where tm_synced is null
foreach ($db->getAll("select * from bol_del where tm_synced is null") as $prod) {
    if ($prod["bol_id"] === null) {
        var_dump($prod);
        user_error("bol_id is null.");
    }
    list($res, $head) = bol_http("DELETE", "/offers/".$prod["bol_id"], []);
    if (! in_array($res["status"], ["PENDING", "SUCCESS"])) {
        var_dump($res);
        if (isset($res["violations"]) && $res["violations"][0]["name"] === "offer-id") {
            echo "WARN: Skip invalid offerid.\n";
        } else {
            user_error("DELETE offer err.");
        }
    }
    $db->exec("UPDATE `bol_del` SET `tm_synced` = ? WHERE `bol_id` = ?", [$now, $prod["bol_id"]]);
    //$db->exec("DELETE from prods where ean = ?", [$prod["ean"]]);
    if (VERBOSE) echo sprintf("bol_del %s\n", $prod["bol_id"]);
    $del++;
    ratelimit($head);
}

$added = 0;
// 1.Sync prods not in Bol
foreach ($db->getAll("select id, ean, title, calc_price_bol, price_me, price, stock from prods where bol_id is null and bol_pending is null and bol_error is null") as $prod) {
    if (in_array($prod["stock"], [null])) die("ERR: Stock amount empty!");
    if (intval($prod["stock"]) >= 1000) $prod["stock"] = 999;
    $price = $prod["calc_price_bol"];
    if (VERBOSE) echo sprintf("bol_add ean=%s stock=%s\n", $prod["ean"], $prod["stock"]);

    list($res, $head) = bol_http("POST", "/offers", [
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
        $db->exec("update prods set bol_error=? where id=?", [time(), $prod["id"]]);
        var_dump($res);
        ratelimit($head);
        continue;
    }
    $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
    }
    $added++;
    ratelimit($head);
}

// 2.Sync prods that have a different stock amount or price compared to bol
// TODO: Horrible complex SQL-logic

$prods = $db->getAll("select calc_price_bol, bol_id, id, ean, title, price, stock, bol_stock, bol_price from prods where bol_id is not null");
$update = 0;
foreach ($prods as $prod) {
    $bol_id = $prod["bol_id"];

    // make them comparable
    if (intval($prod["stock"]) >= 1000) $prod["stock"] = 999;
    //$prod["bol_stock"] = intval($prod["bol_stock"])-5;
    $prod["stock"] = bcsub($prod["stock"], "5", 0);
    if ($prod["stock"] < 0) $prod["stock"] = "0";
    if ($prod["bol_stock"] < 0) $prod["bol_stock"] = "0";

    if ($prod["bol_stock"] !== $prod["stock"]) {
        if (VERBOSE) echo sprintf("bol.stock_update %s %s=>%s\n", $prod["ean"], $prod["bol_stock"], $prod["stock"]);
        list($res, $head) = bol_http("PUT", "/offers/$bol_id/stock", [
            "amount" => $prod["stock"],
            "managedByRetailer" => true
        ]);
        if ($head["status"] !== 202) {
            $db->exec("update prods set bol_error=? where id=?", [time(), $prod["id"]]);
            var_dump($res);
            ratelimit($head);
            continue;
        }
        ratelimit($head);
        $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
        if ($stmt->rowCount() !== 1) {
            user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
        }
        $update++;
    } else {
        if (VERBOSE) echo sprintf("bol.stock same %s %s=>%s\n", $prod["ean"], $prod["bol_stock"], $prod["stock"]);
    }

    if ($prod["bol_price"] !== $prod["calc_price_bol"]) {
        $bundle = [[
            "quantity" => 1,
            "price" => $prod["calc_price_bol"]
        ]];
        if (VERBOSE) echo sprintf("bol.price_update %s %s=>%s\n", $prod["ean"], $prod["bol_price"], $prod["calc_price_bol"]);
        list($res, $head) = bol_http("PUT", "/offers/$bol_id/price", [
            "pricing" => ["bundlePrices" => $bundle]
        ]);
        if ($head["status"] !== 202) {
            $db->exec("update prods set bol_error=? where id=?", [time(), $prod["id"]]);
            var_dump($res);
            continue;
        }
        ratelimit($head);
        $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
        if ($stmt->rowCount() !== 1) {
            user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
        }
        $update++;
    }
}

if (VERBOSE) {
    echo "del=$del\n";
    echo "added=$added\n";
    echo "update=$update\n";
}
