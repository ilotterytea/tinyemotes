<?php
define("CLIENT_REQUIRES_JSON", isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json");

// DATABASE
define("DB_USER", "kochan");
define("DB_PASS", "kochan");
define("DB_URL", "mysql:host=localhost;dbname=tinyemotes;port=3306");

// RATINGS
define("RATING_NAMES", [
    "-1" => "COAL",
    "1" => "GEM",
]);

// UPLOADS
define("ANONYMOUS_UPLOAD", false);
define("ANONYMOUS_DEFAULT_NAME", "chud");

// EMOTES
define("EMOTE_NAME_MAX_LENGTH", 100);

// ACCOUNTS
define("ACCOUNT_USERNAME_REGEX", "/^[A-Za-z0-9_]+$/");
define("ACCOUNT_USERNAME_MAX_LENGTH", 20);
define("ACCOUNT_PFP_MAX_SIZE", [128, 128]);
define("ACCOUNT_BANNER_MAX_SIZE", [1920, 1080]);