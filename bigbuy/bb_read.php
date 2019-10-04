<?php
/**
 * JSON to DB abstraction machine.
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/error.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/vendor/autoload.php";
use core\Db;

$nomatch=0;
$mismatch=0;
$update=0;
$nochange=0;

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$lines = explode(";", preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . "/default.sql")));
foreach ($lines as $line) {
    if (strlen(trim($line)) === 0) continue;
    //echo $line;
    $res = $db->exec($line);
    //var_dump($res->errorInfo());
}

// Perf++
{
    $db->exec("PRAGMA synchronous = OFF;");
    $db->exec("PRAGMA journal_mode = MEMORY");
}
$txn = $db->txn();

$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_cats.json");
foreach ($lines as $line) {
	if (VERBOSE) echo sprintf("Add %s\n", $line["name"]);
	$db->insert("cats", [
		"id" => $line["id"],
		"name" => $line["name"],
		"parentCategory" => $line["parentCategory"],
		"dateUpd" => strtotime($line["dateUpd"]),
		"dateAdd" => strtotime($line["dateAdd"]),
		"isoCode" => $line["isoCode"]
	]);
}

$lookup_prods = $db->getAllMap("id", "select id from prods");
$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_prods.json");
foreach ($lines as $line) {
	/*if (strlen(trim($line["ean13"])) === 0 || $line["ean13"] === "0") {
		if (VERBOSE) echo sprintf("Skip id=%d sku=%s reason=no ean13\n", $line["id"], $line["sku"]);
		continue;
	}*/
	if ($line["active"] !== 1) {
		if (VERBOSE) echo sprintf("Skip id=%d sku=%s reason=active=%d\n",$line["id"], $line["sku"], $line["active"]);
		continue;		
	}
	$f = [
		"id" => $line["id"],
		"manufacturer" => $line["manufacturer"],
		"ean" => $line["ean13"],
		"sku" => $line["sku"],
		"dateUpd" => strtotime($line["dateUpd"]),
		"category" => $line["category"],
		"dateUpdDescription" => strtotime($line["dateUpdDescription"]),
		"dateUpdStock" => strtotime($line["dateUpdStock"]),
		"wholesalePrice" => $line["wholesalePrice"],
		"retailPrice" => $line["retailPrice"],
		"dateAdd" => strtotime($line["dateAdd"]),
		"taxRate" => $line["taxRate"],
		"dateUpdProperties" => strtotime($line["dateUpdProperties"]),
		"dateUpdCategories" => strtotime($line["dateUpdCategories"]),
		"inShopsPrice" => $line["taxRate"]
	];

	if (! isset($lookup_prods[ $line["id"] ])) {
		if (VERBOSE) echo sprintf("Add id=%d sku=%s\n", $line["id"], $line["sku"]);
		$db->insert("prods", $f);
	} else {
		if (VERBOSE) echo sprintf("Update id=%d sku=%s\n", $line["id"], $line["sku"]);
		$db->update("prods", $f, ["id" => $line["id"]]);
	}
}

$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_prodinfo.json");
foreach ($lines as $line) {
	//{"id":1,"name":"Spin Hoofd Masseur","description":"<p>Leef je in stress? Verminder je stress nu met d","url":"spin-hoofd-masseur","isoCode":"nl","dateUpdDescription":"2016-07-08 07:26:46","sku":"F1515101"},
	if (VERBOSE) echo sprintf("Extend prodinfo=%s\n", $line["id"]);
	$db->update("prods", [
		"name" => $line["name"]
	], [
		"id" => $line["id"]
	], null);
}

$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_prodstock.json");
foreach ($lines as $line) {
	//[{"id":1,"stocks":[{"quantity":0,"minHandlingDays":1,"maxHandlingDays":2}],"sku":"F1515101"}
	if (VERBOSE) echo sprintf("Extend prodstock=%s\n", $line["sku"]);
	$stockn = 0;
	$days = 0;
	foreach ($line["stocks"] as $stock) {
		if ($stock["quantity"] > $stockn) {
			$stockn = $stock["quantity"];
			$days = $stock["maxHandlingDays"];
		}
	}

	if ($stockn > 2) {
		$stockn = 2; // TODO: All hardcoded to 2 to limit much manual labour
	}

	$db->update("prods", [
		"stock" => $stockn,
		"stock_days" => $days
	], [
		"sku" => $line["sku"]
	], null);
}

$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_prodsvariant.json");
foreach ($lines as $line) {
	if (strlen(trim($line["ean13"])) === 0 || $line["ean13"] === "0") {
		if (VERBOSE) echo sprintf("Skip id=%d sku=%s reason=no ean13\n", $line["id"], $line["sku"]);
		continue;
	}

	$f = [
		"id" => $line["id"],
		"product_id" => $line["product"],
		"ean" => $line["ean13"],
		"sku" => $line["sku"],
		"wholesalePrice" => $line["wholesalePrice"],
		"retailPrice" => $line["retailPrice"]
	];

	if (! isset($lookup_prods[ $line["id"] ])) {
		if (VERBOSE) echo sprintf("Add id=%d sku=%s\n", $line["id"], $line["sku"]);
		$db->insert("prod_variants", $f);
	} else {
		if (VERBOSE) echo sprintf("Update id=%d sku=%s\n", $line["id"], $line["sku"]);
		$db->update("prod_variants", $f, ["id" => $line["id"]]);
	}
}

$txn->commit();
$db->close();

if (VERBOSE) {
    /*print "nomatch=$nomatch\n";
    print "mismatch=$mismatch\n";
    print "Update=$update\n";
    print "Nochange=$nochange\n";*/
    print "memory_get_usage() =" . memory_get_usage()/1024 . "kb\n";
    print "memory_get_usage(true) =" . memory_get_usage(true)/1024 . "kb\n";
    print "memory_get_peak_usage() =" . memory_get_peak_usage()/1024 . "kb\n";
    print "memory_get_peak_usage(true) =" . memory_get_peak_usage(true)/1024 . "kb\n";
}
