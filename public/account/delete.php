<?php
include "../../src/utils.php";
include_once "../../src/config.php";

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /account");
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$id = $_SESSION["user_id"];

$profile = ($_GET["profile"] ?? "false") == "true";
$pfp = ($_GET["pfp"] ?? "false") == "true";
$banner = ($_GET["banner"] ?? "false") == "true";
$badge = ($_GET["badge"] ?? "false") == "true";

if ($pfp || $profile) {
    $path = "../static/userdata/avatars/$id";
    if (is_dir($path)) {
        array_map("unlink", glob("$path/*.*"));
        rmdir($path);
    }
}

if ($banner || $profile) {
    $path = "../static/userdata/banners/$id";
    if (is_dir($path)) {
        array_map("unlink", glob("$path/*.*"));
        rmdir($path);
    }
}

if ($badge || $profile) {
    $db->prepare("DELETE FROM user_badges WHERE user_id = ?")->execute([$id]);
}

if ($profile) {
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    session_unset();
    session_destroy();

    setcookie("secret_key", "", time() - 1000);
}

header("Location: /account");