<?php
include_once "../../src/config.php";
include "../../src/accounts.php";
include "../../src/alert.php";
include_once "../../src/utils.php";

if (!authorize_user(true)) {
    return;
}

if (isset($_SESSION["user_role"]) && !$_SESSION["user_role"]["permission_emoteset_own"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

if (!isset($_POST["id"], $_POST["action"], $_POST["emote_set_id"])) {
    generate_alert("/emotes", "Not enough POST fields");
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// checking emote
$emote_id = $_POST["id"];
$stmt = $db->prepare("SELECT id, code, uploaded_by, visibility, created_at FROM emotes WHERE id = ?");
$stmt->execute([$emote_id]);
if ($stmt->rowCount() == 0) {
    generate_alert("/emotes", "Emote not found", 404);
    exit;
}
$emote = $stmt->fetch(PDO::FETCH_ASSOC);

$user_id = $_SESSION["user_id"];
$emote_set_id = $_POST["emote_set_id"];

// checking emote set
$stmt = $db->prepare("SELECT id FROM acquired_emote_sets WHERE emote_set_id = ? AND user_id = ?");
$stmt->execute([$emote_set_id, $user_id]);

if ($stmt->rowCount() == 0) {
    generate_alert("/404.php", "You don't own emote set ID $emote_set_id", 403);
    exit;
}

// inserting emote
$stmt = $db->prepare("SELECT id FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
$stmt->execute([$emote_set_id, $emote_id]);

$action = $_POST["action"];
$payload = [
    "emote" => $emote,
    "emoteset" => $_SESSION["user_active_emote_set"]
];

switch ($action) {
    case "add": {
        if ($stmt->rowCount() != 0) {
            generate_alert("/emotes?id=$emote_id", "This emote has been already added!");
            exit;
        }

        $stmt = $db->prepare("INSERT INTO emote_set_contents(emote_set_id, emote_id, added_by) VALUES (?, ?, ?)");
        $stmt->execute([$emote_set_id, $emote_id, $user_id]);

        if (ACCOUNT_LOG_ACTIONS) {
            $db->prepare("INSERT INTO actions(user_id, action_type, action_payload) VALUES (?, ?, ?)")
                ->execute([$user_id, "EMOTESET_ADD", json_encode($payload)]);
        }

        $db = null;

        generate_alert("/emotes?id=$emote_id", "This emote has been added to your set. Enjoy!", 200);
        break;
    }
    case "remove": {
        if ($row = $stmt->fetch()) {
            $stmt = $db->prepare("DELETE FROM emote_set_contents WHERE id = ?");
            $stmt->execute([$row["id"]]);
        } else {
            generate_alert("/emotes?id=$emote_id", "This emote wasn't added!");
            $db = null;
            exit;
        }

        if (ACCOUNT_LOG_ACTIONS) {
            $db->prepare("INSERT INTO actions(user_id, action_type, action_payload) VALUES (?, ?, ?)")
                ->execute([$user_id, "EMOTESET_REMOVE", json_encode($payload)]);
        }

        $db = null;

        generate_alert("/emotes?id=$emote_id", "This emote has been removed from your set.", 200);
        break;
    }
    case "alias": {
        if (!isset($_POST["value"])) {
            generate_alert("/emotes?id=$emote_id", "No value field");
            exit;
        }

        $value = str_safe($_POST["value"], EMOTE_NAME_MAX_LENGTH);

        $stmt = $db->prepare("SELECT esc.code AS alias_code, e.code FROM emote_set_contents esc
                INNER JOIN emotes e ON e.id = esc.emote_id
                WHERE esc.emote_set_id = ? AND esc.emote_id = ?");
        $stmt->execute([$emote_set_id, $emote_id]);

        if (empty($value)) {
            $value = null;

            if ($row = $stmt->fetch()) {
                $payload["emote"]["original_code"] = $row["alias_code"];
                $payload["emote"]["code"] = $row["code"];
            }
        } else {
            $row = $stmt->fetch();
            $payload["emote"]["original_code"] = $row["alias_code"] ?? $row["code"];
            $payload["emote"]["code"] = $value;
        }

        $stmt = $db->prepare("UPDATE emote_set_contents SET code = ? WHERE emote_set_id = ? AND emote_id = ?");
        $stmt->execute([$value, $emote_set_id, $emote_id]);

        if (ACCOUNT_LOG_ACTIONS) {
            $db->prepare("INSERT INTO actions(user_id, action_type, action_payload) VALUES (?, ?, ?)")
                ->execute([$user_id, "EMOTESET_ALIAS", json_encode($payload)]);
        }

        $db = null;

        generate_alert("/emotes?id=$emote_id", "Updated emote name!", 200);
        break;
    }
    default: {
        generate_alert("/emotes?id=$emote_id", "Unknown action");
        break;
    }
}