<?php
include_once "../../src/alert.php";
include_once "../../src/utils.php";
include_once "../../src/config.php";
include_once "../../src/accounts.php";

if (!authorize_user(true)) {
    exit;
}

$id = intval(str_safe($_POST["id"] ?? "0", 10));
$rate = intval(str_safe($_POST["rate"] ?? "0", 2));

if ($id == 0 || $rate == 0) {
    generate_alert("/emotes" . (isset($_POST["id"]) ? "?id=" . $_POST["id"] : ""), "Not enough POST fields");
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// checking if emote exists
$stmt = $db->prepare("SELECT id FROM emotes WHERE id = ?");
$stmt->execute([$id]);
if ($stmt->rowCount() != 1) {
    generate_alert("/emotes", "Emote ID $id does not exist", 404);
    exit;
}

// checking if user has already given a rate
$stmt = $db->prepare("SELECT id FROM ratings WHERE user_id = ? AND emote_id = ?");
$stmt->execute([$_SESSION["user_id"], $id]);
if ($stmt->rowCount() != 0) {
    generate_alert("/emotes?id=$id", "You have already given a rate for this emote!", 403);
    exit;
}

// giving a rate
$stmt = $db->prepare("INSERT INTO ratings(user_id, emote_id, rate) VALUES (?, ?, ?)");
$stmt->execute([$_SESSION["user_id"], $id, clamp($rate, -2, 2)]);

if (CLIENT_REQUIRES_JSON) {
    $stmt = $db->prepare("SELECT * FROM ratings WHERE id = ?");
    $stmt->execute([$db->lastInsertId()]);

    json_response([
        "status_code" => 200,
        "message" => "Rated!",
        "data" => $stmt->fetch(PDO::FETCH_ASSOC)
    ]);
    exit;
}

generate_alert("/emotes?id=$id", "Rated!", 200);
