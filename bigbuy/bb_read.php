<?php
/**
 * JSON to DB abstraction machine.
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/error.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/vendor/autoload.php";
use core\Db;

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
	if (strlen(trim($line["ean13"])) === 0 || $line["ean13"] === "0") {
		if (VERBOSE) echo sprintf("Skip id=%s reason=no ean13\n", $line["id"]);
		continue;
	}
	if ($line["active"] !== 1) {
		if (VERBOSE) echo sprintf("Skip id=%s reason=active=%d\n", $line["id"], $line["active"]);
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
		if (VERBOSE) echo sprintf("Add prod=%s\n", $line["ean13"]);
		$db->insert("prods", $f);
	} else {
		if (VERBOSE) echo sprintf("Update prod=%s\n", $line["ean13"]);
		$db->update("prods", $f, ["id" => $line["id"]]);
	}
}

$lines = \JsonMachine\JsonMachine::fromFile(__DIR__ . "/cache/bb_prodinfo.json");
foreach ($lines as $line) {
	//{"id":1,"name":"Spin Hoofd Masseur","description":"<p>Leef je in stress? Verminder je stress nu met d","url":"spin-hoofd-masseur","isoCode":"nl","dateUpdDescription":"2016-07-08 07:26:46","sku":"F1515101"},
	if (VERBOSE) echo sprintf("Extend prodinfo=%s\n", $line["name"]);
	$db->update("prods", [
		"name" => $line["name"]
	], [
		"sku" => $line["sku"]
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

	$db->update("prods", [
		"stock" => $stockn,
		"stock_days" => $days
	], [
		"sku" => $line["sku"]
	], null);
}

$txn->commit();
$db->close();
