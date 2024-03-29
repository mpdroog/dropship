<?php
/**
 * Download all missing images
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/strings.php";
require __DIR__ . "/filter.php";
use core\Strings;

// Utils
function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

define("IMGDIR", "/var/www/a.mijnpoesje.nl/pub");
if (! file_exists(IMGDIR)) {
  if (! mkdir(IMGDIR)) {
    user_error(sprintf("mkdir(%s) failed", IMGDIR));
  }
}
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

$xml = new XMLReader();
if (! $xml->open('zip://' . CACHE . "/edc_prods.zip#eg_xml_feed_2015_nl.xml")) {
    user_error("ERR: Failed opening edc_prods.zip");
}
while($xml->read() && $xml->name != 'product')
{
        ;
}

$pfx = "";
define("EDC_URL", "http://cdn.edc.nl/500/%s");
while($xml->name == 'product') {
  $prod = xml($xml->readOuterXML());
  if (count($prod["variants"]["variant"]) === 0) {
    echo sprintf("no variants for prod=%s\n", $prod["title"]);
    $xml->next('product');
    unset($element);
    continue;
  }
  if (! isset($prod["variants"]["variant"]["ean"]) && ! isset($prod["variants"]["variant"][0]["ean"])) {
var_dump($prod["variants"]);
    echo sprintf("broken1 ean for prod=%s\n", $prod["title"]);
    $xml->next('product');
    unset($element);
    continue; // horrible broken input data..
  }
  $ean = $prod["variants"]["variant"]["ean"] ?? $prod["variants"]["variant"][0]["ean"];
  if (is_array($ean)) {
    echo sprintf("broken ean for prod=%s\n", $prod["title"]);
    $xml->next('product');
    unset($element);
    continue; // horrible broken input data..
  }
  if (VERBOSE) $pfx = $prod["title"] . " " . $ean;
  if (filter_ignore($prod["title"])) {
    if (VERBOSE) echo sprintf("$pfx Ignore %s\n", $prod["title"]);
    $xml->next('product');
    unset($element);
    continue;
  }

  $imgs = $prod["pics"]["pic"];
  if (! is_array($imgs)) {
    $imgs = [$imgs];
  }
  if (count($imgs) === 0) {
    user_error("No image for asset?");
  }
  if (VERBOSE) echo sprintf("$pfx found imgs=%d\n", count($imgs));

  // Map in DB
  {
    $variants = $prod["variants"]["variant"];
    if (isset($variants["id"])) {
      // hack. convert to array to generalize struct
      $variants = [$variants];
    }
    $up = 0;
    foreach ($variants as $variant) {
      // TODO: perf?
      if ("1" === $db->getCell("select 1 from prod_img where ean = ?", [$variant["ean"]])) continue;
      $db->insert("prod_img", ["ean" => $variant["ean"], "count" => count($imgs)]);
      $up++;
    }
    if (VERBOSE) echo "$pfx prod_img insert=$up\n";
  }

  $brandTitle = Strings::slugify($prod["brand"]["title"]);
  if (! file_exists(IMGDIR . "/$brandTitle")) {
    if (! mkdir(IMGDIR . "/$brandTitle")) {
      user_error("mkdir brand fail");
    }
  }
  foreach ($imgs as $idx => $pic) {
    if ($idx === 0) {
      $idx = 1;
    } else {
      $idx = explode("_", $pic)[1];
      $idx = str_replace(".jpg", "", $idx);
    }
    $f = IMGDIR . "/$brandTitle/$ean-" . Strings::slugify($prod["title"]) . "_" . $idx;
    $webp = file_exists("$f.webp");
    if (! file_exists("$f.jpg") || $webp === false) {
      if (VERBOSE) echo sprintf("$pfx Download " . EDC_URL . "\n", $pic);
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
      //echo sprintf("WARN: JPG(CMYK/RGB) file=%s.jpg\n", $f);

    } else {
      if ($webp === false) {
        var_dump($f);
        user_error("jpg exist, webp not. buggy?");
      }
      if (VERBOSE) echo "$pfx Already exists: $f\n";
    }
  }
  $xml->next('product');
}
