<?php
/*
 * Sync order from Bol to EDC
 */
require __DIR__ . "/core/init.php";
const API_URL = "https://api.bol.com/retailer";
const API_CLIENTID = "8338f293-a8a0-4d6c-b660-7d77d76002cb";
const API_SECRET = "aMPKgg6tsz_5fvRQbNweO4ejCaSdOI_cVb698D5YwfMy1GeAvm94YGeAD1JRjmI_eGKk0s2bRXc59NECLcrKSw";
const API_USER = "sync";
const PACKING_ID = 3880;

function bol_bearer() {
    $session = curl_init("https://login.bol.com/token?grant_type=client_credentials");
    curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($session, CURLOPT_USERPWD, API_CLIENTID . ':' . API_SECRET);
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($session);
    curl_close($session);
    $j = json_decode($res, true);
    if (! is_array($j)) {
        user_error("http_bearer: invalid res=$res");
    }
    if ($j["token_type"] !== "Bearer") {
        user_error("http_bearer: unsupported token type=" . $j["token_type"]);
    }
    if ($j["scope"] !== "RETAILER") {
        var_dump($j);
        user_error("http_bearer: account does not have retailer role but role=" . $j["scope"]);
    }

    $exp = $j["expires_in"];
    return [
        "expire" => strtotime("+$exp sec"),
        "bearer" => $j["access_token"]
    ];
}

function bol_http($method, $url, $d = []) {
    global $token;
    if (! is_array($token) || $token["expire"] < time()) {
        // Lazy auto-request new token
        $token = bol_bearer();
    }

    $bearer = $token["bearer"];
    $session = curl_init(API_URL . $url);
    $headers = ['Accept: application/vnd.retailer.v3+json', sprintf('Authorization: Bearer %s', $bearer)];
    if (count($d) > 0) {
        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($d));
        $headers[] = 'Content-Type: application/vnd.retailer.v3+json';
    }

    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($session);
    curl_close($session);
    $results = json_decode($response, true);
    return $results;
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

                // Failure
                } else {
                        echo '<pre>';
                        echo 'There was a problem with the order request. The following output was received from EDC:'.PHP_EOL;
                        print_r($json);
                        echo '</pre>';
                }
        }
}

$res = bol_http("GET", "/orders", []);

foreach ($res["orders"] as $order) {
    var_dump($order);
    $details = bol_http("GET", "/orders/" . $order["orderId"]);
    var_dump($details);

    $ship = $details["customerDetails"]["shipmentDetails"];
    $prods = $details["orderItems"];

    $xprods = [];
    foreach ($prods as $prod) {
        $artnr = $db->getCell("select edc_artnum from prods where ean=?", [$prods["ean"]]);
        if (strlen($artnr) === 0) {
            user_error("failed resolving artnr for ean " . $prods["ean"]);
        }
        $xprods[] = "<artnr>$artnr</artnr>";
    }
    $xprods = implode("\n", $xprods);

$xml = '<?xml version="1.0"?>
<orderdetails>
<customerdetails>
	<email>' . $ship["email"] .'</email>
	<apikey>35t55w94ec2833998860r3e5626eet1c</apikey>
	<output>advanced</output>
</customerdetails>
<receiver>
	<name>' . $ship["firstName"] . ' ' . $ship["surName"]  . '</name>
	<street>' . $ship["streetName"] . '</street>
	<house_nr>' . $ship["houseNumber"] . $ship["houseNumberExtended"] . '</house_nr>
	<postalcode>' . $ship["zipCode"] . '</postalcode>
	<city>' . $ship["city"] . '</city>
	<country>' . $ship["countryCode"] . '</country>
        <packing_slip_id>' . PACKING_ID . '</packing_slip_id>
</receiver>
<products>' . $xprods . '</products>
</orderdetails>';

    die();
}
