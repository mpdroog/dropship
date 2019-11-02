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

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

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

foreach ($weights as $weight) {
    echo $weight . "\n";
    $prod = $db->getRow("select name,weight,sku from prods where weight >= ? and weight <= ?", [$weight, bcadd($weight, $step, 1)]);
    var_dump($prod);

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
    var_dump($s);

    // 1 call per 3sec
    sleep(3);

}
