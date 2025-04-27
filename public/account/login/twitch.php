<?php
include "../../../src/utils.php";
include_once "../../../src/config.php";

$client_id = "472prq7kqn0a21l5um2lz7374471pp";
$client_secret = "koho369mw8p51di4fx34jm2ogdmbj2";
$redirect_uri = "http://localhost:8000/account/login/twitch.php";

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
session_start();

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
    echo "Failed to identify";
    exit;
}

$twitch_user = $twitch_user["data"][0];

// saving it
$_SESSION["twitch_access_token"] = $response["access_token"];
$_SESSION["twitch_refresh_token"] = $response["refresh_token"];
$_SESSION["twitch_expires_on"] = time() + intval($response["expires_in"]);

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// creating user if not exists
$stmt = $db->prepare("SELECT id, user_id FROM connections WHERE alias_id = ? AND platform = 'twitch'");
$stmt->execute([$twitch_user["id"]]);

$user_id = "";
$user_secret_key = "";
$user_name = "";

if ($row = $stmt->fetch()) {
    $id = $row["id"];
    $user_id = $row["user_id"];

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($row = $stmt->fetch()) {
        $user_name = $row["username"];
        $user_secret_key = $row["secret_key"];
        $user_id = $row["id"];
    } else {
        $db = null;
        echo "Connection found, but not user?";
        exit;
    }
} else {
    $user_secret_key = generate_random_string(32);
    $user_name = $twitch_user["login"];

    $stmt = $db->prepare("INSERT INTO users(username, secret_key) VALUES (?, ?)");
    if (!$stmt->execute([$user_name, $user_secret_key])) {
        $db = null;
        echo "Failed to create a user";
        exit;
    }

    $user_id = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO connections(user_id, alias_id, platform, data) VALUES (?, ?, 'twitch', ?)");
    $stmt->execute([
        $user_id,
        $twitch_user["id"],
        $_SESSION["twitch_access_token"] . ":" . $_SESSION["twitch_refresh_token"] . ":" . $_SESSION["twitch_expires_on"]
    ]);
}

$_SESSION["user_id"] = $user_id;
$_SESSION["user_name"] = $user_name;
setcookie("secret_key", $user_secret_key, time() + 86400 * 30, "/");

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

header("Location: /account");