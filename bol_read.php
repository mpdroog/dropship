<?php
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/filter.php";
require __DIR__ . "/core/strings.php";
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

// Convert current sqlite-db to hashmap for rapid lookups
// TODO: Something more memory friendly?
$lookup = [];
foreach ($db->getAll("select ean, bol_id, bol_updated, stock from prods") as $prod) {
    if (filter_ean($prod["ean"])) {
        if (VERBOSE) echo sprintf("filter_ean(%s)\n", $prod["ean"]);
        continue;
    }
    if (isset($lookup[ $prod["ean"] ])) {
        user_error(sprintf("assumption fail. double EAN(%s)", $prod["ean"]));
    }
    $lookup[ $prod["ean"] ] = $prod;
}

$dels = [];
foreach ($db->getAll("select bol_id, tm_synced from bol_del") as $prod) {
    $dels[ $prod["bol_id"] ] = ["tm_synced" => $prod["tm_synced"]];
}

// TODO: Abusing memory here..
$lines = explode("\n", file_get_contents(CACHE . "/bol_offers.csv"));
$head = array_shift($lines);

$pos_offer = null;
$pos_ean = null;
$pos_updated = null;
$pos_stock = null;
$pos_price = null;
$pos_ref = null;
foreach (explode(",", $head) as $i => $name) {
  if ($name === "offerId") $pos_offer = $i;
  if ($name === "ean") $pos_ean = $i;
  if ($name === "mutationDateTime") $pos_updated = $i;
  if ($name === "stockAmount") $pos_stock = $i;
  if ($name === "bundlePricesPrice") $pos_price = $i;
  if ($name === "referenceCode") $pos_ref = $i;
}
// var_dump($pos_offer, $pos_ean, $pos_updated, $pos_stock, $pos_price);
if ($pos_offer === null || $pos_ean === null || $pos_updated === null || $pos_stock === null || $pos_price === null) {
  echo "ERR: One of pos-args from CSV top missing.";
  exit(1);
}

$nomatch = 0;
$mismatch = 0;
$nochange = 0;
$update = 0;

$now = time();
$txn = $db->txn();
foreach ($lines as $line) {
    if (trim($line) === "") continue;
    $tok = explode(",", $line);

    $offerid = $tok[$pos_offer];
    $ean = $tok[$pos_ean];
    $updated = $tok[$pos_updated];
    $stock = $tok[$pos_stock];
    $price = $tok[$pos_price];
    $ref = $tok[$pos_ref];

    if (! isset($lookup[ $ean ])) {
        // Ignore BigBuy prefixed prods
        if (core\Strings::has_prefix($ref, "BB-")) continue;
        $nomatch++;
        echo sprintf("WARN: EAN(%s) not found in local sqlite", $ean);
        if (isset($dels[$offerid])) {
            if ($dels[$offerid]["tm_synced"] !== null) {
                echo " already sent delete-signal.\n";
                continue;
            }
            echo sprintf(" offerid(%s) already stored (bol_upload not working?).\n", $offerid);
            continue; // already set to del in future
        }
        $id = $db->insert("bol_del", [
            "bol_id" => $offerid,
            "tm_added" => $now,
            "tm_synced" => null
        ], [
            "tm_added" => time()
        ]);
        echo sprintf(" added to bol_del(id=%s).\n", $id);
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
        if(VERBOSE) echo sprintf("EAN(%s) no change.\n", $ean);
        continue;
    }

    $pending = 0;
    if ($l["stock"] != $stock) {
        $pending = null; // force sync for new stock amount
    }

    $stmt = $db->exec("update prods set bol_id=?, bol_updated=?, bol_stock=?, bol_pending=?, bol_price=? where ean=?", [$offerid, $updated, $stock, $pending, $price, $ean]);
    if ($stmt->rowCount() !== 1) {
        user_error("ERR: Failed updating DB with ean=$ean");
    }
    $update++;
}
$txn->commit();
$db->close();

if (VERBOSE) {
    print "nomatch=$nomatch\n";
    print "mismatch=$mismatch\n";
    print "Update=$update\n";
    print "Nochange=$nochange\n";
    print "memory_get_usage() =" . memory_get_usage()/1024 . "kb\n";
    print "memory_get_usage(true) =" . memory_get_usage(true)/1024 . "kb\n";
    print "memory_get_peak_usage() =" . memory_get_peak_usage()/1024 . "kb\n";
    print "memory_get_peak_usage(true) =" . memory_get_peak_usage(true)/1024 . "kb\n";
}
