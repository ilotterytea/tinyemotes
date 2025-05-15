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
    $emotes = fetch_all_emotes_from_emoteset($db, $emote_set["id"], $user_id, null);
    $emote_set["emotes"] = $emotes;
} elseif (!EMOTESET_PUBLIC_LIST) {
    generate_alert("/404.php", "The public list of emotesets is disabled", 403);
    exit;
} else {
    $emote_sets = [];
    foreach ($db->query("SELECT es.* FROM emote_sets es", PDO::FETCH_ASSOC) as $row) {
        $emote_set_row = $row;
        $emote_set_row["emotes"] = fetch_all_emotes_from_emoteset(
            $db,
            $emote_set_row["id"],
            $user_id,
            5
        );
        array_push($emote_sets, $emote_set_row);
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
            false => 'Emoteset - ' . $emote_set["name"],
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
                        <div class="box navtab">
                            <?php echo $title ?>
                        </div>
                        <div class="box content small-gap items">
                            <?php
                            if (!empty($emote_sets)) {
                                foreach ($emote_sets as $set_row) {
                                    ?>
                                    <a href="/emotesets.php?id=<?php echo $set_row["id"] ?>" class="box">
                                        <div>
                                            <p><?php echo $set_row["name"] ?></p>
                                        </div>

                                        <div>
                                            <?php
                                            foreach ($set_row["emotes"] as $emm) {
                                                echo '<img src="/static/userdata/emotes/' . $emm["id"] . '/1x.webp" height="' . EMOTE_MAX_SIZE[1] / 4 . '">';
                                            }
                                            ?>
                                        </div>
                                    </a>
                                <?php }

                                echo '</div></section>';
                            } else if (!empty($emote_set)) {
                                foreach ($emote_set["emotes"] as $emote_row) {
                                    echo '<a class="box emote" href="/emotes?id=' . $emote_row["id"] . '">';
                                    echo '<img src="/static/userdata/emotes/' . $emote_row["id"] . '/2x.webp" alt="' . $emote_row["code"] . '" />';
                                    echo '<h1>' . $emote_row["code"] . '</h1>';
                                    echo '<p>' . ($emote_row["uploaded_by"] == null ? (ANONYMOUS_DEFAULT_NAME . "*") : $emote_row["uploaded_by"]["username"]) . '</p>';
                                    echo '</a>';
                                }
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