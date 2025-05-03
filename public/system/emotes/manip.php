<?php
include_once "../../../src/alert.php";
include_once "../../../src/accounts.php";
include_once "../../../src/config.php";
include_once "../../../src/utils.php";

if (!MOD_EMOTES_APPROVE) {
    generate_alert("/404.php", "Manual emote approval is disabled", 405);
    exit;
}

if (!authorize_user(true) || !$_SESSION["user_role"]["permission_approve_emotes"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

if (!isset($_POST["id"], $_POST["action"])) {
    generate_alert("/system/emotes", "Not enough POST fields");
    exit;
}

$id = str_safe($_POST["id"], 32);
$action = $_POST["action"];

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT id, code, uploaded_by FROM emotes WHERE id = ? AND visibility = 2 LIMIT 1");
$stmt->execute([$id]);

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $verdict = 2;

    switch ($action) {
        case "approve": {
            $db->prepare("UPDATE emotes SET visibility = 1 WHERE id = ?")
                ->execute([$row["id"]]);
            $verdict = 1;
            break;
        }
        case "reject": {
            $db->prepare("UPDATE emotes SET visibility = 0 WHERE id = ?")
                ->execute([$row["id"]]);
            $verdict = 0;
            break;
        }
        default: {
            generate_alert("/system/emotes", "Unknown action");
            exit;
        }
    }

    $comment = str_safe($_POST["comment"] ?? "", EMOTE_COMMENT_MAX_LENGTH, false);

    if ($comment == "") {
        $comment = null;
    }

    $db->prepare("INSERT INTO mod_actions(user_id, emote_id, verdict, comment) VALUES (?, ?, ?, ?)")
        ->execute([$_SESSION["user_id"], $row["id"], $verdict, $comment]);

    if ($row["uploaded_by"] != null) {
        $contents = match ($verdict) {
            0 => 'Your emote "' . $row["code"] . '" has been unlisted! Anyone can add it via a direct link.',
            1 => 'Your emote "' . $row["code"] . '" has been approved! Enjoy!',
            default => 'We did something with your emote "' . $row["code"] . '"'
        };

        $db->prepare("INSERT INTO inbox_messages(recipient_id, message_type, contents, link) VALUES (?, ?, ?, ?)")
            ->execute([$row["uploaded_by"], "1", $contents, "/emotes?id=" . $row["id"]]);
    }

    generate_alert("/system/emotes", 'Emote "' . $row["code"] . '" has been ' . ($verdict == 0 ? 'rejected (unlisted)' : 'approved (public)') . '!', 200);
    exit;
}

generate_alert("system/emotes", "Emote ID $id not found", 404);