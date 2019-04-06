<?php
namespace core;
/**
 * Temporarily disable the error handler.
 * Useful when calling legacy code and you don't want the
 * new code to get written as sloppy as the old because of
 * missing strictness.
 */
class Error {
    private static $handler = null;

    public static function mute() {
        if (self::$handler !== null) {
            throw new \Exception("Already muted, deverr calling it twice");
        }
        self::$handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            //error_log("Error::mute ($errfile:$errline) $errno: $errstr";);
            return true;
        });
        return self::$handler;
    }

    public static function unmute() {
        if (self::$handler === null) {
            throw new \Exception("Already unmuted, deverr calling it without setting");
        }

        $handler = self::$handler;
        self::$handler = null;
        return set_error_handler($handler);
    }
}

