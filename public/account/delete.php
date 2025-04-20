<?php
include "../../src/utils.php";
include_once "../../src/config.php";

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /account");
    exit;
}

$id = $_SESSION["user_id"];

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("UPDATE emotes SET uploaded_by = NULL WHERE uploaded_by = ?");
$stmt->execute([$id]);

$stmt = $db->prepare("DELETE FROM connections WHERE user_id = ?");
$stmt->execute([$id]);

$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

session_unset();
session_destroy();

setcookie("secret_key", "", time() - 1000);

$db = null;

$path = "../static/userdata/avatars/$id";
if (is_file($path)) {
    unlink($path);
}

header("Location: /account");