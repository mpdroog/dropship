<?php
/*
 * Collect sending price based on weight.
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/../core/bol.php";
require __DIR__ . "/../core/bigbuy.php";

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
$max = "13";
$step = "0.5";

$weights = [];
$n = $min;
while (true) {
    $n = bcadd($n, $step, 1);
    $weights[] = $n;
    if ($n > $max) break;
}

$txn = $db->txn();
$already = $db->getAllMap("key", "select (shipping_service_id || '-' || weight_modulo) as key, cost from shipping");
$db->exec("delete from shipestimate");
$db->exec("delete from shipping");

foreach ($weights as $weight) {
    if(VERBOSE) echo $weight . "\n";
    $prod = $db->getRow("select id,name,weight,sku,ean from prods where weight <= ? and stock > 5 and bol_error is null ORDER BY weight desc", [$weight]);
    if(VERBOSE) var_dump($prod);
    $ean = $prod["ean"];

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
        $db->insert("shipping", [
            "shipping_service_id" => $opt["shippingService"]["id"],
	    "shipping_name" => $opt["shippingService"]["name"],
            "shipping_method" => strtolower($opt["shippingService"]["transportMethod"]),
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
    if (count($prices) === 0) {
        $db->exec("update prods set bol_error=? where id=?", [time(), $prod["id"]]);    
	die("no van shipping method for EAN=$ean");
    }
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
$txn->commit();

