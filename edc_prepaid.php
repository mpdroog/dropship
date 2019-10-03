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

$ch = curl_init();
if ($ch === false) {
    user_error('curl_init fail');
}
$ok = 1;
$ok &= curl_setopt($ch, CURLOPT_URL,"http://192.168.178.36:8022/v1/script.cmd");
$ok &= curl_setopt($ch, CURLOPT_POST, 1);
$ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents(__DIR__ . "/edc_prepaid.cmd"));
$ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$ok &= curl_setopt($ch, CURLOPT_USERPWD, "rootdevnl" . ":" . "77UJ/xbap/lfwkhWqoCn3QW/lcBq46oXinL9UreLchU");  

if ($ok !== 1) {
    user_error("curl_setopt fail");
}

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($res === false) {
    user_error("curl_exec fail");
}
if ($code !== 200) {
    user_error("curl_http($code) res=$res");
}

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
if ($prepaid < 50) {
    echo sprintf("prepaid.remain=%s\n", $prepaid);
    echo sprintf("https://www.erotischegroothandel.nl/mijn_overzicht/tegoed/\n");
}

// Ensure nothing queued
if (mb_strpos($res, 'U hebt geen openstaande automatische orders.') === false) {
    echo "Outstanding automatic orders waiting for you to be processed!\n";
    echo sprintf("https://www.erotischegroothandel.nl/mijn_overzicht/ao_menu/\n");
}
