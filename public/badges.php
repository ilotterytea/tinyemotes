<?php
include_once "../src/utils.php";
include_once "../src/config.php";
include_once "../src/user.php";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT
    u.id, u.username,
    r.name AS role_name,
    r.badge_id AS role_badge_id,
    ub.badge_id AS custom_badge_id,
    co.alias_id AS connection_alias_id,
    co.platform AS connection_platform
    FROM users u
    JOIN role_assigns ra ON ra.user_id = u.id
    JOIN roles r ON r.id = ra.role_id
    LEFT JOIN user_badges ub ON ub.user_id = u.id
    LEFT JOIN connections co ON co.user_id = u.id
    WHERE r.badge_id IS NOT NULL OR ub.badge_id IS NOT NULL
");
$stmt->execute();

$rows = $stmt->fetchAll();

$badges = [];

foreach ($rows as $row) {
    $badge = [
        "id" => $row["id"],
        "username" => $row["username"],
        "role" => Role::from_array($row),
        "custom_badge" => Badge::from_array($row, "custom"),
        "connection" => match (isset($row["connection_alias_id"], $row["connection_platform"])) {
            true => [
                "alias_id" => $row["connection_alias_id"],
                "platform" => $row["connection_platform"]
            ],
            false => null
        }
    ];

    array_push($badges, $badge);
}

json_response([
    "status_code" => 200,
    "message" => null,
    "data" => $badges
]);