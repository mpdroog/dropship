<?php
/*
 * Download BOL datamodel to know how to upload.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/bol.php";

$arg_exportid = null;
if (count($_SERVER["argv"]) === 2) {
    $arg_exportid = $_SERVER["argv"][1];
    if (! is_numeric($arg_exportid)) {
        exit("ERR: argument should be exportid");
    }
}
if (count($_SERVER["argv"]) > 2) {
    exit("ERR: argument should be exportid");
}

// https://mailing.bol.com/content/Datamodel.xml
$fname = __DIR__ . "/bol_datamodel.xml";
$fd = fopen($fname, "w");
$res = bol_zip("GET", "https://mailing.bol.com/content/Datamodel.xml", $fd);
if (VERBOSE) var_dump($res);
fclose($fd);

if ($res !== true) {
  user_error("bol_datamodel failed");
}
if (VERBOSE) echo sprintf("Written to %s\n", $fname);

