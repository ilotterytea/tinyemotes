<?php
include "../../src/utils.php";
include_once "../../src/config.php";

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /account");
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("UPDATE users SET secret_key = ? WHERE id = ?");
$stmt->execute([generate_random_string(32), $_SESSION["user_id"]]);

session_unset();
session_destroy();

setcookie("secret_key", "", time() - 1000);

$db = null;

header("Location: /account");