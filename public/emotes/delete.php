<?php
include_once "../../src/alert.php";
include_once "../../src/config.php";
include_once "../../src/accounts.php";

if (!authorize_user(true)) {
    generate_alert("/account", "Not authorized", 403);
    exit;
}

if (!isset($_POST["id"])) {
    generate_alert("/emotes", "Emote ID is not specified");
    exit;
}

$emote_id = $_POST["id"];
$user_id = $_SESSION["user_id"];

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT uploaded_by, code FROM emotes WHERE id = ?");
$stmt->execute([$emote_id]);

if ($row = $stmt->fetch()) {
    if ($row["uploaded_by"] === $user_id) {
        $unlink = intval($_POST["unlink"] ?? "0") == 1;

        if ($unlink) {
            $stmt = $db->prepare("UPDATE emotes SET uploaded_by = NULL WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$emote_id, $user_id]);
            generate_alert("/emotes/?id=$emote_id", 'Your authorship has been removed for the emote "' . $row["code"] . '"', 200);
        } else {
            $stmt = $db->prepare("DELETE FROM emotes WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$emote_id, $user_id]);

            $path = $_SERVER["DOCUMENT_ROOT"] . "/static/userdata/emotes/$emote_id";
            array_map("unlink", glob("$path/*.*"));
            rmdir($path);

            generate_alert("/emotes", 'Emote "' . $row["code"] . '" has been removed from the servers', 200);
        }
    } else {
        generate_alert("/emotes", "You don't own the emote \"" . $row["code"] . "\"", 403);
    }
} else {
    generate_alert("/emotes", "Emote ID $emote_id not found", 404);
}