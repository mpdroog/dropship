<?php
/**
 * EDC Automation check.
 * - Ensure prepaid > 50EUR
 * - Ensure nothing is queued
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/strings.php";
require __DIR__ . "/core/defer.php";
require __DIR__ . "/core/vigilo.php";

$begin = time();
$a = core\Defer(function() {
    global $begin;
    if (VERBOSE) echo sprintf("script.time=%s sec\n", (time()-$begin));
});

Vigilo::init("api", "zijujekusufilefogepolemo");
$cmd = file_get_contents(__DIR__ . "/edc_prepaid.cmd");
$res = Vigilo::script($cmd);
if (VERBOSE) var_dump($res);
$uuid = $res["uuid"];
if (VERBOSE) echo sprintf("uuid=%s\n", $uuid);

for ($i = 0; $i < 25; $i++) {
    $res = Vigilo::script_poll($uuid, null, ["audit" => "1"]);
    if (VERBOSE) var_dump($res);
    if ($res["ok"]) break;
    sleep(5);
}
if ($i >= 24) {
    var_dump($res);
    user_error("Timeout response for uuid=" . $uuid);
}

$res = $res["res"];
// ugly HTML parser here
// prepaid left
$s = '<strong id="header_klant_tegoed">';
$b = mb_strpos($res, $s);
$e = mb_strpos($res, '</strong>', $b+strlen($s));
if ($b === false || $e === false) {
    var_dump($res);
    var_dump(["b" => $b, "e" => $e]);
    user_error("failed parsing html positions.");
}
$prepaid = mb_substr($res, $b+strlen($s), $e-$b);
$prepaid = floatval(str_replace(",", ".", $prepaid));
if (VERBOSE) {
    echo sprintf("prepaid=%s\n", $prepaid);
}
if ($prepaid < 40) {
    echo sprintf("prepaid.remain=%s\n", $prepaid);
    echo sprintf("https://www.erotischegroothandel.nl/mijn_overzicht/tegoed/\n");
}

// Ensure nothing queued
if (mb_strpos($res, 'U hebt geen openstaande automatische orders.') === false) {
    echo "Outstanding automatic orders waiting for you to be processed!\n";
    echo sprintf("https://www.erotischegroothandel.nl/mijn_overzicht/ao_menu/\n");
}
