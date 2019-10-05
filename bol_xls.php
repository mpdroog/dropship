<?php
/**
 * Convert all errnous products to XLS for manual import
 * into Bol.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/strings.php";
require __DIR__ . "/core/xlsxwriter.php";

if (VERBOSE) echo sprintf("sqlite:%s/db.sqlite\n", CACHE);
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

/*
  "slug" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT NOT NULL,
  "ean" TEXT NOT NULL,
  "stock" INTEGER,
  "price" real NOT NULL,
  "price_me" real NOT NULL,
  "vat" integer not null,
  "brand_id" integer not null,
  "discount" integer not null,
  "time_updated" TEXT NOT NULL,
  "edc_artnum" TEXT NOT NULL,
  "bol_id" text,
  "bol_updated" text,
  "bol_error" INTEGER,
  "prod_id" INTEGER,
  "cats" TEXT NOT NULL,
  "bol_stock" INTEGER,
  "bol_pending" INTEGER,
  "bol_price" real,
  "calc_price_bol" real not null,
  "calc_price_site" real not null
*/

$lines = $db->getAll("select ean, stock, calc_price_bol, title as product, description from prods where bol_error is not null and stock > 5");
$header = [
    "ean" => 'integer',
    "stock" => 'integer',
    "calc_price_bol" => 'price',
    "product" => 'string',
    "description" => 'string'
];

$xml = new XLSXWriter();
$xml->writeSheetHeader('Sheet1', $header );
foreach($lines as $row) {
  if (VERBOSE) echo $row["ean"] . " - " . $row["stock"] . "\n";
  $xml->writeSheetRow('Sheet1', $row );
}

$base = "/var/www/dropship.rootdev.nl/pub";
$xml->writeToFile($base . '/bol.xlsx');
echo sprintf("Written %d sync entries to %s%s\n", count($lines), $base, "/bol.xlsx");
echo "https://dropship.rootdev.nl/bol.xlsx\n";
