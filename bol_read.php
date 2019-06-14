<?php
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/error.php";
const CSV_HEAD = "2da3306a0eb73cdf28087d3b2ef17dd8";
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

// Convert current sqlite-db to hashmap for rapid lookups
// TODO: Something more memory friendly?
$lookup = [];
foreach ($db->getAll("select ean, bol_id, bol_updated, stock from prods") as $prod) {
    if (isset($lookup[ $prod["ean"] ])) {
        user_error("assumption fail. double EAN");
    }
    $lookup[ $prod["ean"] ] = $prod;
}

$dels = [];
foreach ($db->getAll("select bol_id from bol_del") as $prod) {
    $dels[ $prod["bol_id"] ] = true;
}

// TODO: Abusing memory here..
$lines = explode("\n", file_get_contents(__DIR__ . "/bol_offers.csv"));
$head = array_shift($lines);
if (CSV_HEAD !== md5($head)) {
    echo "md5.old=" . CSV_HEAD;
    echo "md5.new=" . md5($head);
    exit("ERR: CSV-header mismatching with dev header, head=$head\n");
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

    $offerid = $tok[0];
    $ean = $tok[1];
    $updated = $tok[10];
    $stock = $tok[7];
    $price = $tok[5];

    if (! isset($lookup[ $ean ])) {
        $nomatch++;
        echo sprintf("WARN: EAN(%s) not found in local sqlite", $ean);
        if (isset($dels[$offerid])) {
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
