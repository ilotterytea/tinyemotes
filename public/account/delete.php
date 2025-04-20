<?php
include "../../src/utils.php";

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /account");
    exit;
}

$id = $_SESSION["user_id"];

$db = new SQLite3("../../database.db");

$stmt = $db->prepare("UPDATE emotes SET uploaded_by = NULL WHERE uploaded_by = :id");
$stmt->bindValue(":id", $id);
$stmt->execute();

$stmt = $db->prepare("DELETE FROM connections WHERE user_id = :id");
$stmt->bindValue(":id", $id);
$stmt->execute();

$stmt = $db->prepare("DELETE FROM users WHERE id = :id");
$stmt->bindValue(":id", $id);
$stmt->execute();

session_unset();
session_destroy();

setcookie("secret_key", "", time() - 1000);

$db->close();

$path = "../static/userdata/avatars/$id";
if (is_file($path)) {
    unlink($path);
}

header("Location: /account");