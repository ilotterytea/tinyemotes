<?php
include "../../src/emote.php";
include "../../src/accounts.php";
include_once "../../src/config.php";

authorize_user();

function display_list_emotes(int $page, int $limit): array
{
    $offset = $page * $limit;
    $db = new PDO(DB_URL, DB_USER, DB_PASS);
    $stmt = $db->prepare("SELECT * FROM emotes ORDER BY created_at ASC LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $emotes = [];

    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        array_push($emotes, new Emote(
            $row["id"],
            $row["code"],
            $row["ext"],
            intval(strtotime($row["created_at"])),
            $row["uploaded_by"]
        ));
    }

    return $emotes;
}

function display_emote(int $id)
{
    $db = new PDO(DB_URL, DB_USER, DB_PASS);
    $stmt = $db->prepare("SELECT * FROM emotes WHERE id = ?");
    $stmt->execute([$id]);

    $emote = null;

    if ($row = $stmt->fetch()) {
        $emote = new Emote(
            $row["id"],
            $row["code"],
            $row["ext"],
            intval(strtotime($row["created_at"])),
            $row["uploaded_by"]
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

include "../../src/partials.php";

if ($id == "" || !is_numeric($id)) {
    $page = intval($_GET["p"] ?? "0");
    $limit = 50;
    $emotes = display_list_emotes($page, $limit);
    include "../../src/emotes/multiple_page.php";
} else {
    $emote = display_emote(intval($id));
    include "../../src/emotes/single_page.php";
}
