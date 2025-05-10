<?php
include_once $_SERVER["DOCUMENT_ROOT"] . '/../src/accounts.php';
include_once $_SERVER["DOCUMENT_ROOT"] . '/../src/alert.php';

if (!isset($_GET["local"])) {
    header("Location: /");
    exit;
}

session_start();

setcookie("secret_key", "", time() - 1000, "/");
session_unset();
session_destroy();

generate_alert("/", "Signed out!", 200);