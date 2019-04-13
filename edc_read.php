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
"kuis",
"kooi",
"ballon",
"halsband",
"riem",
"bra",
"harnas",
"slip",
"bh",
"strap-on",
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
		echo sprintf("Ignore (not allowed on Bol) %s\n", $prod["title"]);
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
                    echo sprintf("Ignore (filter) %s\n", $prod["title"]);
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
                    echo sprintf("Ignore (filter) %s\n", $prod["title"]);
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
                echo "Product missing EAN, SKIP...\n";
                $error++;
                $xml->next('product');
                unset($element);
                continue;
            }
	$last_update = $db->getCell("SELECT time_updated from prods WHERE id=?", [$variant["id"]]);
	if ($last_update === false) {
            echo sprintf("Add %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
	    $db->insert("prods", [
		"id" => $variant["id"],
		"prod_id" => $prod["id"],
		"title" => $prod["title"] . " " . $variant["title"],
		"description" => $prod["description"],
		"ean" => $variant["ean"],
		"stock" => $variant["stockestimate"],
		"price" => $prod["price"]["b2c"],
		"time_updated" => $prod["modifydate"],
                "cats" => implode(",", $catids),
                "bol_pending" => 0,
                "edc_artnum" => $variant["subartnr"]
            ]);
	    $add++;
        } else if ($last_update !== $prod["modifydate"]) {
            echo sprintf("Update %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
            $db->update("prods", [
                "title" => $prod["title"] + " " + $variant["title"],
                "description" => $prod["description"],
                "ean" => $variant["ean"],
                "stock" => $variant["stockestimate"],
                "price" => $prod["price"]["b2c"],
                "time_updated" => $prod["modifydate"],
                "cats" => implode(",", $catids),
                "bol_pending" => 0,
                "edc_artnum" => $variant["subartnr"]
            ], ["id" => $variant["id"]]);
            $update++;
	} else {
            echo sprintf("Nochange %s %s\n", $variant["ean"], $prod["title"] . " " . $variant["title"]);
            $nochange++;
	}

	}
	
	$xml->next('product');
	unset($element);
}
$txn->commit();
$db->close();
$xml->close();

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
