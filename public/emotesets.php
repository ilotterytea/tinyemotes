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

$page = max(1, intval($_GET["p"] ?? "0"));
$total_emotesets = 1;
$total_pages = 1;

if ($id == "global") {
    $stmt = $db->prepare("SELECT * FROM emote_sets WHERE is_global = true");
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT 
        e.*, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code
        FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_id = e.id
        WHERE esc.emote_set_id = ?");
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
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("SELECT * FROM emote_sets LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $emote_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emote_sets as &$e) {
        $stmt = $db->prepare("SELECT e.*, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code
        FROM emotes e
        JOIN emote_set_contents esc ON esc.emote_set_id = ?
        WHERE e.id = esc.emote_id");
        $stmt->execute([$e["id"]]);

        $e["emotes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($e["emotes"] as &$em) {
            if ($em["uploaded_by"]) {
                $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
                $stmt->execute([$em["uploaded_by"]]);
                $em["uploaded_by"] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM emote_sets");
    $count_stmt->execute();
    $total_emotesets = intval($count_stmt->fetch()[0]);
    $total_pages = ceil($total_emotesets / $limit);
} else if (intval($alias_id) > 0) {
    $alias_id = intval($alias_id);
    $stmt = $db->prepare("SELECT es.* FROM emote_sets es
    INNER JOIN connections co ON co.alias_id = ?
    WHERE co.user_id = es.owner_id
    ");
    $stmt->execute([$alias_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT e.*, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code
        FROM emotes e
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
    $stmt = $db->prepare("SELECT * FROM emote_sets WHERE id = ?");
    $stmt->execute([$id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emote_set = $row;

        $stmt = $db->prepare("SELECT e.*, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code
        FROM emotes e
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
        echo $emote_sets != null ? (count($emote_sets) . " emotesets") : ('"' . $emote_set["name"] . '" emoteset');
        echo ' - ' . INSTANCE_NAME;
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
                            <?php echo $emote_set != null ? ('"' . $emote_set["name"] . '" emoteset') : "$total_emotesets emotesets - Page $page/$total_pages" ?>
                        </div>
                        <div class="box content items">
                            <?php
                            if (!empty($emote_sets)) {
                                foreach ($emote_sets as $set_row) {
                                    ?>
                                    <a href="/emotesets.php?id=<?php echo $set_row["id"] ?>" class="box">
                                        <div>
                                            <?php
                                            echo '<p>' . $set_row["name"] . '</p>';
                                            ?>
                                        </div>

                                        <div>
                                            <?php
                                            foreach ($set_row["emotes"] as $emm) {
                                                echo '<img src="/static/userdata/emotes/' . $emm["id"] . '/1x.webp">';
                                            }
                                            ?>
                                        </div>
                                    </a>
                                <?php }

                                echo '</div></section>';

                                if ($total_pages > 1) {
                                    echo '' ?>
                                    <section class="box center row">
                                        <?php
                                        html_pagination($total_pages, $page, "/emotesets.php");
                                        ?>
                                        <?php
                                }
                            } else if (!empty($emote_set)) {
                                foreach ($emote_set["emotes"] as $emote_row) {
                                    echo '<a class="box emote" href="/emotes?id=' . $emote_row["id"] . '">';
                                    echo '<img src="/static/userdata/emotes/' . $emote_row["id"] . '/2x.webp" alt="' . $emote_row["code"] . '"/>';
                                    echo '<h1>' . $emote_row["code"] . '</h1>';
                                    echo '<p>' . ($emote_row["uploaded_by"] == null ? (ANONYMOUS_DEFAULT_NAME . "*") : $emote_row["uploaded_by"]["username"]) . '</p>';
                                    echo '</a>';
                                }
                            } else {
                                echo 'No emotesets found...';
                            }
                            ?>
                            </section>
                    </section>
                </section>
        </div>
    </div>
</body>

</html>