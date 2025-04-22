<?php
include "../../src/emote.php";
include "../../src/accounts.php";
include_once "../../src/config.php";
include "../../src/partials.php";
include "../../src/utils.php";
include "../../src/alert.php";

authorize_user();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

function display_list_emotes(PDO &$db, int $page, int $limit): array
{
    $search = $_GET["q"] ?? "";
    $user_id = $_SESSION["user_id"] ?? "-1";
    $offset = $page * $limit;
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

function display_emote(PDO &$db, int $id)
{
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

$emotes = null;
$emote = null;

$id = $_GET["id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if ($id == "" || !is_numeric($id)) {
    $page = intval($_GET["p"] ?? "0");
    $limit = 50;
    $emotes = display_list_emotes($db, $page, $limit);
} else {
    $emote = display_emote($db, intval($id));
}
?>

<html>

<head>
    <title><?php
    echo empty($emotes) ? "Emote " . $emote->get_code() : "Emotes"
        ?> - alright.party</title>
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
                    <?php display_alert() ?>
                    <section class="box">
                        <div class="box navtab">
                            <?php echo empty($emotes) ? "Emote - " . $emote->get_code() : "Emotes" ?>
                        </div>
                        <?php
                        if (empty($emotes)) { ?>
                            <div class="box content">
                                <div class="emote-showcase">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() . '/' . '1x.' . $emote->get_ext() ?>"
                                        alt="<?php echo $emote->get_code() ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() . '/' . '2x.' . $emote->get_ext() ?>"
                                        alt="<?php echo $emote->get_code() ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote->get_id() . '/' . '3x.' . $emote->get_ext() ?>"
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
                                        $stmt = $db->prepare("SELECT id FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
                                        $stmt->execute([$_SESSION["user_emote_set_id"], $emote->get_id()]);
                                        $added = $stmt->rowCount() > 0;
                                    }
                                    ?>
                                    <form action="/emotes/setmanip.php" method="POST">
                                        <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                            style="display: none;">
                                        <?php
                                        if ($added) { ?>
                                            <input type="text" name="action" value="remove" style="display: none;">
                                            <button type="submit" class="red">Remove from my channel</button>
                                            <?php
                                        } else { ?>
                                            <input type="text" name="action" value="add" style="display: none;">
                                            <button type="submit" class="green">Add to my channel</button>
                                            <?php
                                        }
                                        ?>
                                    </form>
                                </div>
                                <div class="items row right full">
                                    <?php
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
                                    ?>
                                    <a class="button red" href="/emotes/report.php?id=<?php echo $emote->get_id() ?>">Report
                                        emote</a>
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
                                    $username = "anonymous";
                                    $link = "#";

                                    if ($emote->get_uploaded_by()) {
                                        $db = new PDO(DB_URL, DB_USER, DB_PASS);
                                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$emote->get_uploaded_by()]);

                                        if ($row = $stmt->fetch()) {
                                            $username = $row["username"];
                                            $link = "/users.php?id=" . $emote->get_uploaded_by();
                                        }

                                        $db = null;
                                    }

                                    echo "<a href=\"$link\">";
                                    echo $username;
                                    echo "</a>";

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", $emote->get_created_at());
                                    echo ' UTC">about ' . format_timestamp(time() - $emote->get_created_at()) . " ago</span>";
                                    ?></td>
                                </tr>
                                <tr>
                                    <th>Rating</th>
                                    <td>Not rated</td>
                                </tr>
                            </table>
                        </section>

                        <section class="box">
                            <div class="content">
                                <?php
                                $db = new PDO(DB_URL, DB_USER, DB_PASS);
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
                                    echo '<a class="box emote" href="emotes?id=' . $e->get_id() . '">';

                                    if ($e->is_added_by_user()) {
                                        echo '<img src="/static/img/icons/yes.png" class="emote-check" />';
                                    }

                                    echo '<img src="/static/userdata/emotes/' . $e->get_id() . '/2x.' . $e->get_ext() . '" alt="' . $e->get_code() . '"/>';
                                    echo '<p>' . $e->get_code() . '</p>';
                                    echo '</a>';
                                }
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                    </section>
                </section>
            </section>
        </div>
    </div>
</body>

</html>