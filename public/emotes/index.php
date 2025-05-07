<?php
include "../../src/emote.php";
include "../../src/accounts.php";
include_once "../../src/config.php";
include "../../src/partials.php";
include "../../src/utils.php";
include "../../src/alert.php";

authorize_user();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

function display_list_emotes(PDO &$db, string $search, string $sort_by, int $page, int $limit): array
{
    $user_id = $_SESSION["user_id"] ?? "-1";
    $offset = ($page - 1) * $limit;

    $sort = match ($sort_by) {
        "low_ratings" => "rating ASC",
        "recent" => "e.created_at DESC",
        "oldest" => "e.created_at ASC",
        default => "rating DESC"
    };

    $stmt = $db->prepare("SELECT e.*,
    CASE WHEN EXISTS (
        SELECT 1
        FROM emote_set_contents ec
        INNER JOIN emote_sets es ON es.id = ec.emote_set_id
        WHERE ec.emote_id = e.id AND es.owner_id = ?
    ) THEN 1 ELSE 0 END AS is_in_user_set, COALESCE(COUNT(r.rate), 0) AS rating
    FROM emotes e
    LEFT JOIN ratings AS r ON r.emote_id = e.id
    WHERE e.code LIKE ? AND e.visibility = 1
    GROUP BY 
    e.id, e.code, e.created_at
    ORDER BY $sort
    LIMIT ? OFFSET ?
    ");

    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $search, PDO::PARAM_STR);
    $stmt->bindParam(3, $limit, PDO::PARAM_INT);
    $stmt->bindParam(4, $offset, PDO::PARAM_INT);

    $stmt->execute();

    $emotes = [];

    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $uploader = null;

        if ($row["uploaded_by"]) {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$row["uploaded_by"]]);
            $uploader = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        array_push($emotes, new Emote(
            $row["id"],
            $row["code"],
            "webp",
            intval(strtotime($row["created_at"])),
            $uploader,
            $row["is_in_user_set"],
            $row["rating"],
            $row["visibility"]
        ));
    }

    return $emotes;
}

