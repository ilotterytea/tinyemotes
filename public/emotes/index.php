<?php
include "../../src/emote.php";
include "../../src/accounts.php";
include_once "../../src/config.php";
include "../../src/partials.php";
include "../../src/utils.php";
include "../../src/alert.php";

authorize_user();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$user_id = $_SESSION["user_id"] ?? "";

$emotes = null;
$emote = null;
$total_emotes = 0;
$total_pages = 0;

// fetching emote by id
if (isset($_GET["id"])) {
    $id = $_GET["id"];

    $stmt = $db->prepare("SELECT e.id, e.code, e.created_at, e.source, e.visibility,
            COALESCE(COUNT(r.rate), 0) as total_rating,
            COALESCE(ROUND(AVG(r.rate), 2), 0) AS average_rating,
            CASE WHEN up.private_profile = FALSE OR up.id = ? THEN e.uploaded_by ELSE NULL END AS uploaded_by
        FROM emotes e
        LEFT JOIN user_preferences up ON up.id = e.uploaded_by
        LEFT JOIN ratings AS r ON r.emote_id = e.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $id]);

    $row = $stmt->fetch();

    if ($row["id"]) {
        // fetching emote tags
        $stmt = $db->prepare("SELECT t.code FROM tags t
                INNER JOIN tag_assigns ta ON ta.emote_id = ?
                WHERE t.id = ta.tag_id
            ");
        $stmt->execute([$row["id"]]);
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tags = array_column($tags, "code");

        $row["tags"] = $tags;
        $row["ext"] = "webp";
        $emote = Emote::from_array_with_user($row, $db);
    } else {
        generate_alert("/404.php", "Emote ID $id does not exists", 404);
        exit;
    }
}
// fetching all emotes
else {
    $sort = $_GET["sort"] ?? "high_ratings";
    $sort = match ($sort) {
        "low_ratings" => "rating ASC",
        "recent" => "e.created_at DESC",
        "oldest" => "e.created_at ASC",
        default => "rating DESC"
    };
    $page = max(1, intval($_GET["p"] ?? "1"));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $search = $_GET["q"] ?? "";

    // fetching emotes
    $stmt = $db->prepare("SELECT e.*,
    CASE WHEN up.private_profile = FALSE OR up.id = ? THEN e.uploaded_by ELSE NULL END AS uploaded_by,
    CASE WHEN EXISTS (
        SELECT 1
        FROM emote_set_contents ec
        INNER JOIN emote_sets es ON es.id = ec.emote_set_id
        JOIN acquired_emote_sets aes ON aes.emote_set_id = es.id
        WHERE ec.emote_id = e.id AND es.id = ?
    ) THEN 1 ELSE 0 END AS is_in_user_set, COALESCE(COUNT(r.rate), 0) AS rating
    FROM emotes e
    LEFT JOIN user_preferences up ON up.id = e.uploaded_by
    LEFT JOIN ratings AS r ON r.emote_id = e.id
    LEFT JOIN tag_assigns ta ON ta.emote_id = e.id
    LEFT JOIN tags t ON t.id = ta.tag_id
    WHERE (t.code = ? OR e.code LIKE ?) AND e.visibility = 1
    GROUP BY 
    e.id, e.code, e.created_at
    ORDER BY $sort
    LIMIT ? OFFSET ?
    ");

    $sql_search = "%$search%";
    $user_emote_set_id = $_SESSION["user_active_emote_set_id"] ?? "";

    $stmt->bindParam(1, $user_id, PDO::PARAM_STR);
    $stmt->bindParam(2, $user_emote_set_id, PDO::PARAM_STR);
    $stmt->bindParam(3, $search, PDO::PARAM_STR);
    $stmt->bindParam(4, $sql_search, PDO::PARAM_STR);
    $stmt->bindParam(5, $limit, PDO::PARAM_INT);
    $stmt->bindParam(6, $offset, PDO::PARAM_INT);

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $emotes = [];

    foreach ($rows as $row) {
        array_push($emotes, Emote::from_array_with_user($row, $db));
    }

    $total_emotes = count($emotes);
    $total_pages = ceil($total_emotes / $limit);
}

if (CLIENT_REQUIRES_JSON) {
    json_response([
        "status_code" => 200,
        "message" => null,
        "data" => $emotes ?? $emote
    ]);
    exit;
}
?>

<html>

<head>
    <title><?php
    echo ($emote != null ? "Emote " . $emote->get_code() : "Emotes") . ' - ' . INSTANCE_NAME
        ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>

            <section class="content row">
                <section class="sidebar">
                    <?php
                    html_navigation_search();
                    html_featured_emote($db);
                    html_random_emote($db);
                    ?>
                </section>
                <section class="content">
                    <?php display_alert() ?>
                    <section class="box">
                        <div class="box navtab row">
                            <?php
                            if ($emote != null) {
                                echo "Emote - " . $emote->get_code();
                                echo '<div class="row small-gap" style="margin-left:auto">';

                                $original_path = "/static/userdata/emotes/" . $emote->get_id();
                                $files = glob($_SERVER["DOCUMENT_ROOT"] . $original_path . "/original.*");

                                if (!empty($files)) {
                                    $filename = basename($files[0]);
                                    echo "<a href='$original_path/$filename' target='_BLANK'><img src='/static/img/icons/emotes/emote.png' alt='[Show original]' title='Show original' /></a>";
                                }

                                $stmt = $db->prepare("
                                    SELECT MAX(es.is_featured) AS is_featured, MAX(es.is_global) AS is_global
                                    FROM emote_sets es
                                    JOIN emote_set_contents esc ON esc.emote_set_id = es.id
                                    JOIN emotes e ON esc.emote_id = e.id
                                    WHERE e.id = ?
                                ");
                                $stmt->execute([$emote->get_id()]);

                                if ($row = $stmt->fetch()) {
                                    if ($row["is_featured"]) {
                                        echo '<img src="/static/img/icons/star.png" title="Featured emote" alt="Featured" />';
                                    }
                                    if ($row["is_global"]) {
                                        echo '<img src="/static/img/icons/world.png" title="Global emote" alt="Global" />';
                                    }
                                }
                                echo '</div>';
                            } else {
                                echo "<div class='grow'>Emotes - Page $page/$total_pages</div>";
                                html_emotelist_mode();
                            }
                            ?>
                        </div>
                        <?php
                        if ($emote != null) { ?>
                            <div class="box content">
                                <div class="emote-showcase items-bottom">
                                    <?php
                                    for ($size = 1; $size < 4; $size++) {
                                        echo '<div class="column items-center small-gap">';

                                        echo '<img src="/static/userdata/emotes/';
                                        echo $emote->get_id();
                                        echo "/{$size}x.webp\"";
                                        echo 'title="' . $emote->get_code() . '" />';

                                        $path = $_SERVER["DOCUMENT_ROOT"] . '/static/userdata/emotes/' . $emote->get_id() . "/{$size}x.webp";

                                        echo '<div class="column items-center">';

                                        if ($file_size = filesize($path)) {
                                            $kb = sprintf("%.2f", $file_size / 1024);
                                            echo "<p class='font-small'>{$kb}KB</p>";
                                        }

                                        if ($image_size = getimagesize($path)) {
                                            echo "<p class='font-small'>$image_size[0]x$image_size[1]</p>";
                                        }

                                        echo '</div></div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </section>
                        <section class="box items row">
                            <?php if (isset($_SESSION["user_id"])) {
                                echo '' ?>
                                <div class="items row left full">
                                    <?php
                                    $added = false;

                                    if (isset($_SESSION["user_active_emote_set_id"])) {
                                        $stmt = $db->prepare("SELECT id, code FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
                                        $stmt->execute([$_SESSION["user_active_emote_set_id"], $emote->get_id()]);

                                        $added = false;

                                        if ($row = $stmt->fetch()) {
                                            $added = true;
                                            $emote_current_name = $row["code"] ?? $emote->get_code();
                                        }
                                    }

                                    if (isset($_SESSION["user_role"]) && $_SESSION["user_role"]["permission_emoteset_own"]) {
                                        echo '' ?>
                                        <form action="/emotes/setmanip.php" method="POST">
                                            <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                                style="display: none;">
                                            <input type="text" name="emote_set_id"
                                                value="<?php echo $_SESSION["user_active_emote_set_id"] ?>" style="display: none;">
                                            <?php
                                            if ($added) {
                                                ?>
                                                <input type="text" name="action" value="remove" style="display: none;">
                                                <button type="submit" class="red">Remove from my channel</button>
                                            </form>
                                            <form action="/emotes/setmanip.php" method="POST" class="row">
                                                <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                                    style="display: none;">
                                                <input type="text" name="emote_set_id"
                                                    value="<?php echo $_SESSION["user_active_emote_set_id"] ?>" style="display: none;">
                                                <input type="text" name="value" id="emote-alias-input"
                                                    value="<?php echo $emote_current_name ?>"
                                                    placeholder="<?php echo $emote->get_code() ?>">
                                                <input type="text" name="action" value="alias" style="display: none;">
                                                <button type="submit" class="transparent"><img src="/static/img/icons/pencil.png"
                                                        alt="Rename" title="Rename"></button>
                                                <?php
                                            } else { ?>
                                                <input type="text" name="action" value="add" style="display: none;">
                                                <button type="submit" class="green">Add to my channel</button>
                                                <?php
                                            }
                                            ?>
                                        </form>
                                        <?php
                                        ;
                                    }
                                    ?>

                                    <?php if ($emote->get_uploaded_by() === $_SESSION["user_id"]): ?>
                                        <form action="/emotes/delete.php" method="post">
                                            <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                                style="display: none;">
                                            <button type="submit" class="transparent">
                                                <img src="/static/img/icons/bin.png" alt="Delete emote" title="Delete emote">
                                            </button>
                                        </form>
                                        <form action="/emotes/delete.php" method="post">
                                            <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                                style="display: none;">
                                            <input type="text" name="unlink" value="1" style="display:none">
                                            <button type="submit" class="transparent">
                                                <img src="/static/img/icons/link_break.png" alt="Remove your authorship"
                                                    title="Remove your authorship">
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="items row right full">
                                    <?php
                                    if (isset($_SESSION["user_role"])) {
                                        if ($_SESSION["user_role"]["permission_rate"]) {
                                            $stmt = $db->prepare("SELECT rate FROM ratings WHERE user_id = ? AND emote_id = ?");
                                            $stmt->execute([$_SESSION["user_id"], $id]);

                                            if ($row = $stmt->fetch()) {
                                                echo 'You gave <img src="/static/img/icons/ratings/' . $row["rate"] . '.png" width="16" height="16"';
                                                echo 'title="' . RATING_NAMES[$row["rate"]] . '">';
                                            } else {
                                                foreach (RATING_NAMES as $key => $value) {
                                                    echo '<form action="/emotes/rate.php" method="POST">';
                                                    echo '<input type="text" name="id" value="' . $emote->get_id() . '"style="display: none;">';
                                                    echo "<input type=\"text\" name=\"rate\" value=\"$key\" style=\"display:none;\">";
                                                    echo '<button type="submit" class="transparent">';
                                                    echo "<img
                                                    src=\"/static/img/icons/ratings/$key.png\" alt=\"$value!\"
                                                    title=\"IT'S A $value!\">";
                                                    echo '</button></form>';
                                                }
                                            }
                                        }
                                        if (REPORTS_ENABLE && $_SESSION["user_role"]["permission_report"]) {
                                            echo "<a class='button red' href='/report?emote_id={$emote->id}'>Report emote</a>";
                                        }
                                    }
                                    ?>
                                </div>
                                <?php
                            } else {
                                echo '' ?>
                                <p><a href="/account/login">Log in</a> to get additional features...</p>
                                <?php
                            }
                            ?>
                        </section>

                        <section class="box">
                            <table class="vertical">
                                <?php if (!empty($emote->get_tags())): ?>
                                    <tr>
                                        <th>Tags</th>
                                        <td>
                                            <?php
                                            foreach ($emote->get_tags() as $tag) {
                                                echo "<a href='/emotes/?q=$tag'>$tag</a> ";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Uploader</th>
                                    <td><?php
                                    $username = ANONYMOUS_DEFAULT_NAME;
                                    $link = "#";
                                    $show_private_badge = false;
                                    $badge = null;
                                    $custom_badge = null;

                                    if ($emote->get_uploaded_by()) {
                                        $u = $emote->get_uploaded_by();
                                        $show_private_badge = $u->private_profile;

                                        $username = $u->username;
                                        $link = "/users.php?id={$u->id}";
                                        $badge = $u->role;
                                        $custom_badge = $u->custom_badge;
                                    }

                                    echo "<a href=\"$link\">";
                                    echo $username;
                                    echo "</a>";

                                    if ($show_private_badge) {
                                        echo " <img src='/static/img/icons/eye.png' alt='(Private)' title='You are the only one who sees this' />";
                                    }

                                    if ($badge && $badge->badge) {
                                        echo " <img src='/static/userdata/badges/{$badge->badge->id}/1x.webp' alt='## {$badge->name}' title='{$badge->name}' />";
                                    }

                                    if ($custom_badge) {
                                        echo " <img src='/static/userdata/badges/{$custom_badge->id}/1x.webp' alt='' title='Personal badge' />";
                                    }

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", $emote->get_created_at());
                                    echo ' UTC">about ' . format_timestamp(time() - $emote->get_created_at()) . " ago</span>";
                                    ?></td>
                                </tr>
                                <?php
                                $stmt = $db->prepare("SELECT u.id, a.created_at FROM users u
                                    INNER JOIN mod_actions a ON a.emote_id = ?
                                    WHERE u.id = a.user_id");
                                $stmt->execute([$emote->get_id()]);

                                if ($row = $stmt->fetch()) {
                                    $approver = User::get_user_by_id($db, $row["id"]);

                                    echo '<tr><th>Approver</th><td>';
                                    echo "<a href='/users.php?id={$approver->id}' target='_blank'>{$approver->username}</a>";

                                    if ($approver->role && $approver->role->badge) {
                                        echo " <img src='/static/userdata/badges/{$approver->role->badge->id}/1x.webp' alt='## {$approver->role->name}' title='{$approver->role->name}' />";
                                    }

                                    if ($approver->custom_badge) {
                                        echo " <img src='/static/userdata/badges/{$approver->custom_badge->id}/1x.webp' alt='' title='Personal badge' />";
                                    }

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", strtotime($row["created_at"])) . ' UTC">';
                                    echo format_timestamp(strtotime($row["created_at"]) - $emote->get_created_at()) . ' after upload';
                                    echo '</span></td></tr>';
                                }

                                if (RATING_ENABLE): ?>
                                    <tr>
                                        <th>Rating</th>
                                        <?php
                                        if ($emote->get_rating()["total"] < RATING_EMOTE_MIN_VOTES) {
                                            echo '<td>Not rated (' . $emote->get_rating()["total"] . ')</td>';
                                        } else {

                                            $rating = $emote->get_rating()["average"];

                                            // TODO: make it customizable
                                            list($rating_classname, $rating_name) = match (true) {
                                                in_range($rating, 0.75, 1.0) => [
                                                    "gemerald",
                                                    "<img src='/static/img/icons/ratings/1.png'>
                                        <img src='/static/img/icons/ratings/1.png'>
                                        <img src='/static/img/icons/ratings/1.png'> Shiny Gemerald! 
                                        <img src='/static/img/icons/ratings/1.png'>
                                        <img src='/static/img/icons/ratings/1.png'>
                                        <img src='/static/img/icons/ratings/1.png'>
                                        "
                                                ],
                                                in_range($rating, 0.25, 0.75) => ["gem", "<img src='/static/img/icons/ratings/1.png'> Gem <img src='/static/img/icons/ratings/1.png'>"],
                                                in_range($rating, -0.25, 0.25) => ["iron", "Iron"],
                                                in_range($rating, -0.75, -0.25) => ["coal", "<img src='/static/img/icons/ratings/-1.png'> Coal <img src='/static/img/icons/ratings/-1.png'>"],
                                                in_range($rating, -1.0, -0.75) => [
                                                    "brimstone",
                                                    "
                                        <img src='/static/img/icons/ratings/brimstone.webp'>
                                        <img src='/static/img/icons/ratings/-1.png'>
                                        <img src='/static/img/icons/ratings/brimstone.webp'>
                                        !!!AVOID THIS CANCER-GIVING BRIMSTONE!!!
                                        <img src='/static/img/icons/ratings/brimstone.webp'>
                                        <img src='/static/img/icons/ratings/-1.png'>
                                        <img src='/static/img/icons/ratings/brimstone.webp'>
                                        "
                                                ]
                                            };

                                            echo '<td>';
                                            echo "<span class=\"rating $rating_classname\">$rating_name</span>";
                                            echo ' (' . $emote->get_rating()["total"] . ')';
                                            echo '</td>';
                                        }
                                        ?>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Visibility</th>
                                    <td><?php
                                    switch ($emote->get_visibility()) {
                                        case 0:
                                            echo 'Unlisted';
                                            break;
                                        case 1:
                                            echo 'Public';
                                            break;
                                        case 2:
                                            echo 'Pending approval (unlisted for a moment)';
                                            break;
                                        default:
                                            echo 'N/A';
                                            break;
                                    }
                                    ?></td>
                                </tr>
                                <?php if ($emote->get_source()): ?>
                                    <tr>
                                        <th>Source</th>
                                        <td>
                                            <a href="<?php echo $emote->get_source() ?>"
                                                target="_blank"><?php echo $emote->get_source() ?></a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </section>

                        <section class="box">
                            <div class="content">
                                <?php
                                $stmt = $db->prepare("SELECT users.id, users.username
                            FROM users
                            INNER JOIN emote_sets AS es ON es.owner_id = users.id
                            INNER JOIN emote_set_contents AS ec ON ec.emote_set_id = es.id
                            INNER JOIN acquired_emote_sets AS aes ON aes.emote_set_id = es.id
                            WHERE ec.emote_id = ? AND aes.is_default = TRUE");

                                $stmt->execute([$emote->get_id()]);
                                $count = $stmt->rowCount();

                                $db = null;

                                if ($count > 0) {
                                    echo "<p>Added in $count channels</p>";
                                } else {
                                    echo "No one has added this emote yet... :'(";
                                }
                                ?>
                                <div class="items row">
                                    <?php
                                    while ($row = $stmt->fetch()) {
                                        echo '<a href="/users.php?id=' . $row["id"] . '">' . $row["username"] . '</a>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        } else { ?>
                            <div class="box content items">
                                <?php html_display_emotes($emotes); ?>
                            </div>
                            <?php if ($total_pages > 1) {
                                echo '' ?>
                            </section>
                            <section class="box center row">
                                <?php
                                html_pagination(
                                    $total_pages,
                                    $page,
                                    "/emotes?q=" . substr($search, 1, strlen($search) - 2) . "&sort_by=$sort_by"
                                );
                            }
                        }
                        ?>
                    </section>
                </section>
            </section>
        </div>
    </div>
</body>

</html>