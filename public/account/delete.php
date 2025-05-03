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
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

session_unset();
session_destroy();

setcookie("secret_key", "", time() - 1000);

$db = null;

$path = "../static/userdata/avatars/$id";
if (is_file($path)) {
    unlink($path);
}

header("Location: /account");