<?php
include_once "../../../src/config.php";
include_once "../../../src/utils.php";
include_once "../../../src/alert.php";

if (!TWITCH_REGISTRATION_ENABLE) {
    generate_alert("/404.php", "Registration via Twitch is disabled", 405);
    exit;
}

session_start();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if (isset($_GET["disconnect"], $_SESSION["user_id"])) {
    $stmt = $db->prepare("SELECT c.id,
        CASE WHEN (
            SELECT u.password FROM users u WHERE u.id = c.user_id
        ) IS NOT NULL
        THEN 1 ELSE 0
        END AS set_password
        FROM connections c
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION["user_id"]]);

    if ($row = $stmt->fetch()) {
        if ($row["set_password"]) {
            $db->prepare("DELETE FROM connections WHERE user_id = ? AND platform = 'twitch'")->execute([$_SESSION["user_id"]]);
            generate_alert("/account", "Successfully disconnected from Twitch!", 200);
        } else {
            generate_alert("/account", "You must set a password before deleting any connections", 403);
        }
    } else {
        generate_alert("/account", "No Twitch connection found", 404);
    }
    exit;
}

$client_id = TWITCH_CLIENT_ID;
$client_secret = TWITCH_SECRET_KEY;
$redirect_uri = TWITCH_REDIRECT_URI;

if (isset($_GET["error"])) {
    header("Location: /account/login");
    exit;
}

if (!isset($_GET["code"])) {
    header("Location: https://id.twitch.tv/oauth2/authorize?client_id=$client_id&redirect_uri=$redirect_uri&response_type=code");
    exit;
}

$code = $_GET["code"];

// obtaining twitch token
$request = curl_init();
curl_setopt($request, CURLOPT_URL, "https://id.twitch.tv/oauth2/token");
curl_setopt($request, CURLOPT_POST, 1);
curl_setopt(
    $request,
    CURLOPT_POSTFIELDS,
    "client_id=$client_id&client_secret=$client_secret&code=$code&grant_type=authorization_code&redirect_uri=$redirect_uri"
);
curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($request);
curl_close($request);

$response = json_decode($response, true);

if (array_key_exists("status", $response)) {
    header("Location: /account/login");
    exit;
}

// identifying user
$request = curl_init();
curl_setopt($request, CURLOPT_URL, "https://api.twitch.tv/helix/users");
curl_setopt($request, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $response["access_token"],
    "Client-Id: $client_id"
]);
curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

$twitch_user = curl_exec($request);
curl_close($request);

$twitch_user = json_decode($twitch_user, true);

if (empty($twitch_user["data"])) {
    generate_alert("/account", "Failed to identify Twitch user", 500);
    exit;
}

$twitch_user = $twitch_user["data"][0];

// saving it
$twitch_access_token = $response["access_token"];
$twitch_refresh_token = $response["refresh_token"];
$twitch_expires_on = time() + intval($response["expires_in"]);

// creating user if not exists
$stmt = $db->prepare("SELECT * FROM users u
    INNER JOIN connections c ON c.alias_id = ?
    WHERE c.user_id = u.id AND c.platform = 'twitch'
");
$stmt->execute([$twitch_user["id"]]);

$user_id = "";
$user_secret_key = "";
$user_name = "";

if ($row = $stmt->fetch()) {
    if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] != $row["id"]) {
        generate_alert("/account", "There is another " . INSTANCE_NAME . " account associated with that Twitch account", 409);
        exit;
    }

    $user_name = $row["username"];
    $user_secret_key = $row["secret_key"];
    $user_id = $row["id"];
} else {
    $user_secret_key = generate_random_string(32);
    $user_name = $twitch_user["login"];
    $user_id = bin2hex(random_bytes(16));

    list($user_secret_key, $user_name, $user_id) = match (isset($_SESSION["user_id"])) {
        true => [$_COOKIE["secret_key"], $_SESSION["user_name"], $_SESSION["user_id"]],
        default => [generate_random_string(32), $twitch_user["login"], bin2hex(random_bytes(16))]
    };

    if (!isset($_SESSION["user_id"])) {
        // checking for duplicates
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$user_name]);
        $duplicates = intval($stmt->fetch()[0]);
        if ($duplicates > 0) {
            $i = 1;
            while (true) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute(["$user_name$i"]);

                if ($stmt->fetch()[0] == 0) {
                    break;
                }

                $i++;
            }
            $user_name .= $i;
        }

        $stmt = $db->prepare("INSERT INTO users(id, username, secret_key) VALUES (?, ?, ?)");
        if (!$stmt->execute([$user_id, $user_name, $user_secret_key])) {
            $db = null;
            echo "Failed to create a user";
            exit;
        }
    }

    $stmt = $db->prepare("INSERT INTO connections(user_id, alias_id, platform, data) VALUES (?, ?, 'twitch', ?)");
    $stmt->execute([
        $user_id,
        $twitch_user["id"],
        sprintf("%s:%s:%s", $twitch_access_token, $twitch_refresh_token, $twitch_expires_on)
    ]);
}

$_SESSION["user_id"] = $user_id;
$_SESSION["user_name"] = $user_name;
setcookie("secret_key", $user_secret_key, time() + ACCOUNT_COOKIE_MAX_LIFETIME, "/");

$db = null;

// downloading profile picture
$path = "../../static/userdata/avatars";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

if (!is_file("$path/$user_id")) {
    $fp = fopen("$path/$user_id", "wb");
    $request = curl_init();
    curl_setopt($request, CURLOPT_URL, $twitch_user["profile_image_url"]);
    curl_setopt($request, CURLOPT_FILE, $fp);
    curl_setopt($request, CURLOPT_HEADER, 0);

    curl_exec($request);
    curl_close($request);
    fclose($fp);
}

// downloading profile banner
$path = "../../static/userdata/banners";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

if (!is_file("$path/$user_id")) {
    $fp = fopen("$path/$user_id", "wb");
    $request = curl_init();
    curl_setopt($request, CURLOPT_URL, $twitch_user["offline_image_url"]);
    curl_setopt($request, CURLOPT_FILE, $fp);
    curl_setopt($request, CURLOPT_HEADER, 0);

    curl_exec($request);
    curl_close($request);
    fclose($fp);
}

header("Location: /account");