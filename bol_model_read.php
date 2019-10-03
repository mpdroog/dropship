<?php
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/error.php";
const CSV_HEAD = "2da3306a0eb73cdf28087d3b2ef17dd8";
$db = new core\Db(sprintf("sqlite:%s/db.sqlite", CACHE), "", "");

$nomatch = 0;
$mismatch = 0;
$nochange = 0;
$update = 0;

function xml($res) {
    $xml = new SimpleXMLElement($res);
    return json_decode(json_encode($xml), true);
}

$xml = new XMLReader();
if (! $xml->open(CACHE . "/bol_datamodel.xml")) {
    user_error("ERR: Failed opening edc_prods.zip");
}

while($xml->read() && $xml->name != 'productType')
{
        ;
}

$prods = [];
{
    foreach ($db->getAll("select id, name from bol_prods") as $row) {
        $prods[ $row["name"] ] = $row["id"];
    }
}

$txn = $db->txn();
$rows = 0;
while($xml->name == 'productType')
{
        $rows++;
        if ($rows % 100) {
            $txn->commit();
            $txn = $db->txn();
        }
        $prod = xml($xml->readOuterXML());

        if (isset($prods[ $prod["name"] ])) {
            $idx = $prods[ $prod["name"] ];
        } else {
            $idx = $db->insert("bol_prods", [
                "name" => $prod["name"],
                "chunkid" => $prod["chunkId"]
            ]);
        }

        $attrs = 0;
        // TODO: something for uniqeness?
        foreach ($prod["attributes"]["attribute"] as $attr) {
            $attrs++;
            $db->insert("bol_prod_attrs", [
                "bol_prod_id" => $idx,
                "name" => $attr["name"],
                "label" => $attr["label"],
                "definition" => $attr["attributeDefinition"] ?? ""
            ]);
        }
        echo "Add prod " . $prod["name"] . " attr=" . $attrs . "\n";

        $xml->next('productType');
        unset($element);
}
$txn->commit();

