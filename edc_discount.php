<?php
/**
 * Read EDC discount from Chrome API
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
$cmd = file_get_contents(__DIR__ . "/edc_discount.cmd");
$res = Vigilo::script($cmd);
$uuid = $res["uuid"];
if (VERBOSE) echo sprintf("uuid=%s\n", $uuid);

for ($i = 0; $i < 25; $i++) {
    $res = Vigilo::script_poll($uuid);
    if (VERBOSE) var_dump($res);
    if ($res["ok"]) break;

    sleep(5);
}
if ($i >= 24) {
    var_dump($res);
    user_error("No response for uuid=" . $uuid);
}

$res = $res["res"];
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
        var_dump($lines);
        user_error("Line is not same as first?");
    }
}

file_put_contents(__DIR__ . "/cache/edc_discount.csv", trim($res));
