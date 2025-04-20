<?php
include_once "../../src/config.php";
include "../../src/accounts.php";
include "../../src/alert.php";

if (!authorize_user(true)) {
    return;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// checking emote
$emote_id = $_POST["id"];
$stmt = $db->prepare("SELECT id FROM emotes WHERE id = ?");
$stmt->execute([$emote_id]);
if ($stmt->rowCount() == 0) {
    generate_alert("/emotes/$emote_id", "Emote not found", 404);
    exit;
}

$user_id = $_SESSION["user_id"];

// obtaining or creating a emote set
$stmt = $db->prepare("SELECT emote_set_id FROM acquired_emote_sets WHERE user_id = ? AND is_default = true");
$stmt->execute([$user_id]);
$emote_set_id = null;

if ($row = $stmt->fetch()) {
    $emote_set_id = $row["emote_set_id"];

    // checking ownership
    $stmt = $db->prepare("SELECT id FROM emote_sets WHERE id = ? AND owner_id = ?");
    $stmt->execute([$emote_set_id, $user_id]);

    if ($stmt->rowCount() == 0) {
        $_SESSION["user_emote_set_id"] = "";
        generate_alert("/emotes/$emote_id", "Bad ownership permissions on active emoteset", 403);
        exit;
    }
}

if ($emote_set_id == null) {
    $stmt = $db->prepare("INSERT INTO emote_sets(owner_id, name) VALUES (?, ?)");
    $stmt->execute([$user_id, $_SESSION["user_name"] . "'s emoteset"]);
    $emote_set_id = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO acquired_emote_sets(user_id, emote_set_id, is_default) VALUES (?, ?, true)");
    $stmt->execute([$user_id, $emote_set_id]);
}

$_SESSION["user_emote_set_id"] = $emote_set_id;

// inserting emote
$stmt = $db->prepare("SELECT id FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
$stmt->execute([$emote_set_id, $emote_id]);

if ($stmt->rowCount() != 0) {
    generate_alert("/emotes/$emote_id", "This emote has been already added!");
    exit;
}

$stmt = $db->prepare("INSERT INTO emote_set_contents(emote_set_id, emote_id, added_by) VALUES (?, ?, ?)");
$stmt->execute([$emote_set_id, $emote_id, $user_id]);

$db = null;

generate_alert("/emotes/$emote_id", "This emote has been added to your set. Enjoy!", 200);