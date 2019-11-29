<?php
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/error.php";
require __DIR__ . "/../core/db.php";

const BIGBUY_URL = "https://api.bigbuy.eu";
const BIGBUY_KEY = "NjZlNTJjNTliODg2ODk5Y2JmZjk4OGI4M2Q1MTRhNjk1YWNhNzQxYmI1YjJlYmZmZTI0NTI4ZWNlMDY0NmU5MQ";

function bb_dl($url, $fd, array $args) {
    $ch = curl_init(BIGBUY_URL . $url . "?" . http_build_query($args));
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_FILE, $fd);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY)
    ]);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

    $res = curl_exec($ch);
    if ($res === false) {
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

if (! file_exists(__DIR__ . "/cache")) {
    if (! mkdir(__DIR__ . "/cache")) {
        user_error("mkdir cache fail");
    }
}

foreach ([
  __DIR__ . "/cache/bb_variantstock.json" => "/rest/catalog/productsvariationsstock.json",
  __DIR__ . "/cache/bb_carriers.json" => "/rest/shipping/carriers.json",
  __DIR__ . "/cache/bb_prodstock.json" => "/rest/catalog/productsstock.json",
  __DIR__ . "/cache/bb_prods.json" => "/rest/catalog/products.json",
  __DIR__ . "/cache/bb_cats.json" => "/rest/catalog/categories.json",
  __DIR__ . "/cache/bb_prodinfo.json" => "/rest/catalog/productsinformation.json",
  __DIR__ . "/cache/bb_prodsvariant.json" => "/rest/catalog/productsvariations.json",
  //__DIR__ . "/cache/bb_prodvariant.json" => "/rest/catalog/productsvariationsstock.json",
] as $fname => $path) {
  $fd = fopen($fname, "w");
  $res = bb_dl($path, $fd, ["isoCode" => "nl"]);
  fclose($fd);
  if ($res === true) {
    echo sprintf("Feed written to %s\n", $fname);
  }
}

die();
function bb_get($path, array $args) {
  $url = BIGBUY_URL . $path . ".json";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        sprintf('Authorization: Bearer %s', BIGBUY_KEY)
    ]);

        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $args);


    $res = curl_exec($ch);
    if ($res === false) {
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

/*var_dump(
bb_get("/rest/catalog/categories", [
  "isoCode" => "nl"
])
);*/

var_dump(bb_get("/rest/catalog/products.json", []));

