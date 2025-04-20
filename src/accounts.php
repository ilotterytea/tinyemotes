<?php
function authorize_user()
{
    session_start();

    if (!isset($_COOKIE["secret_key"])) {
        if (isset($_SESSION["user_id"])) {
            session_unset();
        }

        return;
    }

    $db = new SQLite3("../../database.db");

    $stmt = $db->prepare("SELECT id, username FROM users WHERE secret_key = :secret_key");
    $stmt->bindValue("secret_key", $_COOKIE["secret_key"]);
    $results = $stmt->execute();

    if ($row = $results->fetchArray()) {
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["user_name"] = $row["username"];
    } else {
        session_regenerate_id();
        setcookie("secret_key", "", time() - 1000);
    }

    $db->close();
}