<?php
/**
 * Download all missing images
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/filter.php";

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

define("IMGDIR", "/var/www/mijnpoesje.nl/pub/assets");
if (! file_exists(IMGDIR)) {
  if (! mkdir(IMGDIR)) {
    user_error(sprintf("mkdir(%s) failed", IMGDIR));
  }
}
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");

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
  if (filter_ignore($prod["title"])) {
    if (VERBOSE) echo sprintf("Ignore %s\n", $prod["title"]);
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

  // Map in DB
  {
    $variants = $prod["variants"]["variant"];
    if (isset($variants["id"])) {
      // hack. convert to array to generalize struct
      $variants = [$variants];
    }
    foreach ($variants as $variant) {
      // TODO: perf?
      if ("1" === $db->getCell("select 1 from prod_img where ean = ?", [$variant["ean"]])) continue;
      $db->insert("prod_img", ["ean" => $variant["ean"], "count" => count($imgs)]);
    }
  }

  $brandTitle = slugify($prod["brand"]["title"]);
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
    $f = IMGDIR . "/$brandTitle/" . slugify($prod["title"]) . "_" . $idx;
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
      //echo sprintf("WARN: JPG(CMYK/RGB) file=%s.jpg\n", $f);

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
