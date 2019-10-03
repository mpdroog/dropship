<?php
$r = json_decode(file_get_contents(__DIR__ . "/cache/bb_prods.json"), true);
var_dump(
count($r)
);
