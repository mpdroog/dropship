<?php
/**
 * Convert all errnous products to XLS for manual import
 * into Bol.
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/strings.php";
require __DIR__ . "/core/xlsxwriter.php";
use core\Strings;

define("IMGDIR", "/var/www/a.masturbatorwebshop.be/pub");
define("EDC_URL", "http://cdn.edc.nl/500/%s");

if (VERBOSE) echo sprintf("sqlite:%s/db.sqlite\n", CACHE);
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

$skip = implode(",", ["848518012753", "818141012079", "818141012109"]);
$lines = $db->getAllMap("ean", "select ean, stock, calc_price_bol, title as productnaam, description, 'nieuw' as conditie, '24uurs-20' as levertijd, 'verkoper' as afleverwijze, brands.name as brand from prods join brands on prods.brand_id = brands.id where bol_error is not null and stock > 5 and ean not in ($skip)");
$header = [
    "ean" => 'integer',
    "stock" => 'integer',
    "calc_price_bol" => 'price',
    "productnaam" => 'string',
    "description" => 'string',
    "conditie" => 'string',
    "levertijd" => 'string',
    "afleverwijze" => 'string',
    "brand" => 'string',
    "image" => 'string'
];

// meta
$xml = new XMLReader();
if (! $xml->open('zip://' . CACHE . "/edc_prods.zip#eg_xml_feed_2015_nl.xml")) {
    user_error("ERR: Failed opening edc_prods.zip");
}
while($xml->read() && $xml->name != 'product')
{
        ;
}

$skip = ["848518012753", "818141012079", "818141012109"];
$ignore = [
"Zeer groot", "Eindoordeel", "Balzak", "Garantie", "Soort garantie", "Verpakking", "Platte onderkant", "CE_keurmerk", "Structuur", "Bediening", "Opening", "Manier van pompen", "Wastemperatuur", "Droogtrommel", "Strijken",
"Powerbox nodig","Motief",
"Hol","Rotatie","Bereik afstandsbediening","Ontlucht ventiel","Penis verlengend","Strijken","Droogtrommel","Chemisch reinigen","Reservoir","Vorm","Travel-Ready"
];
while($xml->name == 'product')
{
        $prod = xml($xml->readOuterXML());
        if (is_array($prod["description"])) $prod["description"] = implode(" ", $prod["description"]);

       $variants = $prod["variants"]["variant"];
        if (isset($variants["id"])) {
                // hack. convert to array to generalize struct
                $variants = [$variants];
        }

        foreach ($variants as $variant) {
                if (! isset($variant["ean"]) || is_array($variant["ean"])) {
                    continue;
                }
                if (! isset($lines[ $variant["ean"]  ])) {
                    continue;
                }
                if (VERBOSE) echo sprintf("name=%+s\n", $prod["title"]);

                $line = $lines[ $variant["ean"]  ];
                foreach ($prod["measures"] as $k => $v) {
                    if (! isset($header[$k])) $header[$k] = "string";
                    $line[ $k ] = $v;
                }
                foreach ($prod["properties"]["prop"] as $m) {
                    if (in_array($m["property"], $ignore)) continue;
                    if (! isset($header[ $m["property"] ])) {
                        $header[ $m["property"] ] = "string";
                    }
                    if (count($m["values"]["value"]) > 1 && isset($m["values"]["value"][0])) {
                        $line[ $m["property"] ] = "";
                        $n = 0;
                        foreach ($m["values"]["value"] as $v) {
                            if ($n > 0) $line[ $m["property"] ] .= ",";
                            $n++;
                            $line[ $m["property"] ] .= $v["title"];
                        }
                    } else {
                        $line[ $m["property"] ] = $m[ "value"  ];
                    }
                }
                if (isset($prod["material"]) && count($prod["material"]) > 1) {
                    if (! isset($header["material"])) $header["material"] = "string";
                    $line[ "material" ] = $prod["material"]["title"];
                }
                if (isset($prod["battery"]) && count($prod["material"]) > 1) {
                    if (! isset($header["battery"])) $header["battery"] = "string";
                    $line[ "battery" ] = $prod["battery"]["required"];
                }

  $imgs = $prod["pics"]["pic"];
  if (! is_array($imgs)) {
    $imgs = [$imgs];
  }
  $brandTitle = Strings::slugify($prod["brand"]["title"]);
  if (! file_exists(IMGDIR . "/$brandTitle")) {
    if (! mkdir(IMGDIR . "/$brandTitle")) {
      user_error("mkdir brand fail");
    }
  }
  $ean = $variant["ean"];
  $idx = 0;
  $pic = $imgs[0];
    $f = IMGDIR . "/$brandTitle/$ean-" . Strings::slugify($prod["title"]) . "_" . $idx . ".jpg";
    if (! file_exists($f)) {
        if (VERBOSE) echo "create=$f\n";
        file_put_contents("$f", file_get_contents(sprintf(EDC_URL, $pic)));
    }
    $line["image"] = "https://a.masturbatorwebshop.be/$brandTitle/$ean-" . Strings::slugify($prod["title"]) . "_" . $idx . ".jpg";

                $lines[ $variant["ean"]  ] = $line;
        }

        $xml->next('product');
        unset($element);
}
// end meta

$xml = new XLSXWriter();
$xml->writeSheetHeader('Sheet1', $header );
foreach($lines as $row) {
  $out = [];
  foreach ($header as $f => $ignore) {
    $out[$f] = $row[$f] ?? "";
  }

  //if (VERBOSE) var_dump($out);
  $xml->writeSheetRow('Sheet1', $out );
}

$base = "/var/www/dropship.rootdev.nl/pub";
$xml->writeToFile($base . '/bol.xlsx');
echo sprintf("Written %d sync entries to %s%s\n", count($lines), $base, "/bol.xlsx");
echo "https://dropship.rootdev.nl/bol.xlsx\n";
