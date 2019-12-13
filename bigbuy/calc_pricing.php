<?php
/*
 * Calculate prices for all products.
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/db.php";
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$shipping = $db->getAllMap("weight_modulo", "select cost, weight_modulo from shipestimate");
if (VERBOSE) var_dump($shipping);

function ceiling($number, $significance = 1) {
    return ( is_numeric($number) && is_numeric($significance) ) ? (ceil($number/$significance)*$significance) : false;
}

foreach ($db->getAll("select ean, id, name, wholesalePrice, calc_price_bol, weight from prods") as $line) {
	$mod = strval(ceiling($line["weight"], 0.5));
	$mod = number_format($mod, 1, ".", "");
	if ($mod === "0.0") $mod = "0.5";

	if(VERBOSE) echo sprintf("%s (wholesalePrice=%s weight=%s mod=%s) ", $line["ean"], $line["wholesalePrice"], $line["weight"], $mod);
	if ($mod > 13) {
            if (VERBOSE) echo "too heavy\n";
            continue;
	}
        $bol_price = $line["wholesalePrice"];
        $bol_price = bcadd($bol_price, $shipping[$mod]["cost"], 3); // sending cost
	$bol_price = bcadd($bol_price, "10", 3); // 10eur me
        $bol_price = bcadd($bol_price, "1", 3); // 1eur bol
        $bol_price = bcmul($bol_price, "1.15", 3);  // 15% BOL
        // TODO: Not always 21%
        $bol_price = bcmul($bol_price, "1.21", 3); // 21VAT

        // fmt
        $bol_price = round($bol_price, 2);
	$bol_price = strval(number_format($bol_price, 2, ".", ""));
	if ($line["calc_price_bol"] !== null) {
            $line["calc_price_bol"] = number_format(str_replace(",", "", $line["calc_price_bol"]), 2, ".", "");
        }
	if ($line["calc_price_bol"] === $bol_price) {
	    if (VERBOSE) echo sprintf("price not changed.\n");
            continue;
	}
	if (VERBOSE) echo sprintf("prev=%s calc_price_bol=%s\n", $line["calc_price_bol"], $bol_price);

	// Update?
	$db->update("prods", [
	    "calc_price_bol" => $bol_price
    ], [
	    "id" => $line["id"]
	]);
}
