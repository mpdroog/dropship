<?php
/*
 * Remove all products from Bol
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";
die("Disabled by default.");

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

$now = time();
$del = 0;
foreach ($db->getAll("select ean, bol_id from prods where bol_id is not null") as $prod) {
    if (VERBOSE) echo sprintf("bol del %s %s\n", $prod["ean"], $prod["bol_id"]);
    list($res, $head) = bol_http("DELETE", "/offers/".$prod["bol_id"], []);
    if (! in_array($res["status"], ["PENDING", "SUCCESS"])) {
        var_dump($res);
        if (isset($res["violations"]) && $res["violations"][0]["name"] === "offer-id") {
            echo "WARN: Skip invalid offerid.\n";
        } else {
            user_error("DELETE offer err.");
        }
    }
    $db->exec("UPDATE `prods` SET bol_id=null,bol_pending=null WHERE `bol_id` = ?", [$prod["bol_id"]]);
    $del++;
    ratelimit($head);
}
var_dump($del);
