<?php
/*
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/bol.php";

$arg_ean = null;
if (count($_SERVER["argv"]) === 2) {
    $arg_ean = $_SERVER["argv"][1];
    if (! is_numeric($arg_ean)) {
        exit("ERR: argument should be exportid");
    }
}
if (count($_SERVER["argv"]) > 2) {
    exit("ERR: argument should be ean");
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

$now = time();
$del = 0;

$added = 0;
// Push everything we can
$update = 0;
foreach ($db->getAll("select bol_id, id, ean, title, calc_price_bol, stock from prods where bol_id is not null", []) as $prod) {
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
    ratelimit($head);
    if ($head["status"] !== 202) {
        var_dump($res);
        continue;
    }

    $bundle = [[
        "quantity" => 1,
        "price" => $prod["calc_price_bol"]
    ]];
    list($res, $head) = bol_http("PUT", "/offers/$bol_id/price", [
        "pricing" => ["bundlePrices" => $bundle]
    ]);

    $stmt = $db->exec("update prods set bol_pending=? where id=?", [$res["id"], $prod["id"]]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=" . $prod["ean"]);
    }
    echo sprintf("bol_update %s price=%s\n", $prod["ean"], $prod["calc_price_bol"]);
    $update++;
    ratelimit($head);
}

echo "del=$del\n";
echo "added=$added\n";
echo "update=$update\n";

