<?php
/*
 * Read SQLite and find everything we need to send.
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";

//define("CACHE_BB", __DIR__ . "/cache");
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
if (! WRITE) echo "readonly-mode\n";

$now = time();
$del = 0;
// 0.Del prods in bol_del where tm_synced is null
foreach ($db->getAll("select * from bol_del where tm_synced is null") as $prod) {
    if ($prod["bol_id"] === null) {
        var_dump($prod);
        user_error("bol_id is null.");
    }
    if (WRITE) {
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
    }
    //$db->exec("DELETE from prods where ean = ?", [$prod["ean"]]);
    if (VERBOSE) echo sprintf("bol_del %s\n", $prod["bol_id"]);
    $del++;
    ratelimit($head);
}

function recurGroups($id) {
    global $db;
    $out = [];
    $ignores = $db->getCol("select id from cats where parentCategory = ?", [$id]);
    foreach ($ignores as $ignore) {
        $out[] = $ignore;
        $out = array_merge($out, recurGroups($ignore));
    }
    return $out;
}

$ignore = [];
$defs = [
    "2403", // Eten/Drinken | Keuken
    "2570", // Mode | Accessoires
    "2491", // Sport | Vrije Tijd
    "2988", // Gaming Laptops
    "2993", // Gaming PC
];
foreach ($defs as $def) {
    $ignore = array_merge($ignore, recurGroups($def));
}
$ignore = implode(",", $ignore);

//$sql = "select ean from prods_variants v join prods p on v.product_id = p.id where weight < 10 and category not in ($ignore)";
$added = 0;
// 1.Sync prods not in Bol
foreach ($db->getAll("select id, ean, \"name\", MAX(calc_price_bol) as calc_price_bol, SUM(stock) as stock from prods where bol_id is null and bol_pending is null and bol_error is null and category not in ($ignore) group by ean") as $prod) {
    if (in_array($prod["stock"], [null])) die("ERR: Stock amount empty!");
    if (intval($prod["stock"]) >= 1000) $prod["stock"] = 999;
    $price = $prod["calc_price_bol"];
    $qty = "0";
    if ($prod["stock"] >= 10) {
        $qty = "1";
    }
    if (VERBOSE) echo sprintf("bol_add ean=%s stock=%s ", $prod["ean"], $qty);
    if ($price === null) {
            if (VERBOSE) echo " skip (missing price)\n";
	    continue; // skip
    }
    if ($qty === "0") {
        if (VERBOSE) echo " qty=0 skip\n";
        continue;
    }
    if ($price > 200) {
        if (VERBOSE) echo " price>200eur skip\n";
        continue;
    }

    if (WRITE) {
    list($res, $head) = bol_http("POST", "/offers", [
        "ean" => $prod["ean"],
        "condition" => [
            "name" => "NEW"
        ],
        "referenceCode" => "BB-" . $prod["id"],
        "onHoldByRetailer" => false,
        "unknownProductTitle" => $prod["name"],
        "pricing" => [
            "bundlePrices" => [[
                "quantity" => $qty,
                "price" => $price
            ]]
        ],
        "stock" => [
            "amount" => $prod["stock"],
            "managedByRetailer" => true
        ],
        "fulfilment" => [
            "type" => "FBR",
            "deliveryCode" => "4-8d"
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
    ratelimit($head);
    }
    if (VERBOSE) echo "\n";
    $added++;
}

// 2.Sync prods that have a different stock amount or price compared to bol
$prods = $db->getAll("select id, MAX(calc_price_bol) as calc_price_bol, SUM(stock) as stock, bol_id, id, ean, 'name' as title, bol_stock, bol_price from prods where bol_id is not null and bol_error is null group by ean");
$update = 0;
foreach ($prods as $prod) {
    if (trim($prod["ean"]) === "") {
        echo sprintf("missing ean for id=%s bol_id=%s\n", $prod["id"], $prod["bol_id"]);
        continue;
    }
    $bol_id = $prod["bol_id"];

    $qty = "0";
    if ($prod["stock"] >= 10) {
        $qty = "1";
    }
    if ($prod["bol_stock"] < 0) $prod["bol_stock"] = "0";

    if (FORCE || $prod["bol_stock"] !== $qty) {
        if (VERBOSE) echo sprintf("bol.stock_update %s %s=>%s\n", $prod["ean"], $prod["bol_stock"], $qty);
        if (WRITE) {
	list($res, $head) = bol_http("PUT", "/offers/$bol_id/stock", [
            "amount" => $qty,
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
	}
        $update++;
    } else {
        if (VERBOSE) echo sprintf("bol.stock same %s %s=>%s\n", $prod["ean"], $prod["bol_stock"], $prod["stock"]);
    }

    if ($qty !== "0") {
        // Only update price when quantity is more than 0 (else Bol will just error)
        if (FORCE || $prod["bol_price"] !== $prod["calc_price_bol"]) {
            $bundle = [[
                "quantity" => $qty,
                "price" => $prod["calc_price_bol"]
            ]];
            if (VERBOSE) echo sprintf("bol.price_update %s %s=>%s\n", $prod["ean"], $prod["bol_price"], $prod["calc_price_bol"]);
	    if (WRITE) {
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
	    }
            $update++;
        }
    }
}

if (VERBOSE) {
    if (! WRITE) echo "readonly-mode\n";
    echo "del=$del\n";
    echo "added=$added\n";
    echo "update=$update\n";
}
