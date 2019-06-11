<?php
const API_URL = "https://api.bol.com/retailer";
const API_CLIENTID = "8338f293-a8a0-4d6c-b660-7d77d76002cb";
const API_SECRET = "aMPKgg6tsz_5fvRQbNweO4ejCaSdOI_cVb698D5YwfMy1GeAvm94YGeAD1JRjmI_eGKk0s2bRXc59NECLcrKSw";
const API_USER = "sync";

function bol_bearer() {
    $session = curl_init("https://login.bol.com/token?grant_type=client_credentials");
    curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($session, CURLOPT_USERPWD, API_CLIENTID . ':' . API_SECRET);
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($session);
    if ($res === false) {
        user_error("bol_bearer e=" . curl_error($session));
    }
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

    $res_headers = [];
    curl_setopt($session, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$res_headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
          return $len;
        $name = strtolower(trim($header[0]));
        if (!array_key_exists($name, $res_headers))
          $res_headers[$name] = [trim($header[1])];
        else
          $res_headers[$name][] = trim($header[1]);
        return $len;
    });


    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($session);
    $code = curl_getinfo($session, CURLINFO_HTTP_CODE);
    $res_headers["status"] = $code;
    curl_close($session);
    $results = json_decode($response, true);
    if ($code === "429") {
        user_error("Error, ratelimit.");
    }
    return [$results, $res_headers];
}
function ratelimit(array $head) {
    if (! isset($head["x-ratelimit-remaining"])) {
        return;
    }
    if ($head["x-ratelimit-remaining"][0] === "1") {
        $retry = intval($head["x-ratelimit-reset"][0]) - time();
        if ($retry < 10) {
            $retry = 10;
        }
        if (VERBOSE) echo sprintf("[ratelimit] sleep %d sec..", $retry);
        sleep($retry);
    }
}

function bol_zip($method, $url, $fd) {
    global $token;
    if (! is_array($token) || $token["expire"] < time()) {
        // Lazy auto-request new token
        $token = bol_bearer();
    }

    $bearer = $token["bearer"];
    $ch = curl_init($url);
    $headers = ['Accept: application/vnd.retailer.v3+csv', sprintf('Authorization: Bearer %s', $bearer)];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_FILE, $fd);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);
    if ($res === false) {
        var_dump($url);
        user_error(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