function display_emote(PDO &$db, string $id)
{
    $stmt = $db->prepare("SELECT e.*, COALESCE(COUNT(r.rate), 0) as total_rating,
    COALESCE(ROUND(AVG(r.rate), 2), 0) AS average_rating
    FROM emotes e
    LEFT JOIN ratings AS r ON r.emote_id = ?
    WHERE e.id = ?");
    $stmt->execute([$id, $id]);

    $emote = null;

    if ($row = $stmt->fetch()) {
        if ($row["id"] != null) {
            $emote = new Emote(
                $row["id"],
                $row["code"],
                "webp",
                intval(strtotime($row["created_at"])),
                $row["uploaded_by"],
                false,
                ["total" => $row["total_rating"], "average" => $row["average_rating"]],
                $row["visibility"]
            );
        }
    }

    if ($emote == null) {
        if (CLIENT_REQUIRES_JSON) {
            json_response([
                "status_code" => 404,
                "message" => "Emote ID $id does not exist",
                "data" => null
            ], 404);
            exit;
        }

        header("Location: /404.php");
        exit;
    }

    return $emote;
}

$emotes = null;
$emote = null;

$id = $_GET["id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$page = max(1, intval($_GET["p"] ?? "1"));
$limit = 50;
$total_emotes = 0;
$total_pages = 0;
$search = "%" . ($_GET["q"] ?? "") . "%";
$sort_by = $_GET["sort_by"] ?? "";

if (empty($id)) {
    $emotes = display_list_emotes($db, $search, $sort_by, $page, $limit);
    $stmt = $db->prepare("SELECT COUNT(*) FROM emotes WHERE code LIKE ? AND visibility = 1");
    $stmt->execute([$search]);
    $total_emotes = $stmt->fetch()[0];
    $total_pages = ceil($total_emotes / $limit);
} else {
    $emote = display_emote($db, $id);
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
                        <div class="box navtab">
                            <?php echo $emote != null ? "Emote - " . $emote->get_code() : "$total_emotes Emotes - Page $page/$total_pages" ?>
                        </div>
                        <?php
                        if ($emote != null) { ?>
                            <div class="box content">
                                <div class="emote-showcase">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() ?>/1x.webp"
                                        alt="<?php echo $emote->get_code() ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() ?>/2x.webp"
                                        alt="<?php echo $emote->get_code() ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() ?>/3x.webp"
                                        alt="<?php echo $emote->get_code() ?>">
                                </div>
                            </div>
                        </section>
                        <section class="box items row">
                            <?php if (isset($_SESSION["user_id"])) {
                                echo '' ?>
                                <div class="items row left full">
                                    <?php
                                    $added = false;

                                    if (isset($_SESSION["user_emote_set_id"])) {
                                        $stmt = $db->prepare("SELECT id, code FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
                                        $stmt->execute([$_SESSION["user_emote_set_id"], $emote->get_id()]);

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
                                            <?php
                                            if ($added) {
                                                ?>
                                                <input type="text" name="action" value="remove" style="display: none;">
                                                <button type="submit" class="red">Remove from my channel</button>
                                            </form>
                                            <form action="/emotes/setmanip.php" method="POST" class="row">
                                                <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                                    style="display: none;">
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
                                        if ($_SESSION["user_role"]["permission_report"]) {
                                            echo '<a class="button red" href="/report?emote_id=<?php echo $emote->get_id() ?>">Report emote</a>';
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
                                <tr>
                                    <th>Uploader</th>
                                    <td><?php
                                    $username = ANONYMOUS_DEFAULT_NAME;
                                    $link = "#";

                                    if ($emote->get_uploaded_by()) {
                                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$emote->get_uploaded_by()]);

                                        if ($row = $stmt->fetch()) {
                                            $username = $row["username"];
                                            $link = "/users.php?id=" . $emote->get_uploaded_by();
                                        }
                                    }

                                    echo "<a href=\"$link\">";
                                    echo $username;
                                    echo "</a>";

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", $emote->get_created_at());
                                    echo ' UTC">about ' . format_timestamp(time() - $emote->get_created_at()) . " ago</span>";
                                    ?></td>
                                </tr>
                                <?php
                                $stmt = $db->prepare("SELECT u.id, u.username, a.created_at FROM users u
                                    INNER JOIN mod_actions a ON a.emote_id = ?
                                    WHERE u.id = a.user_id");
                                $stmt->execute([$emote->get_id()]);

                                if ($row = $stmt->fetch()) {
                                    echo '<tr><th>Approver</th><td>';
                                    echo '<a href="/users.php?id=' . $row["id"] . '" target="_blank">' . $row["username"] . '</a>, <span title="';
                                    echo date("M d, Y H:i:s", strtotime($row["created_at"])) . ' UTC">';
                                    echo format_timestamp(strtotime($row["created_at"]) - $emote->get_created_at()) . ' after upload';
                                    echo '</span></td></tr>';
                                }

                                if (RATING_ENABLE): ?>
                                    <tr>
                                        <th>Rating</th>
                                        <?php
                                        if ($emote->get_rating()["total"] < 10) {
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
                            </table>
                        </section>

                        <section class="box">
                            <div class="content">
                                <?php
                                $stmt = $db->prepare("SELECT users.id, users.username
                            FROM users
                            INNER JOIN emote_sets AS es ON es.owner_id = users.id
                            INNER JOIN emote_set_contents AS ec ON ec.emote_set_id = es.id
                            WHERE ec.emote_id = ?");

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
                                <?php
                                foreach ($emotes as $e) {
                                    echo '<a class="box emote" href="/emotes?id=' . $e->get_id() . '">';

                                    if ($e->is_added_by_user()) {
                                        echo '<img src="/static/img/icons/yes.png" class="emote-check" />';
                                    }

                                    echo '<img src="/static/userdata/emotes/' . $e->get_id() . '/2x.webp" alt="' . $e->get_code() . '"/>';
                                    echo '<h1>' . $e->get_code() . '</h1>';
                                    echo '<p>' . ($e->get_uploaded_by() == null ? (ANONYMOUS_DEFAULT_NAME . "*") : $e->get_uploaded_by()["username"]) . '</p>';
                                    echo '</a>';
                                }
                                ?>
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