<?php
/**
 * Vigilo API.
 */
class Vigilo {
  private static $creds;
  public static function init($user, $pass) {
    self::$creds = [$user, $pass];
  }

  // https://www.php.net/manual/en/function.exif-imagetype.php#113253
  private static function is_jpeg(&$pict) {
    return (bin2hex($pict[0]) == 'ff' && bin2hex($pict[1]) == 'd8');
  }
  private static function is_png(&$pict) {
    return (bin2hex($pict[0]) == '89' && $pict[1] == 'P' && $pict[2] == 'N' && $pict[3] == 'G');
  }

  private static function img($url, $fields = null) {
      $url = "https://renderapi.vigilo.io/v1$url";
      $ch = curl_init($url);
      if ($ch === false) {
          user_error("curl_init fail");
      }
      $ok = 1;
      $ok &= curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
      $ok &= curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      //$ok &= curl_setopt($ch, CURLOPT_AUTOREFERER, true);
      $ok &= curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      $ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($fields) {
          $ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      }

      $ok &= curl_setopt($ch, CURLOPT_USERPWD, self::$creds[0] . ":" . self::$creds[1]);
      if ($ok !== 1) {
          $e = curl_error($ch);
          user_error("curl_setopt failed e=$e");
      }

      $res = curl_exec($ch);
      if ($res === false) {
          var_dump($res);
          user_error(curl_error($ch));
      }
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $e = curl_error($ch);
      curl_close($ch);

      if ($res === false) {
          user_error("curl_exec fail e=$e");
      }
      if ($code !== 200) {
          user_error("curl_exec(http=$code) $res");
      }
      return $res;
  }

  private static function json($url, $fields = null) {
      $ch = curl_init($url);
      if ($ch === false) {
          user_error("curl_init fail");
      }
      $ok = 1;
      $ok &= curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
      $ok &= curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      $ok &= curl_setopt($ch, CURLOPT_AUTOREFERER, true);
      $ok &= curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      $ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($fields) {
          $ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      }
      $ok &= curl_setopt($ch, CURLOPT_USERPWD, self::$creds[0] . ":" . self::$creds[1]);
      if ($ok !== 1) {
          $e = curl_error($ch);
          user_error("curl_setopt failed e=$e");
      }

      $res = curl_exec($ch);
      if ($res === false) {
          var_dump($res);
          user_error(curl_error($ch));
      }
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $e = curl_error($ch);
      curl_close($ch);

      if ($res === false) {
          user_error("curl_exec fail e=$e");
      }
      if ($code !== 200) {
          user_error("curl_exec(http=$code) $res");
      }
      if ($contentType !== "application/json") {
          user_error("curl_exec($url) returned invalid res=$res");
      }
      return json_decode($res, true);
  }

  public static function script($cmd) {
      $url = "https://renderapi.vigilo.io/v1/script.cmd";
      return self::json($url, $cmd);
  }

  public static function script_poll($jobid, $fields = null) {
      $url = "https://renderapi.vigilo.io/v1/job?uuid=$jobid";
      $ch = curl_init($url);
      if ($ch === false) {
          user_error("curl_init fail");
      }
      $ok = 1;
      $ok &= curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
      $ok &= curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      $ok &= curl_setopt($ch, CURLOPT_AUTOREFERER, true);
      $ok &= curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      $ok &= curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($fields) {
          $ok &= curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      }
      $ok &= curl_setopt($ch, CURLOPT_USERPWD, self::$creds[0] . ":" . self::$creds[1]);
      if ($ok !== 1) {
          $e = curl_error($ch);
          user_error("curl_setopt failed e=$e");
      }

      $res = curl_exec($ch);
      if ($res === false) {
          var_dump($res);
          user_error(curl_error($ch));
      }
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $e = curl_error($ch);
      curl_close($ch);

      if ($res === false) {
          user_error("curl_exec fail e=$e");
      }
      if (! in_array($contentType, ["application/json", "text/x-cmd-response"])) {
          user_error("curl_exec($url ct=$contentType) returned invalid res=$res");
      }
      if ($contentType === "application/json") {
          $res = json_decode($res, true);
      }
      return [
          "ok" => $code === 200 && $contentType === "text/x-cmd-response",
	  "res" => $res
      ];
  }

  public static function screenshot_html($html, $width=500, $height = 300) {
      $res = self::img("/screenshot.png?width=$width&height=$height&mobile=0", $html, "png");
      if (! self::is_png($res)) {
          user_error("Unexpected: response not PNG");
      }
      return $res;
  }
}
