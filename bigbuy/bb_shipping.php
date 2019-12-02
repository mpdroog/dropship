<?php
/*
 * Collect sending price based on weight.
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
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY),
        'Content-Type: application/json',
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
        print_r($json);
        user_error("bb_json fail");
    }
    return $json;
}
function parseDate($term) {
  list($num, $dur) = explode(" ", $term);
  if (! in_array($dur, ["days", "h"])) {
    return false;
  }
  if (strpos($num, "-") !== false) {
    $num = substr($num, strpos($num, "-")+1);
  }
  if (! is_numeric($num)) {
    return false;
  }
  $m = ["days" => "days", "h" => "hours"];
  return "+$num " . $m[$dur];
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$shipping = $db->getAll("select shipping_service_id, weight_modulo from shipping");

$min = "0";
$max = "10";
$step = "0.5";

$weights = [];
$n = $min;
while (true) {
    $n = bcadd($n, $step, 1);
    $weights[] = $n;
    if ($n > $max) break;
}

$already = $db->getAllMap("key", "select (shipping_service_id || '-' || weight_modulo) as key, cost from shipping");
$db->exec("delete from shipestimate");
$db->exec("delete from shipping");

foreach ($weights as $weight) {
    echo $weight . "\n";
    $prod = $db->getRow("select name,weight,sku from prods where weight <= ? ORDER BY weight desc", [$weight]);
    if(VERBOSE) var_dump($prod);

    $s = bb_json("/rest/shipping/orders.json", [
  "order" => [
    "delivery" => [
      "isoCountry" => "NL",
      "postcode" => "1703bt"
    ],
    "products" => [[
      "reference" => $prod["sku"],
      "quantity" => 1
    ]]
  ]
]);

    foreach ($s["shippingOptions"] as $opt) {
	$key = sprintf("%s-%s", $opt["shippingService"]["id"], $weight);
	if (isset($already[$key])) {
            if ($already[$key]["cost"] !== strval($opt["cost"])) {
                die(sprintf("ERR: shippingService price diff? expect=%s found=%s\n", $already[$key]["cost"], $opt["cost"]));
	    }
	    // Already have price
	    continue;
	}
        $db->insert("shipping", [
            "shipping_service_id" => $opt["shippingService"]["id"],
	    "shipping_name" => $opt["shippingService"]["name"],
            "shipping_method" => $opt["shippingService"]["transportMethod"],
	    "delay" => parseDate($opt["shippingService"]["delay"]),
	    "cost" => $opt["cost"],
	    "weight_modulo" => $weight,
	    "weight_max" => $opt["weight"]
        ]);
	if(VERBOSE) echo sprintf(
	    "Weight(%s) has maxweight=%s method=%s delay=%s cost=%s\n",
	    $weight, $opt["weight"], $opt["shippingService"]["name"] . "-" . $opt["shippingService"]["transportMethod"], parseDate($opt["shippingService"]["delay"]), $opt["cost"]
	);
    }

    // Post-process, get the 2 cheapest and use the most expensive of those
    // so we can use 1 value after this for price calculations
    $prices = $db->getAll("select delay,cost from shipping where shipping_method = 'van' and weight_modulo = ? order by cost asc limit 2", [$weight]);
    if (count($prices) === 0) die("no van shipping method?");
    $cost = count($prices) === 2 ? $prices[1] : $prices[0];

    /*
     CREATE TABLE "shipestimate" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "weight_modulo" real NOT NULL,
  "delay" TEXT NOT NULL,
  "cost" real NOT NULL
);
     */
    $db->insert("shipestimate", [
	    "weight_modulo" => $weight,
	    "delay" => $cost["delay"],
	    "cost" => $cost["cost"]
    ]);

    // 1 call per 3sec
    sleep(3);
}

