<?php
namespace core;

class Strings
{
	/** prefix returns the text before sep from s */
	public static function prefix($s, $sep)
	{
		return mb_substr($s, 0, mb_strpos($s, $sep));
	}
	/** prefix returns the text after sep from s */
	public static function suffix($s, $sep)
	{
		return mb_substr($s, strlen($sep) + mb_strpos($s, $sep));
	}

	/** HasPrefix tests whether the string s begins with prefix. */
	public static function has_prefix($s, $prefix)
	{
		return mb_substr($s, 0, mb_strlen($prefix)) === $prefix;
	}

	/** HasSuffix tests whether the string s ends with suffix. */
	public static function has_suffix($s, $suffix)
	{
		return mb_substr($s, -1 * mb_strlen($suffix)) === $suffix;
	}

	/** Contains tests whether the string s contains search. */
	public static function contains($s, $search)
	{
		return mb_strpos($s, $search) !== false;
	}
	
	/** Slugify converts a string into an URL-friendly text */
	public static function slugify($text)
	{
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
	public static function strip_filler($val, $filler='X')
	{
		for ($i = strlen($val)-1; $i > 0; $i--) {
			if ($val[$i] !== $filler) {
				break;
			}
		}
		return substr($val, 0, $i+1);
	}
	public static function fill($text, $len, $filler='0')
	{
		if (strlen($text) === $len) {
			return $text;
		}
		for ($i = strlen($text); $i < $len; $i++) {
			$text = $filler . $text;
		}
		return $text;
	}
	
	public static function between($text, $from, $to)
	{
		$b = mb_strpos($text, $from);
		$e = mb_strpos($text, $to, $b+strlen($from));
		if ($b === false || $e === false) {
			var_dump(["b" => $b, "e" => $e]);
			user_error("failed parsing html positions(0) b=$from, e=$to");
		}
		$off = $b+strlen($from);
		return mb_substr($text, $off, $e-$off);
	}
}

