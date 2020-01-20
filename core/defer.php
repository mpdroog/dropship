<?php
namespace core;

// Always run a function on close
// Example use:
// $cfd = core\Defer(function() {
//    echo "Called!";
// });
// ....
// $cfd = null; // Optional, force to call func now
//
// When is this useful?
// i.e. unlink a file on script stop
// https://github.com/php-defer/php-defer/blob/master/src/functions.inc.php
function Defer(callable $callback) {
    return new class($callback) {
            private $callback;
            public function __construct($callback)
            {
                $this->callback = $callback;
            }
            public function __destruct()
            {
                \call_user_func($this->callback);
            }
        };
}

