<?php
/*
 * Send through orders from Bol
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";
require __DIR__ . "/../core/bigbuy.php";

// CACHE
$orderdb = new \core\Db(sprintf("sqlite:%s/orders.sqlite", ""), "", "");
$lines = explode(";", file_get_contents(__DIR__ . "/default_sync.sql"));
foreach ($lines as $line) {
    if (strlen(trim($line)) === 0) continue;
    //echo $line;
    $res = $orderdb->exec($line);
    //var_dump($res->errorInfo());
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
list($res, $headers) = bol_http("GET", "/orders", []);
$res["orders"] = $res["orders"] ?? [];

foreach ($res["orders"] as $order) {
    /**
  ["orderId"]=>
  string(10) "2772766290"
  ["dateTimeOrderPlaced"]=>
  string(25) "2019-12-21T18:33:34+01:00"
  ["orderItems"]=>
  array(1) {
    [0]=>
    array(4) {
      ["orderItemId"]=>
      string(10) "2352542986"
      ["ean"]=>
      string(13) "5025232878406"
      ["cancelRequest"]=>
      bool(false)
      ["quantity"]=>
      int(1)
    }
}*/
    if (VERBOSE) var_dump($order);

    if (count($order["orderItems"]) !== 1)  {
        user_error("Unsupported: Multiple product order.");
    }

    $prod = $db->getRow("select sku from prods where ean = ?", [$order["orderItems"][0]["ean"]]);
    if (! is_array($prod)) {
        echo sprintf("ERR: No such prod with EAN=%s\n", $order["orderItems"][0]["ean"]);
	exit;
    }

    if ("1" === $orderdb->getCell("select 1 from orders where bol_id = ?", [$order["orderId"]])) {
        if (VERBOSE) echo "Already processed " . $order["orderId"] . "\n";
        continue;
    }
    list($details, $header) = bol_http("GET", "/orders/" . $order["orderId"]);
    if (VERBOSE) var_dump($details);
  /*["orderId"]=>
  string(10) "2772766290"
  ["dateTimeOrderPlaced"]=>
  string(25) "2019-12-21T18:33:34+01:00"
  ["customerDetails"]=>
  array(2) {
    ["shipmentDetails"]=>
    array(10) {
      ["salutationCode"]=>
      string(2) "02"
      ["firstName"]=>
      string(3) "Ank"
      ["surName"]=>
      string(7) "Huisman"
      ["streetName"]=>
      string(10) "Kuypersweg"
      ["houseNumber"]=>
      string(1) "6"
      ["houseNumberExtended"]=>
      string(3) "-35"
      ["zipCode"]=>
      string(7) "6871 ED"
      ["city"]=>
      string(6) "RENKUM"
      ["countryCode"]=>
      string(2) "NL"
      ["email"]=>
      string(47) "2czdjmuaggrpo7oizhwwyc2ibtrgzo@verkopen.bol.com"
    }
    ["billingDetails"]=>
    array(10) {
      ["salutationCode"]=>
      string(2) "02"
      ["firstName"]=>
      string(3) "Ank"
      ["surName"]=>
      string(7) "Huisman"
      ["streetName"]=>
      string(10) "Kuypersweg"
      ["houseNumber"]=>
      string(1) "6"
      ["houseNumberExtended"]=>
      string(3) "-35"
      ["zipCode"]=>
      string(7) "6871 ED"
      ["city"]=>
      string(6) "RENKUM"
      ["countryCode"]=>
      string(2) "NL"
      ["email"]=>
      string(47) "2czdjmuaggrpo7oizhwwyc2ibtrgzo@verkopen.bol.com"
    }
  }
  ["orderItems"]=>
  array(1) {
    [0]=>
    array(13) {
      ["orderItemId"]=>
      string(10) "2352542986"
      ["offerReference"]=>
      string(8) "BB-65582"
      ["ean"]=>
      string(13) "5025232878406"
      ["title"]=>
      string(57) "Panasonic SC-HC200 Home audio micro system 20W Zwart, Wit"
      ["quantity"]=>
      int(1)
      ["offerPrice"]=>
      float(133.37)
      ["offerId"]=>
      string(36) "bb468f45-cb78-4d36-b68e-b13dfb57e3d5"
      ["transactionFee"]=>
      float(10.34)
      ["latestDeliveryDate"]=>
      string(10) "2020-01-07"
      ["expiryDate"]=>
      string(10) "2020-01-10"
      ["offerCondition"]=>
      string(3) "NEW"
      ["cancelRequest"]=>
      bool(false)
      ["fulfilmentMethod"]=>
      string(3) "FBR"
    }
  }*/
    $ship = $details["customerDetails"]["shipmentDetails"];
    $ship["houseNumberExtended"] = $ship["houseNumberExtended"] ?? "";
    $prods = $details["orderItems"];

    $p = ["reference" => $prod["sku"], "quantity" => 1];
    $carriers = bb_json("/rest/shipping/orders.json", [
    "order" => [
        "delivery" => ["isoCountry" => $ship["countryCode"], "postcode" => $ship["zipCode"]],
	"products" => [$p]
    ]
    ]);

    $carrier = strtolower($carriers["shippingOptions"][0]["shippingService"]["name"]);
    echo "carrier=$carrier\n";

    $txn = $orderdb->txn();
    $row_id = $orderdb->insert("orders", [
        "bol_id" => $order["orderId"],
        "edc_id" => null
    ]);

    $s = ["id" => null];
    if (WRITE) {
	    die("X");
    list($header, $s) = bb_json_header("/rest/order/create.json", [
    "order" => [
        "internalReference" => $order["orderId"],
	"language" => "nl",
	"paymentMethod" => "paypal",
	"carriers" => [["name" => $carrier]],
	"shippingAddress" => [
	    "firstName" => $ship["firstName"],
	    "lastName" => $ship["surName"],
	    "country" => $ship["countryCode"],
	    "postcode" => $ship["zipCode"],
	    "town" => $ship["city"],
	    "address" => $ship["houseNumber"] . ' ' . $ship["houseNumberExtended"],
	    "phone" => "0613990998",
	    "email" => $ship["email"],
	    "comment" => ""
        ],
        "products" => [
           $p 
	]
    ]
    ]);
    var_dump($s);
    var_dump($header);
    echo $header["Location"];
    $s["id"] = $header["Location"];
    }

    echo sprintf("Bol id=%s bigbuy=%s\n", $order["orderId"], $s["id"]);
    $orderdb->exec("update orders set edc_id=? where id = ?", [$s["id"], $row_id]);

    if (WRITE) {
        $txn->commit();
    }
}

