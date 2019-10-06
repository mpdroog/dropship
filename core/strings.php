<?php
namespace core;

class Strings {
	/** HasPrefix tests whether the string s begins with prefix. */
	public static function has_prefix($s, $prefix) {
		return mb_substr($s, 0, mb_strlen($prefix)) === $prefix;
	}

	/** HasSuffix tests whether the string s ends with suffix. */
	public static function has_suffix($s, $suffix) {
		return mb_substr($s, -1 * mb_strlen($suffix)) === $suffix;
	}
	/** Slugify converts a string into an URL-friendly text */
        public static function slugify($text) {
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

	public static function fill($text, $len, $filler='0') {
		if (strlen($text) === $len) return $text;
		for ($i = strlen($text); $i < $len; $i++) {
			$text = $filler . $text;
		}
		return $text;
	}
}
