<?php
// please leave it as it is ;)
define("TINYEMOTES_NAME", "TinyEmotes");
define("TINYEMOTES_VERSION", "0.2.1");
define("TINYEMOTES_LINK", "https://github.com/ilotterytea/tinyemotes");

if ($s = file_get_contents("../.git/refs/heads/master")) {
    define("TINYEMOTES_COMMIT", $s);
} else {
    define("TINYEMOTES_COMMIT", null);
}