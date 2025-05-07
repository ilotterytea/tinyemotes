<?php
include_once "config.php";

function authorize_user(bool $required = false): bool
{
    session_start();

    if (!isset($_SESSION["captcha_solved"]) && !CLIENT_REQUIRES_JSON) {
        header("Location: /captcha.php");
        exit;
    }

    if (!isset($_COOKIE["secret_key"]) && !isset($_SERVER["HTTP_AUTHORIZATION"])) {
        if (isset($_SESSION["user_id"])) {
            session_unset();
        }

        if ($required) {
            if (isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json") {
                http_response_code(401);
                echo json_encode([
                    "status_code" => 401,
                    "message" => "Unauthorized",
                    "data" => null
                ]);
            } else {
                header("Location: /account");
            }
        }

        return false;
    }

    include_once "config.php";

    $db = new PDO(DB_URL, DB_USER, DB_PASS);

    $key = $_SERVER["HTTP_AUTHORIZATION"] ?? $_COOKIE["secret_key"];

    $stmt = $db->prepare("SELECT id, username FROM users WHERE secret_key = ?");
    $stmt->execute([$key]);

    if ($row = $stmt->fetch()) {
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["user_name"] = $row["username"];

        $stmt = $db->prepare("UPDATE users SET last_active_at = UTC_TIMESTAMP WHERE id = ?");
        $stmt->execute([$row["id"]]);

        // fetching role
        $stmt = $db->prepare("SELECT * FROM roles r
        INNER JOIN role_assigns ra ON ra.user_id = ?
        WHERE r.id = ra.role_id
        ");
        $stmt->execute([$row["id"]]);

        $_SESSION["user_role"] = null;

        if ($role_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION["user_role"] = $role_row;
        }

        $stmt = $db->prepare("SELECT es.* FROM emote_sets es
            INNER JOIN acquired_emote_sets aes ON aes.emote_set_id = es.id
            WHERE aes.user_id = ? AND aes.is_default = TRUE
            ");
        $stmt->execute([$row["id"]]);

        $_SESSION["user_active_emote_set"] = null;

        if ($emote_set_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION["user_active_emote_set"] = $emote_set_row;
        }
    } else {
        session_regenerate_id();
        session_unset();
        setcookie("secret_key", "", time() - 1000);

        if ($required) {
            if (isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json") {
                http_response_code(401);
                echo json_encode([
                    "status_code" => 401,
                    "message" => "Unauthorized",
                    "data" => null
                ]);
            } else {
                header("Location: /account");
            }
        }
    }

    $db = null;
    $stmt = null;
    return isset($_SESSION["user_name"]);
}