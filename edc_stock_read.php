<?php
/**
 * Parse XML stock parser to SQLite DB.
 * Credits: http://drib.tech/programming/parse-large-xml-files-php
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";

function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$modified = date("Y-m-d H:i:s", filemtime(__DIR__ . "/edc_stock.xml"));
$xml = new XMLReader();
if (! $xml->open(__DIR__ . "/edc_stock.xml")) {
    user_error("ERR: Failed opening edc_stock.xml");
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

        if (is_array($prod["ean"])) $prod["ean"] = trim(implode(" ", $prod["ean"]));
        if (strlen($prod["ean"]) === 0) {
            if (VERBOSE) echo "Product missing EAN, SKIP...\n";
            $error++;
            $xml->next('product');
            unset($element);
            continue;
        }

	$stock = $db->getCell("SELECT stock from prods WHERE id=? and ean=?", [$prod["variantid"], $prod["ean"]]);
	if ($stock === false) {
            $filtern++;
            $xml->next('product');
            unset($element);
            continue;
        } else if ($stock !== $prod["qty"]) {
            echo sprintf("Update(%s) %s => %s\n", $prod["ean"], $stock, $prod["qty"]);
            $db->exec("UPDATE `prods` SET `stock`=?, bol_pending=null WHERE `id` = ? AND `ean` = ?", [$prod["qty"], $prod["variantid"], $prod["ean"]]);
            $update++;
	} else {
            if (VERBOSE) echo sprintf("Nochange %s\n", $prod["ean"]);
            $nochange++;
	}
	
	$xml->next('product');
	unset($element);
}
$txn->commit();
$db->close();
$xml->close();

print "Ignore=$ignore\n";
print "Update=$update\n";
print "Nochange=$nochange\n";
print "Error=$error\n";
print "Filter=$filtern\n";
print "memory_get_usage() =" . memory_get_usage()/1024 . "kb\n";
print "memory_get_usage(true) =" . memory_get_usage(true)/1024 . "kb\n";
print "memory_get_peak_usage() =" . memory_get_peak_usage()/1024 . "kb\n";
print "memory_get_peak_usage(true) =" . memory_get_peak_usage(true)/1024 . "kb\n";
