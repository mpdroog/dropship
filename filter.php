<?php

$filters = [
"t-shirt",
"slip",
"string",
"durex",
"boxer",
"short",
"lingerie",
"jurk",
"jarretel",
"suit",
"kous",
"onderbroek",
"tuig",
"body",
"handboei",
"panty",
"body",
"masker",
"gag",
"kleding",
"sloffen",
"gordel",
"batterij",
"masker",
"beha",
"handschoen",
"haak",
"pantoffel",
"dwangbuis",
//"kuis",
//"kooi",
//"ballon",
"halsband",
"riem",
" bra",
"harnas",
"slip",
"bh",
//"strap-on",
"s/"
];

function filter_ignore($title) {
    global $filters;
    $title = strtolower($title);
    foreach ($filters as $filter) {
        if (strpos($title, $filter) !== false) {
            return true;
        }
    }
    return false;
}

$eans = [
    "8709641004775" => true
];
function filter_ean($ean) {
    global $eans;
    return isset($eans[$ean]);
}
