<?php
// INSTANCE
define("INSTANCE_NAME", "TinyEmotes");
define("INSTANCE_STATIC_FOLDER", "static"); // Static folder. Used only in /404.php.

// DATABASE
define("DB_USER", "ENTER_DATABASE_USER"); // Database user. MANDATORY!
define("DB_PASS", "ENTER_DATABASE_PASSWORD"); // Database password. MANDATORY!
define("DB_HOST", "ENTER_DATABASE_HOST"); // Database host. Can be 'localhost' if it's on the same machine as Tinyemotes.
define("DB_NAME", "ENTER_DATABASE_NAME"); // Database name.
define("DB_URL", 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';port=3306'); // Database URL. Change it if you don't use MySQL/MariaDB.

// RATINGS
define("RATING_ENABLE", true); // Enable ratings for emotes.
define("RATING_NAMES", [
    "-1" => "COAL",
    "1" => "GEM",
]); // Rating names. The schema is [ "id/rating_point" => "name" ].
define("RATING_EMOTE_MIN_VOTES", 10); // Minimal amount of votes to display emote rating.

// UPLOADS
define("ANONYMOUS_UPLOAD", false); // Allow anonymous upload for emotes.
define("ANONYMOUS_DEFAULT_NAME", "Anonymous"); // Default uploader name for anonymous emotes. It's also used when original uploader has been deleted.

// EMOTES
define("EMOTE_UPLOAD", true); // Enable emote upload.
define("EMOTE_NAME_MAX_LENGTH", 100); // Max length for emote name.
define("EMOTE_COMMENT_MAX_LENGTH", 100); // Max length for emote comment.
define("EMOTE_VISIBILITY_DEFAULT", 2); // Default visibility for emotes. 0 - unlisted, 1 - public, 2 - pending approval (same as unlisted).
define("EMOTE_MAX_SIZE", [128, 128]); // Max size of emote.
define("EMOTE_NAME_REGEX", "/^[A-Za-z0-9_]+$/"); // RegEx filter for emote names.
define("EMOTE_STORE_ORIGINAL", true); // Store original uploads of emotes.

// TAGS
define("TAGS_ENABLE", true); // Allow emote tagging.
define("TAGS_CODE_REGEX", "/^[A-Za-z0-9_]+$/");
define("TAGS_MAX_COUNT", 10); // Maximum tags per emote. Set -1 for unlimited amount.

// EMOTESETS
define("EMOTESET_PUBLIC_LIST", true); // Show emotesets public.

// MODERATION
define("MOD_SYSTEM_DASHBOARD", true); // Enable system dashboard for moderators (/system).
define("MOD_EMOTES_APPROVE", true); // Enable manual emote approval (/system/emotes).

// REPORTS
define("REPORTS_ENABLE", true); // Enable emote, user reports.

// ACCOUNTS
define("ACCOUNT_REGISTRATION_ENABLE", true); // Enable account registration.
define("ACCOUNT_COOKIE_MAX_LIFETIME", 86400 * 30); // Remember user for a month.
define("ACCOUNT_USERNAME_REGEX", "/^[A-Za-z0-9_]+$/"); // RegEx filter for account usernames.
define("ACCOUNT_USERNAME_LENGTH", [2, 20]); // [Min, Max] length for account usernames.
define("ACCOUNT_PASSWORD_MIN_LENGTH", 10); // Minimal length for passwords.
define("ACCOUNT_SECRET_KEY_LENGTH", 32); // The length for secret keys.
define("ACCOUNT_PFP_MAX_SIZE", [128, 128]); // Max dimensions for account pictures.
define("ACCOUNT_BANNER_MAX_SIZE", [1920, 1080]); // Max dimensions for account banners.
define("ACCOUNT_BADGE_MAX_SIZE", [72, 72]); // Max dimensions for account badges.
define("ACCOUNT_PUBLIC_LIST", true); // The public list of accounts.
define("ACCOUNT_LOG_ACTIONS", true); // Log user's actions (emote addition, etc.).

// TWITCH
define("TWITCH_REGISTRATION_ENABLE", false); // Enable account registration via Twitch.
define("TWITCH_CLIENT_ID", "AAAAAAAAA"); // Client ID of your Twitch application.
define("TWITCH_SECRET_KEY", "BBBBBBBBB"); // Secret key of your Twitch application.
define("TWITCH_REDIRECT_URI", ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http") . "://$_SERVER[HTTP_HOST]/account/login/twitch.php"); // Redirect URI of your Twitch application.

// CAPTCHA
define("CAPTCHA_ENABLE", true); // Enable built-in captcha.
define("CAPTCHA_SIZE", [580, 220]); // Captcha size.
define("CAPTCHA_FORCE_USERS", false); // Force authorized users to solve captcha.

// FOR DEVELOPERS
define("CLIENT_REQUIRES_JSON", isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json");