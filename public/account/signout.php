<?php
include "../../src/utils.php";

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /account");
    exit;
}

$db = new SQLite3("../../database.db");

$stmt = $db->prepare("UPDATE users SET secret_key = :secret_key WHERE id = :id");
$stmt->bindValue(":id", $_SESSION["user_id"]);
$stmt->bindValue(":secret_key", generate_random_string(32));
$stmt->execute();

session_unset();
session_destroy();

setcookie("secret_key", "", time() - 1000);

$db->close();

header("Location: /account");