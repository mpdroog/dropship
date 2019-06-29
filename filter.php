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

// https://stackoverflow.com/questions/2955251/php-function-to-make-slug-url-string
function slugify($text) {
  // replace non letter or digits by -
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  // transliterate
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  // remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);
  // trim
  $text = trim($text, '-');
  // remove duplicate -
  $text = preg_replace('~-+~', '-', $text);
  // lowercase
  $text = strtolower($text);

  if (empty($text)) {
    user_error("slugify($text) invalid.");
  }
  return $text;
}

