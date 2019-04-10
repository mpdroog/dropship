<?php
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
const CSV_HEAD = "ca6be752df02d19db9cc0556461beaf3";
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

// Convert current sqlite-db to hashmap for rapid lookups
// TODO: Something more memory friendly?
$lookup = [];
foreach ($db->getAll("select ean, bol_id, bol_updated from prods") as $prod) {
    $lookup[ $prod["ean"] ] = $prod;
}

$lines = explode("\n", file_get_contents(__DIR__ . "/bol_offers.csv"));
// offerId,ean,conditionName,conditionCategory,conditionComment,price,fulfilmentDeliveryCode,retailerStock,onHoldByRetailer,fulfilmentType,mutationDateTime
// 819fe9b3-7e82-4d13-e053-828b620ae101,4024144270354,NEW,NEW,,37.00,24uurs-22,12,false,FBR,2019-03-14 10:41:25.596 UTC
$head = array_shift($lines);
if (CSV_HEAD !== md5($head)) {
    exit("ERR: CSV-header mismatching with dev header, head=$head\n");
}

$nomatch = 0;
$mismatch = 0;
$nochange = 0;
echo $head;
foreach ($lines as $line) {
    if (trim($line) === "") continue;
    $tok = explode(",", $line);
    var_dump($tok);

    $offerid = $tok[0];
    $ean = $tok[1];
    $updated = tok[10];

    if (! isset($lookup[ $ean ])) {
        $nomatch++;
        echo sprintf("WARN: EAN(%s) not found in local sqlite\n", $ean);
        continue;
    }

    $l = $lookup[$ean];
    if ($l["bol_id"] !== null && $l["bol_id"] !== $offerid) {
        $mismatch++;
        echo sprintf("WARN: EAN(%s) has different bol_id in DB compared to bol CSV.\n", $ean);
        continue;
    }
    if ($l["bol_updated"] !== null && $l["bol_updated"] === $updated) {
        $nochange++;
        echo sprintf("EAN(%s) no change.\n", $ean);
        continue;
    }

    $stmt = $db->exec("update prods set bol_id=?, bol_updated=? where ean=?", [$offerid, $updated, $ean]);
    if ($stmt->affectedCount !== 1) {
        user_error("ERR: Failed updating DB with ean=$ean");
    }
}
