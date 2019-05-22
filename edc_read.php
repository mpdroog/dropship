<?php
/**
 * Parse ZIP(XML) fullfeed parser to SQLite DB.
 * Credits: http://drib.tech/programming/parse-large-xml-files-php
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";

function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

$filters = ["lingerie",
"jurk",
"jarretel",
"suit",
"kous",
"onderbroek",
"tuig",
"body",
"handboei",
"panty",
"body",
"masker",
"gag",
"kleding",
"sloffen",
"gordel",
"batterij",
"masker",
"beha",
"handschoen",
"haak",
"pantoffel",
"dwangbuis",
//"kuis",
//"kooi",
//"ballon",
"halsband",
"riem",
" bra",
"harnas",
"slip",
"bh",
//"strap-on",
"s/"
];

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$lines = explode(";", file_get_contents(__DIR__ . "/default.sql"));
foreach ($lines as $line) {
    if (strlen(trim($line)) === 0) continue;
    //echo $line;
    $res = $db->exec($line);
    //var_dump($res->errorInfo());
}

// Prod discounts
$brands = [];
foreach ($db->getAll("select id from brands") as $brand) {
    $brands[ $brand["id"] ] = 1;
}
$lines = explode("\n", file_get_contents(__DIR__ .  "/edc_discount.csv"));
array_shift($lines);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "") continue;
    // brandid;brandname;discount
    // 171;Abierta Fina;10
    $tok = explode(";", $line);
    if (isset($brands[$tok[0]])) continue;
    $db->insert("brands", [
        "id" => $tok[0],
        "name" => $tok[1],
        "discount" => $tok[2]
    ]);
}

// Products
$xml = new XMLReader();
if (! $xml->open('zip://' . __DIR__ . "/edc_prods.zip#eg_xml_feed_2015_nl.xml")) {
    user_error("ERR: Failed opening edc_prods.zip");
}

// Perf++
{
    $db->exec("PRAGMA synchronous = OFF;");
    //$db->exec("PRAGMA journal_mode = MEMORY");
}

$txn = $db->txn();
while($xml->read() && $xml->name != 'product')
{
	;
}

$catDone = [];
$add = 0;
$update = 0;
$ignore = 0;
$nochange = 0;
$error = 0;
$filtern = 0;
while($xml->name == 'product')
{
	$prod = xml($xml->readOuterXML());

	if ($prod["restrictions"]["platform"] === 'Y') {
		if (VERBOSE) echo sprintf("Ignore (not allowed on Bol) %s\n", $prod["title"]);
		$ignore++;
		$xml->next('product');
        	unset($element);
		continue;
	}
        if (is_array($prod["description"])) $prod["description"] = implode(" ", $prod["description"]);

        // Filter
        $title = strtolower($prod["title"]);
        foreach ($filters as $filter) {
                if (strpos($title, $filter) !== false) {
                    if (VERBOSE) echo sprintf("Ignore (title.filter for %s) %s\n", $filter, $prod["title"]);
                    $filtern++;
                    $xml->next('product');
                    unset($element);
                    continue 2;
                }
        }

        $catgroups = $prod["categories"]["category"];
        if (isset($prod["categories"]["category"]["cat"])) {
            $catgroups = [$prod["categories"]["category"]];
        }

        $catids = [];
	foreach ($catgroups as $catgroup) {
            foreach ($catgroup["cat"] as $cat) {
                $catids[] = $cat["id"];

                // Filter
                $title = strtolower($cat["title"]);
                foreach ($filters as $filter) {
                if (strpos($title, $filter) !== false) {
                    if (VERBOSE) echo sprintf("Ignore (cat.filter for %s) %s\n", $filter, $prod["title"]);
                    $filtern++;
                    $xml->next('product');
                    unset($element);
                    continue 4;
                }
                }

                if (isset($catDone[$cat["id"]])) continue;
                $db->exec("INSERT OR IGNORE INTO cats (id, title) VALUES(?, ?)", [$cat["id"], $cat["title"]]);
                $catDone[$cat["id"]] = true;
            }
        }

	$variants = $prod["variants"]["variant"];
	if (isset($variants["id"])) {
		// hack. convert to array to generalize struct
		$variants = [$variants];
	}

	foreach ($variants as $variant) {
            if (! isset($variant["title"])) $variant["title"] = "";
            if (is_array($variant["ean"])) $variant["ean"] = trim(implode(" ", $variant["ean"]));
            if (strlen($variant["ean"]) === 0) {
                if (VERBOSE) echo "Product missing EAN, SKIP...\n";
                $error++;
                $xml->next('product');
                unset($element);
                continue;
            }

            // Manual calculation of our own cost price
            // http://cdn.edc.nl/manuals/manual_b2b_feeds_2016.pdf - 9.1 Manual calculation
            $price = $prod["price"]["b2b"];
            if (isset($prod["price"]["b2bsale"])) {
                $price = $prod["price"]["b2bsale"]; // use discounted price!
            } else if ($prod["price"]["discount"] === 'Y') {
                // Subtract our discount from price
                $discount = $db->getCell("select discount from brands where id = ?", [$prod["brand"]["id"]]);
                if ($discount > 0) {
                    $discount_factor = bcsub("1", bcdiv($discount, "100", 5), 5);
                    $price = bcmul($price, $discount_factor, 5); // Subtract brand discount
                }
            }

            $vat_factor = bcadd("1", bcdiv($prod["price"]["vatnl"], "100", 5), 5);
            // TODO: Crash here if VAT in other country is higher than NL? As we might loose money here
            $price = bcmul($price, $vat_factor, 5); // add VAT (i.e. condoms are 9%)
            $price = bcadd($price, "6.5", 5);  // Add transaction costs

            $price = bcmul($price, "1.05", 5);  // Add 5% profit for me
            $site_price = round($price, 2);

            $price = bcmul($price, "1.15", 5); // bol 15% costs
            $price = bcadd($price, "1", 5);    // bol standard costs
            // $price = bcadd($price, "0.5", 5);    // Add 0,5eur for ourselves
            $bol_price = round($price, 2);
            $bol_price = number_format($bol_price, 2, ".", "");

        $cur = $db->getRow("SELECT time_updated, calc_price_bol from prods WHERE id=?", [$variant["id"]]);
        $last_price = $cur["calc_price_bol"] ?? null;
        if ($last_price !== null) {
            $last_price = number_format(str_replace(",", "", $last_price), 2, ".", "");
        }
	$last_update = $cur["time_updated"] ?? false;
	if ($last_update === false) {
            if (VERBOSE) echo sprintf("Add %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
	    $db->insert("prods", [
		"id" => $variant["id"],
		"prod_id" => $prod["id"],
		"title" => $prod["title"] . " " . $variant["title"],
		"description" => $prod["description"],
		"ean" => $variant["ean"],
		//"stock" => $variant["stockestimate"],
		"price" => $prod["price"]["b2c"],
                "price_me" => $prod["price"]["b2b"],
                "vat" => $prod["price"]["vatnl"],
                "brand_id" => $prod["brand"]["id"],
                "discount" => $prod["price"]["discount"] === 'Y' ? 1 : 0,
		"time_updated" => $prod["modifydate"],
                "cats" => implode(",", $catids),
                "bol_pending" => null, // ready for diff+sync
                "edc_artnum" => $variant["subartnr"],
                "calc_price_site" => $site_price,
                "calc_price_bol" => $bol_price
            ]);
	    $add++;
        } else if ($last_update < $prod["modifydate"] || $last_price !== $bol_price) {
var_dump($last_price, $bol_price);
            if (VERBOSE) echo sprintf("Update %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
            $db->update("prods", [
                "title" => $prod["title"] . " " . $variant["title"],
                "description" => $prod["description"],
                "ean" => $variant["ean"],
                //"stock" => $variant["stockestimate"],
                "price" => $prod["price"]["b2c"],
                "price_me" => $prod["price"]["b2b"],
                "vat" => $prod["price"]["vatnl"],
                "brand_id" => $prod["brand"]["id"],
                "discount" => $prod["price"]["discount"] === 'Y' ? 1 : 0,
                "time_updated" => $prod["modifydate"],
                "cats" => implode(",", $catids),
                "bol_pending" => null, // reeady for diff+sync
                "edc_artnum" => $variant["subartnr"],
                "calc_price_site" => $site_price,
                "calc_price_bol" => $bol_price
            ], ["id" => $variant["id"]], null);
            $update++;
	} else {
            if (VERBOSE) echo sprintf("Nochange %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
            $nochange++;
	}

	}
	
	$xml->next('product');
	unset($element);
}
$txn->commit();
$db->close();
$xml->close();

if (VERBOSE) {
    print "Ignore=$ignore\n";
    print "Add=$add\n";
    print "Update=$update\n";
    print "Nochange=$nochange\n";
    print "Error=$error\n";
    print "Filter=$filtern\n";
    print "memory_get_usage() =" . memory_get_usage()/1024 . "kb\n";
    print "memory_get_usage(true) =" . memory_get_usage(true)/1024 . "kb\n";
    print "memory_get_peak_usage() =" . memory_get_peak_usage()/1024 . "kb\n";
    print "memory_get_peak_usage(true) =" . memory_get_peak_usage(true)/1024 . "kb\n";
}

