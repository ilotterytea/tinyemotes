<?php
include "../../src/emote.php";
include "../../src/accounts.php";
include_once "../../src/config.php";

authorize_user();

function display_list_emotes(int $page, int $limit): array
{
    $search = $_GET["q"] ?? "";
    $user_id = $_SESSION["user_id"] ?? "-1";
    $offset = $page * $limit;
    $db = new PDO(DB_URL, DB_USER, DB_PASS);
    $stmt = $db->prepare("SELECT e.*,
    CASE WHEN EXISTS (
        SELECT 1
        FROM emote_set_contents ec
        INNER JOIN emote_sets es ON es.id = ec.emote_set_id
        WHERE ec.emote_id = e.id AND es.owner_id = ?
    ) THEN 1 ELSE 0 END AS is_in_user_set
    FROM emotes e " .
        (($search != "") ? "WHERE e.code LIKE ?" : "")
        .
        "
    ORDER BY e.created_at ASC
    LIMIT ? OFFSET ?
    ");

    if ($search == "") {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    } else {
        $search = "%$search%";
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $search, PDO::PARAM_STR);
        $stmt->bindParam(3, $limit, PDO::PARAM_INT);
        $stmt->bindParam(4, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();

    $emotes = [];

    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        array_push($emotes, new Emote(
            $row["id"],
            $row["code"],
            $row["ext"],
            intval(strtotime($row["created_at"])),
            $row["uploaded_by"],
            $row["is_in_user_set"]
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
            $row["uploaded_by"],
            false
        );
    }

    if ($emote == null) {
        header("Location: /404.php");
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
include "../../src/utils.php";
include "../../src/alert.php";

if ($id == "" || !is_numeric($id)) {
    $page = intval($_GET["p"] ?? "0");
    $limit = 50;
    $emotes = display_list_emotes($page, $limit);
    include "../../src/emotes/multiple_page.php";
} else {
    $emote = display_emote(intval($id));
    include "../../src/emotes/single_page.php";
}
