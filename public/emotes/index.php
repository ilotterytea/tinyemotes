<?php
include "../../src/emote.php";

function display_list_emotes(int $page, int $limit): array
{
    $db = new SQLite3("../../database.db");
    $stmt = $db->prepare("SELECT * FROM emotes ORDER BY created_at ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":offset", $page * $limit, SQLITE3_INTEGER);
    $stmt->bindValue(":limit", $limit, SQLITE3_INTEGER);
    $results = $stmt->execute();

    $emotes = [];

    while ($row = $results->fetchArray()) {
        array_push($emotes, new Emote(
            $row["id"],
            $row["code"],
            $row["ext"],
            intval(strtotime($row["created_at"]))
        ));
    }

    return $emotes;
}

function display_emote(int $id)
{
    $db = new SQLite3("../../database.db");
    $stmt = $db->prepare("SELECT * FROM emotes WHERE id = :id");
    $stmt->bindValue(":id", $id, SQLITE3_INTEGER);
    $results = $stmt->execute();

    if ($row = $results->fetchArray()) {
        $emote = new Emote(
            $row["id"],
            $row["code"],
            $row["ext"],
            intval(strtotime($row["created_at"]))
        );
    }

    if ($emote == null) {
        echo "not found";
        exit;
    }

    return $emote;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
    $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$id = parse_url($current_url, PHP_URL_PATH);
$id = substr($id, 8);
$id = str_replace("/", "", $id);

$emotes = null;
$emote = null;

if ($id == "" || !is_numeric($id)) {
    $page = intval($_GET["p"] ?? "0");
    $limit = 50;
    $emotes = display_list_emotes($page, $limit);
    include "../../src/emotes/multiple_page.php";
} else {
    $emote = display_emote(intval($id));
    include "../../src/emotes/single_page.php";
}
