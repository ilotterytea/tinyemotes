<?php
include_once "../src/utils.php";
include_once "../src/config.php";
include_once "../src/accounts.php";
include_once "../src/partials.php";
include_once "../src/alert.php";
include_once "../src/emote.php";

authorize_user();

$id = $_GET["id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// searching requested emoteset
$emote_set = null;

// global emoteset
if ($id == "global") {
    $rows = $db->query("SELECT * FROM emote_sets WHERE is_global = TRUE LIMIT 1", PDO::FETCH_ASSOC);

    if ($rows->rowCount()) {
        $emote_set = $rows->fetch();
    } else {
        generate_alert("/404.php", "Global emoteset is not found", 404);
        exit;
    }
}
// featured emoteset
else if ($id == "featured") {
    $rows = $db->query("SELECT * FROM emote_sets WHERE is_featured = TRUE LIMIT 1", PDO::FETCH_ASSOC);

    if ($rows->rowCount()) {
        $emote_set = $rows->fetch();
    } else {
        generate_alert("/404.php", "Featured emoteset is not found", 404);
        exit;
    }
}
// connected emoteset
else if (isset($_GET["alias_id"])) {
    $alias_id = $_GET["alias_id"];
    $platform = $_GET["platform"] ?? "twitch";

    $stmt = $db->prepare("SELECT es.* FROM emote_sets es
        INNER JOIN connections co ON co.alias_id = ? AND co.platform = ?
        INNER JOIN acquired_emote_sets aes ON aes.user_id = co.user_id
        WHERE aes.is_default = TRUE
    ");
    $stmt->execute([$alias_id, $platform]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;
    } else {
        generate_alert("/404.php", "Emoteset is not found for alias ID $alias_id ($platform)", 404);
        exit;
    }
}
// specified emoteset
else if (!empty($id)) {
    $stmt = $db->prepare("SELECT es.* FROM emote_sets es WHERE es.id = ?");
    $stmt->execute([$id]);

    if ($row = $stmt->fetch()) {
        $emote_set = $row;
    } else {
        generate_alert("/404.php", "Emoteset ID $id is not found", 404);
        exit;
    }
}

$user_id = $_SESSION["user_id"] ?? "";
$emote_sets = null;

// fetching emotes
if ($emote_set) {
    $emote_set = Emoteset::from_array_extended($emote_set, $user_id, $db);
} elseif (!EMOTESET_PUBLIC_LIST) {
    generate_alert("/404.php", "The public list of emotesets is disabled", 403);
    exit;
} else {
    $emote_sets = [];
    foreach ($db->query("SELECT * FROM emote_sets", PDO::FETCH_ASSOC) as $row) {
        array_push($emote_sets, Emoteset::from_array_extended($row, $user_id, $db));
    }
}

if (CLIENT_REQUIRES_JSON) {
    if ($emote_sets != null) {
        json_response([
            "status_code" => 200,
            "message" => null,
            "data" => $emote_sets
        ]);
        exit;
    } else if ($emote_set != null) {
        json_response([
            "status_code" => 200,
            "message" => null,
            "data" => $emote_set
        ]);
        exit;
    } else {
        json_response([
            "status_code" => 404,
            "message" => "Emoteset(s) not found",
            "data" => null
        ], 404);
        exit;
    }
}
?>
<html>

<head>
    <title>
        <?php
        $title = match ($emote_set == null) {
            true => count($emote_sets) . ' emotesets',
            false => "Emoteset - {$emote_set->name}",
        };

        echo "$title - " . INSTANCE_NAME;
        ?>
    </title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <section class="content row">
                <section class="content">
                    <section class="box">
                        <div class="box navtab row">
                            <div class="grow">
                                <?php echo $title ?>
                            </div>
                            <?php
                            if (!empty($emote_set)) {
                                html_emotelist_mode();
                            }
                            ?>
                        </div>
                        <div class="box content small-gap items">
                            <?php
                            if (!empty($emote_sets)) {
                                html_display_emoteset($emote_sets);
                            } else if (!empty($emote_set)) {
                                html_display_emotes($emote_set->emotes);
                            } else {
                                echo 'Nothing found...';
                            }
                            ?>
                    </section>
                </section>
            </section>
        </div>
    </div>
</body>

</html>