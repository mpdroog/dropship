<?php
/*
 * Sync order from Bol to EDC
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/bol.php";
require __DIR__ . "/core/edc.php";

// Package
const PACKING_ID = 3880;
$db = new \core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$orderdb = new \core\Db(sprintf("sqlite:%s/orders.sqlite", __DIR__), "", "");
$countries = [
    "NL" => "1",
    "BE" => "2"
];

$lines = explode(";", file_get_contents(__DIR__ . "/default_sync.sql"));
foreach ($lines as $line) {
    if (strlen(trim($line)) === 0) continue;
    //echo $line;
    $res = $orderdb->exec($line);
    //var_dump($res->errorInfo());
}

list($res, $headers) = bol_http("GET", "/orders", []);
$res["orders"] = $res["orders"] ?? [];

foreach ($res["orders"] as $order) {
    if (VERBOSE) var_dump($order);
    if ("1" === $orderdb->getCell("select 1 from orders where bol_id = ?", [$order["orderId"]])) {
        if (VERBOSE) echo "Already processed " . $order["orderId"] . "\n";
        continue;
    }
    list($details, $header) = bol_http("GET", "/orders/" . $order["orderId"]);
    if (VERBOSE) var_dump($details);

    $ship = $details["customerDetails"]["shipmentDetails"];
    $ship["houseNumberExtended"] = $ship["houseNumberExtended"] ?? "";
    $prods = $details["orderItems"];

    $xprods = [];
    foreach ($prods as $prod) {
        $artnr = $db->getCell("select edc_artnum from prods where ean=?", [$prod["ean"]]);
        if (strlen($artnr) === 0) {
            user_error("failed resolving artnr for ean " . $prod["ean"]);
        }
        $xprods[] = "<artnr>$artnr</artnr>";
    }
    $xprods = implode("\n", $xprods);

    $txn = $orderdb->txn();
    $row_id = $orderdb->insert("orders", [
        "bol_id" => $order["orderId"],
        "edc_id" => null
    ]);
    $xml = '<?xml version="1.0"?>
    <orderdetails>
    <customerdetails>
	<email>rootdev@gmail.com</email>
	<apikey>35t55w94ec2833998860r3e5626eet1c</apikey>
	<output>advanced</output>
    </customerdetails>
    <receiver>
	<name>' . $ship["firstName"] . ' ' . $ship["surName"]  . '</name>
        <extra_email>' . $ship["email"] .'</extra_email>
	<street>' . $ship["streetName"] . '</street>
	<house_nr>' . $ship["houseNumber"] . '</house_nr>
	<house_nr_ext>' . $ship["houseNumberExtended"] . '</house_nr_ext>
	<postalcode>' . $ship["zipCode"] . '</postalcode>
	<city>' . $ship["city"] . '</city>
	<country>' . $countries[ $ship["countryCode"] ] . '</country>
        <packing_slip_id>' . PACKING_ID . '</packing_slip_id>
	<own_ordernumber>' . $order["orderId"] . '</own_ordernumber>
    </receiver>
    <products>' . $xprods . '</products>
    </orderdetails>';
    if (VERBOSE) var_dump($xml);

    $id = "TEST001";
    if (WRITE) {
        $id = edc_order($xml);
    }
    echo sprintf("Bol id=%s edc=%s\n", $order["orderId"], $id);
    $orderdb->exec("update orders set edc_id=? where id = ?", [$id, $row_id]);

    if (WRITE) {
        $txn->commit();
    }
}
