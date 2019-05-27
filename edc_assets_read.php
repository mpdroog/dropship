<?php
/**
 * Download all missing images
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";

// Utils
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
function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

$filters = ["lingerie",
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
define("IMGDIR", "/var/www/mijnpoesje.nl/pub/assets");
if (! file_exists(IMGDIR)) {
  if (! mkdir(IMGDIR)) {
    user_error(sprintf("mkdir(%s) failed", IMGDIR));
  }
}

$xml = new XMLReader();
if (! $xml->open('zip://' . __DIR__ . "/edc_prods.zip#eg_xml_feed_2015_nl.xml")) {
    user_error("ERR: Failed opening edc_prods.zip");
}
while($xml->read() && $xml->name != 'product')
{
        ;
}

define("EDC_URL", "http://cdn.edc.nl/500/%s");
while($xml->name == 'product') {
  $prod = xml($xml->readOuterXML());
  if (VERBOSE) echo $prod["title"] . "\n";

  $imgs = $prod["pics"]["pic"];
  if (! is_array($imgs)) {
    $imgs = [$imgs];
  }
  if (count($imgs) === 0) {
    user_error("No image for asset?");
  }
  foreach ($imgs as $idx => $pic) {
    if ($idx !== 0) {
      $idx = explode("_", $pic)[1];
      $idx = str_replace(".jpg", "", $idx);
    }
    $f = IMGDIR . "/" . slugify($prod["title"]) . "_" . $idx;
    $webp = file_exists("$f.webp");
    if (! file_exists("$f.jpg")) {
      if (VERBOSE) echo sprintf("Download " . EDC_URL . "\n", $pic);
      file_put_contents("$f.jpg", file_get_contents(sprintf(EDC_URL, $pic)));
      $out = "";
      $ret = 1;
      // jpg to webp with highest compression
      ob_start();
      exec(sprintf("cwebp -quiet -m 6 -q 80 %s -o %s", escapeshellarg("$f.jpg"), escapeshellarg("$f.webp")), $out, $ret);
      ob_get_clean();
      if ($ret !== 0) {
        $i = new Imagick("$f.jpg");
        $i->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $i->writeImage("$f.jpg");
        $i->destroy();

        exec(sprintf("cwebp -quiet -m 6 -q 80 %s -o %s", escapeshellarg("$f.jpg"), escapeshellarg("$f.webp")), $out, $ret);
        if ($ret !== 0) {
          var_dump($out);
          var_dump($ret);
          user_error("exec(cwebp failed)");
        }
      }

    } else {
      if ($webp === false) {
        var_dump($f);
        user_error("jpg exist, webp not. buggy?");
      }
      if (VERBOSE) echo "Already exists: $f\n";
    }
  }
  $xml->next('product');
}
