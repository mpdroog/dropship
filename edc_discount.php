<?php
/**
 * Read EDC discount from Chrome API
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
$ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents(__DIR__ . "/edc_discount.cmd"));
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

$lines = explode("\n", trim($res));
if (VERBOSE) echo sprintf("line.count=%d\n", count($lines));
if (count($lines) <= 1) {
    var_dump($res);
    user_error("res invalid");
}

$sep = null;
foreach ($lines as $idx => $line) {
    if (VERBOSE) echo sprintf("line.parse=%s\n", $line);
    if ($idx === 0) {
        $sep = count(explode(";", $line));
        if ($sep <= 1) {
            var_dump($res);
            user_error("line.sep invalid amount");
        }
        if (VERBOSE) echo sprintf("line.sep=%d\n", $sep);
        continue;
    }
    if (count(explode(";", $line)) !== $sep) {
        var_dump($line);
        user_error("Line is not same as first?");
    }
}

file_put_contents(__DIR__ . "/cache/edc_discount.csv", trim($res));
