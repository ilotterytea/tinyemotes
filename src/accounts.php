<?php
include_once "config.php";

function authorize_user()
{
    session_start();

    if (!isset($_COOKIE["secret_key"])) {
        if (isset($_SESSION["user_id"])) {
            session_unset();
        }

        return;
    }

    include_once "config.php";

    $db = new PDO(DB_URL, DB_USER, DB_PASS);

    $stmt = $db->prepare("SELECT id, username FROM users WHERE secret_key = ?");
    $stmt->execute([$_COOKIE["secret_key"]]);

    if ($row = $stmt->fetch()) {
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["user_name"] = $row["username"];
    } else {
        session_regenerate_id();
        setcookie("secret_key", "", time() - 1000);
    }

    $db = null;
}