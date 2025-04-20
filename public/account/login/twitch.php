<?php
include "../../../src/utils.php";

$client_id = "";
$client_secret = "";
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

$db = new SQLite3("../../../database.db");

// creating user if not exists
$stmt = $db->prepare("SELECT id, user_id FROM connections WHERE alias_id = :alias_id AND platform = 'twitch'");
$stmt->bindValue("alias_id", $twitch_user["id"]);

$results = $stmt->execute();

$user_id = "";
$user_secret_key = "";
$user_name = "";

if ($row = $results->fetchArray()) {
    $id = $row["id"];
    $user_id = $row["user_id"];

    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $results = $stmt->execute();

    if ($row = $results->fetchArray()) {
        $user_name = $row["username"];
        $user_secret_key = $row["secret_key"];
        $user_id = $row["id"];
    } else {
        $db->close();
        echo "Connection found, but not user?";
        exit;
    }
} else {
    $user_secret_key = generate_random_string(32);
    $user_name = $twitch_user["login"];

    $stmt = $db->prepare("INSERT INTO users(username, secret_key) VALUES (:username, :secret_key)");
    $stmt->bindValue(":username", $user_name);
    $stmt->bindValue(":secret_key", $user_secret_key);
    if (!$stmt->execute()) {
        $db->close();
        echo "Failed to create a user";
        exit;
    }

    $user_id = $db->lastInsertRowID();

    $stmt = $db->prepare("INSERT INTO connections(user_id, alias_id, platform, data) VALUES (:user_id, :alias_id, 'twitch', :data)");
    $stmt->bindValue(":user_id", $user_id);
    $stmt->bindValue(":alias_id", $twitch_user["id"]);
    $stmt->bindValue(
        ":data",
        $_SESSION["twitch_access_token"] . ":" . $_SESSION["twitch_refresh_token"] . ":" . $_SESSION["twitch_expires_on"]
    );
    $stmt->execute();
}

$_SESSION["user_id"] = $user_id;
$_SESSION["user_name"] = $user_name;
setcookie("secret_key", $user_secret_key, time() + 86400 * 30, "/");

$db->close();

// downloading profile picture
$path = "../../static/userdata/avatars";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

$fp = fopen("$path/$user_id", "wb");
$request = curl_init();
curl_setopt($request, CURLOPT_URL, $twitch_user["profile_image_url"]);
curl_setopt($request, CURLOPT_FILE, $fp);
curl_setopt($request, CURLOPT_HEADER, 0);

curl_exec($request);
curl_close($request);
fclose($fp);

header("Location: /account");