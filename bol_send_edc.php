<?php
/*
 * Sync order from Bol to EDC
 */
require __DIR__ . "/core/init.php";
require __DIR__ . "/core/db.php";
require __DIR__ . "/core/error.php";
require __DIR__ . "/core/bol.php";
require __DIR__ . "/core/edc.php";

// Package
const PACKING_ID = 3880;
$db = new \core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
$orderdb = new \core\Db(sprintf("sqlite:%s/orders.sqlite", __DIR__), "", "");
$countries = [
    "NL" => "1",
    "BE" => "2"
];

$lines = explode(";", file_get_contents(__DIR__ . "/default_sync.sql"));
foreach ($lines as $line) {
    if (strlen(trim($line)) === 0) continue;
    //echo $line;
    $res = $db->exec($line);
    //var_dump($res->errorInfo());
}

function edc_http($xml) {
        // Send the XML request
        $postfields = 'data='.$xml;

        $ch = curl_init("https://www.erotischegroothandel.nl/ao/");
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postfields);
        $result = curl_exec($ch);
        curl_close($ch);

        if($ch === false || $result === false){
                user_error('There was a problem with the connection to EDC');
        } else {
                $json = json_decode($result,true);

                // Success
                if($json['result'] == 'OK'){

                        echo '<pre>';
                        echo 'The order was successful. The following output was received from EDC:'.PHP_EOL;
                        print_r($json);
                        echo '</pre>';
                        return $json["ordernumber"];

                // Failure
                } else {
                        echo '<pre>';
                        echo 'There was a problem with the order request. The following output was received from EDC:'.PHP_EOL;
                        print_r($json);
                        echo '</pre>';
                }
        }
	return null;
}

list($res, $headers) = bol_http("GET", "/orders", []);
$res["orders"] = $res["orders"] ?? [];
var_dump($res);

foreach ($res["orders"] as $order) {
    if (VERBOSE) var_dump($order);
    if ("1" === $orderdb->getCell("select 1 from orders where bol_id = ?", [$order["orderId"]])) {
        if (VERBOSE) echo "Already processed " . $order["orderId"] . "\n";
        continue;
    }
    list($details, $header) = bol_http("GET", "/orders/" . $order["orderId"]);
    if (VERBOSE) var_dump($details);

    $ship = $details["customerDetails"]["shipmentDetails"];
    if (isset($ship["houseNumberExtended"])) {
        $ship["houseNumber"] .= " " . $ship["houseNumberExtended"];
    }
    $prods = $details["orderItems"];

    $xprods = [];
    foreach ($prods as $prod) {
        $artnr = $db->getCell("select edc_artnum from prods where ean=?", [$prod["ean"]]);
        if (strlen($artnr) === 0) {
            user_error("failed resolving artnr for ean " . $prod["ean"]);
        }
        $xprods[] = "<artnr>$artnr</artnr>";
    }
    $xprods = implode("\n", $xprods);

$xml = '<?xml version="1.0"?>
<orderdetails>
<customerdetails>
	<email>rootdev@gmail.com</email>
	<apikey>35t55w94ec2833998860r3e5626eet1c</apikey>
	<output>advanced</output>
</customerdetails>
<receiver>
	<name>' . $ship["firstName"] . ' ' . $ship["surName"]  . '</name>
        <extra_email>' . $ship["email"] .'</extra_email>
	<street>' . $ship["streetName"] . '</street>
	<house_nr>' . $ship["houseNumber"] . '</house_nr>
	<postalcode>' . $ship["zipCode"] . '</postalcode>
	<city>' . $ship["city"] . '</city>
	<country>' . $countries[ $ship["countryCode"] ] . '</country>
        <packing_slip_id>' . PACKING_ID . '</packing_slip_id>
	<own_ordernumber>' . $order["orderId"] . '</own_ordernumber>
</receiver>
<products>' . $xprods . '</products>
</orderdetails>';

    if (VERBOSE) var_dump($xml);
    //$id = edc_http($xml);
    $id = "99";
    echo sprintf("Bol id=%s edc=%s\n", $order["orderId"], $id);
    $orderdb->insert("orders", [
        "bol_id" => $order["orderId"],
        "edc_id" => $id
    ]);
}
