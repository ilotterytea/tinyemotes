<?php
include_once "../../src/accounts.php";
include_once "../../src/config.php";
include_once "../../src/utils.php";
include_once "../../src/alert.php";

if (!REPORTS_ENABLE) {
    generate_alert("/404.php", "Reports are disabled", 403);
    exit;
}

if (!authorize_user(true)) {
    exit;
}

if (isset($_SESSION["user_role"]) && !$_SESSION["user_role"]["permission_report"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if (!isset($_POST["contents"])) {
    generate_alert("/report", "Not enough POST fields");
    exit;
}

$stmt = $db->prepare("INSERT INTO reports(sender_id, contents) VALUES (?, ?)");
$stmt->execute([$_SESSION["user_id"], str_safe($_POST["contents"], 200)]);

$report_id = $db->lastInsertId();

$stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->execute([$report_id]);

if (CLIENT_REQUIRES_JSON) {
    json_response([
        "status_code" => 201,
        "message" => null,
        "data" => $stmt->fetch(PDO::FETCH_ASSOC)
    ], 201);
    exit;
}

generate_alert("/report?id=$report_id", "Thank you for your vigilance! MODS will take action as soon as possible.", 200);
