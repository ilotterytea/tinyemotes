<?php

include_once "../../src/accounts.php";
include_once "../../src/alert.php";
include_once "../../src/config.php";

if ($_SERVER["REQUEST_METHOD"] != "POST" || !authorize_user(true)) {
    header("Location: /account");
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION["user_id"]]);

$user = $stmt->fetch();
$current_password = $_POST["password-current"] ?? "";

if ($user["password"] != null && !password_verify($current_password, $user["password"])) {
    generate_alert("/account", "Password is required to apply changes in 'Security' section");
    exit;
}

if (!empty($_POST["password-new"])) {
    $password = $_POST["password-new"];
    if (ACCOUNT_PASSWORD_MIN_LENGTH > strlen($password)) {
        generate_alert("/account", "Your password must be at least " . ACCOUNT_PASSWORD_MIN_LENGTH . " characters");
        exit;
    }

    $db->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_DEFAULT), $user["id"]]);
}

$hide_actions = (int) (intval($_POST["hide-actions"] ?? "0") == 1);

$db->prepare("UPDATE user_preferences SET hide_actions = ? WHERE id = ?")
    ->execute([$hide_actions, $user["id"]]);

if (intval($_POST["signout-everywhere"] ?? "0") == 1) {
    $db->prepare("UPDATE users SET secret_key = ? WHERE id = ?")
        ->execute([generate_random_string(ACCOUNT_SECRET_KEY_LENGTH), $_SESSION["user_id"]]);

    session_unset();
    session_destroy();

    setcookie("secret_key", "", time() - 1000);
}

generate_alert("/account", "Your changes have been applied!", 200);