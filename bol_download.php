<?php
/*
 * Download offer export file (CSV) and save to bol_offers.csv
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/bol.php";

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

$res = null;
if ($arg_exportid !== null) {
    $exportid = $arg_exportid;
} else {
    list($res, $head) = bol_http("POST", "/offers/export", ["format" => "CSV"]);
    if (! isset($res["id"])) {
        var_dump($res);
        user_error("offers/export invalid res.");
    }
    $exportid = $res["id"];
}

// Wait for being processed
$url = "/process-status/$exportid";
$uuid = null;
while (true) {
    if ($res === null) {
        list($res, $head) = bol_http("GET", $url, []);
    }
    if (VERBOSE) echo sprintf("id=%s status=%s\n", $exportid, $res["status"]);
    if ($res["status"] === "SUCCESS") {
        $uuid = $res["entityId"];
        break;
    }
    if ($res["status"] !== "PENDING") {
        var_dump($res);
        user_error("Unprocessible status=" . $res["status"]);
    }

    if (VERBOSE) echo "sleep 15sec\n";
    sleep(15); // 15sec delay
    $res = null; // force next get
}

$fname = CACHE . "/bol_offers.csv";
$fd = fopen($fname, "w");
$res = bol_zip("GET", API_URL."/offers/export/$uuid", $fd);
if (VERBOSE) var_dump($res);
fclose($fd);
if ($res !== true) {
    var_dump($res);
    user_error("Failed downloading offer.");
}
if (VERBOSE) echo sprintf("Written to %s\n", $fname);

