<?php
include_once "../src/utils.php";
include_once "../src/config.php";
include_once "../src/accounts.php";
include_once "../src/partials.php";
authorize_user();

$id = $_GET["id"] ?? "";
$alias_id = $_GET["alias_id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$emote_sets = null;
$emote_set = null;

if ($id == "global") {
    $stmt = $db->prepare("SELECT * FROM emote_sets WHERE is_global = true");
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT e.* FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_id = e.id
        WHERE emote_set_id = ?");
        $stmt->execute([$emote_set["id"]]);

        $emote_set["emotes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emote_set["emotes"] as &$e) {
            if ($uploader_id = $e["uploaded_by"]) {
                $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
                $stmt->execute([$uploader_id]);
                $e["uploaded_by"] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} else if (intval($id) <= 0 && intval($alias_id) <= 0) {
    $page = intval($_GET["p"] ?? "0");
    $limit = 20;
    $offset = $page * $limit;

    $stmt = $db->prepare("SELECT * FROM emote_sets LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $emote_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emote_sets as &$e) {
        $stmt = $db->prepare("SELECT e.* FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_set_id = ?
        WHERE e.id = esc.emote_id
        LIMIT 5");
        $stmt->execute([$e["id"]]);

        $e["emotes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else if (intval($alias_id) > 0) {
    $alias_id = intval($alias_id);
    $stmt = $db->prepare("SELECT es.* FROM emote_sets es
    INNER JOIN connections co ON co.alias_id = ?
    WHERE co.user_id = es.owner_id
    ");
    $stmt->execute([$alias_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT e.* FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_set_id = ?
        WHERE esc.emote_id = e.id");
        $stmt->execute([$emote_set["id"]]);

        $emote_set["emotes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emote_set["emotes"] as &$e) {
            if ($e["uploaded_by"]) {
                $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
                $stmt->execute([$e["uploaded_by"]]);
                $e["uploaded_by"] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} else {
    $id = intval($id);
    $stmt = $db->prepare("SELECT * FROM emote_sets WHERE id = ?");
    $stmt->execute([$id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT e.* FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_set_id = ?
        WHERE esc.emote_id = e.id");
        $stmt->execute([$emote_set["id"]]);

        $emote_set["emotes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <?php echo $emote_sets != null ? (count($emote_sets) . " emotesets") : ('"' . $emote_set["name"] . '" emoteset') ?>
        - alright.party
    </title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <section class="content row">
                <section class="sidebar">
                    <?php html_navigation_search() ?>
                </section>
                <section class="content">
                    <section class="box">
                        <div class="box navtab">
                            <?php echo $emote_sets != null ? (count($emote_sets) . " emotesets") : ('"' . $emote_set["name"] . '" emoteset') ?>
                        </div>
                        <div class="box content items">
                            <?php
                            if ($emote_sets != null) {
                                foreach ($emote_sets as $set_row) { ?>
                                    <a href="/emotesets.php?id=<?php echo $set_row["id"] ?>" class="box">
                                        <div>
                                            <?php
                                            echo '<p>' . $set_row["name"] . '</p>';

                                            if ($set_row["size"]) {
                                                echo '<p class="circled black">' . $set_row["size"] . '</p>';
                                            }
                                            ?>
                                        </div>

                                        <div>
                                            <?php
                                            foreach ($set_row["emotes"] as $e) {
                                                echo '<img src="/static/userdata/emotes/' . $e["id"] . '/1x.' . $e["ext"] . '">';
                                            }
                                            ?>
                                        </div>
                                    </a>
                                <?php }
                            } else {
                                foreach ($emote_set["emotes"] as $emote_row) {
                                    echo '<a class="box emote" href="/emotes?id=' . $emote_row["id"] . '">';
                                    echo '<img src="/static/userdata/emotes/' . $emote_row["id"] . '/2x.' . $emote_row["ext"] . '" alt="' . $emote_row["code"] . '"/>';
                                    echo '<p>' . $emote_row["code"] . '</p>';
                                    echo '</a>';
                                }
                            }
                            ?>
                        </div>
                    </section>
                </section>
            </section>
        </div>
    </div>
</body>

</html>