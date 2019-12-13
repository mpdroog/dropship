<?php
/*
 * Send through orders from Bol
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";
require __DIR__ . "/../core/bigbuy.php";

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$prod = $db->getRow("select weight, sku from prods where ean = ?", ["8719987151026"]);
if (! is_array($prod)) {
    user_error("No such prod by EAN " . "8719987151026");
}

list($res, $headers) = bol_http("GET", "/orders", []);
$res["orders"] = $res["orders"] ?? [];

foreach ($res["orders"] as $order) {
    if (VERBOSE) var_dump($order);
    /*if ("1" === $orderdb->getCell("select 1 from orders where bol_id = ?", [$order["orderId"]])) {
        if (VERBOSE) echo "Already processed " . $order["orderId"] . "\n";
        continue;
    }*/
    list($details, $header) = bol_http("GET", "/orders/" . $order["orderId"]);
    if (VERBOSE) var_dump($details);

    $ship = $details["customerDetails"]["shipmentDetails"];
    $ship["houseNumberExtended"] = $ship["houseNumberExtended"] ?? "";
    $prods = $details["orderItems"];
}

$p = ["reference" => $prod["sku"],
            "quantity" => 1];
//$ship = $db->getAll("select shipping_service_id, shipping_name from shipping where shipping_method='van' and weight_modulo >= ? order by cost", [$prod["weight"]]);
$ship = bb_json("/rest/shipping/orders.json", [
    "order" => [
        "delivery" => ["isoCountry" => "NL", "postcode" => "1703bt"],
	"products" => [$p]
    ]
]);
//var_dump($ship);
$carrier = strtolower($ship["shippingOptions"][0]["shippingService"]["name"]);
echo "carrier=$carrier\n";

$s = bb_json("/rest/order/create.json", [
    "order" => [
        "internalReference" => "123456",
	"language" => "nl",
	"paymentMethod" => "paypal",
	"carriers" => [["name" => $carrier]],
	"shippingAddress" => [
	    "firstName" => "Mark",
	    "lastName" => "Droog",
	    "country" => "NL",
	    "postcode" => "1703BT",
	    "town" => "Heerhugowaard",
	    "address" => "Citrien 8",
	    "phone" => "0613990998",
	    "email" => "rootdev@gmail.com",
	    "comment" => ""
        ],
        "products" => [
           $p 
	]
    ]
]);
var_dump($s);
