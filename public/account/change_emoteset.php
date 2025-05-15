<?php
include_once "../../src/config.php";
include_once "../../src/alert.php";
include_once "../../src/accounts.php";

if (!authorize_user(true)) {
    generate_alert("/404.php", "Unauthorized", 401);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    generate_alert("/404.php", "Method not allowed", 405);
    exit;
}

if (!isset($_POST["id"])) {
    generate_alert("/404.php", "Emote set ID is not provided");
    exit;
}

$emote_set_id = $_POST["id"];
$user_id = $_SESSION["user_id"];

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT id FROM acquired_emote_sets WHERE emote_set_id = ? AND user_id = ?");
$stmt->execute([$emote_set_id, $user_id]);

if ($stmt->rowCount() == 0) {
    generate_alert("/404.php", "You don't own emote set ID $emote_set_id", 403);
    exit;
}

$_SESSION["user_active_emote_set_id"] = $emote_set_id;

header("Location: " . $_POST["redirect"] ?? "/");