<?php
/*
 * Send through orders from Bol
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";

const BIGBUY_URL = "https://api.bigbuy.eu";
const BIGBUY_KEY = "NjZlNTJjNTliODg2ODk5Y2JmZjk4OGI4M2Q1MTRhNjk1YWNhNzQxYmI1YjJlYmZmZTI0NTI4ZWNlMDY0NmU5MQ";

function bb_json($url, array $args) {
    $ch = curl_init(BIGBUY_URL . $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    //curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY),
	'Content-Type: application/json',
	"Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

    $res = curl_exec($ch);
    if ($res === false) {
        var_dump($res);
        user_error(curl_error($ch));
    }
    curl_close($ch);

    if ($res === false) {
        user_error('curl_exec fail');
    }

    $json = json_decode($res, true);
    if (! is_array($json)) {
        print_r($res);
        user_error("bb_json fail");
    }
    return $json;
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$prod = $db->getRow("select weight, sku from prods where ean = ?", ["8719987151026"]);
if (! is_array($prod)) {
    user_error("No such prod by EAN " . "8719987151026");
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
