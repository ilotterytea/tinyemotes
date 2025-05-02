<?php
include_once "../../../src/partials.php";
include_once "../../../src/accounts.php";
include_once "../../../src/alert.php";
include_once "../../../src/config.php";
include_once "../../../src/utils.php";

if (!MOD_EMOTES_APPROVE) {
    generate_alert("/404.php", "Manual emote approval is disabled", 405);
    exit;
}

if (!authorize_user(true) || !$_SESSION["user_role"]["permission_approve_emotes"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

$emote_id = max(0, intval($_GET["id"] ?? "0"));

$db = new PDO(DB_URL, DB_USER, DB_PASS);
$emote_results = $db->query("SELECT e.*, u.username as uploader_name
FROM emotes e
LEFT JOIN users u ON u.id = e.uploaded_by
WHERE e.visibility = 2
ORDER BY e.created_at DESC
LIMIT 25
")->fetchAll(PDO::FETCH_ASSOC);

$emote = $emote_results[0] ?? null;

if ($emote_id > 0) {
    $stmt = $db->prepare("SELECT e.*, u.username as uploader_name
        FROM emotes e
        LEFT JOIN users u ON u.id = e.uploaded_by
        WHERE e.visibility = 2 AND e.id = ?
        LIMIT 1");
    $stmt->execute([$emote_id]);
    $emote = $stmt->fetch(PDO::FETCH_ASSOC) ?? null;
}

?>

<html>

<head>
    <title>System panel - alright.party</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <?php display_alert() ?>
            <section class="content row">
                <section class="box">
                    <div class="box navtab">System panel - Emote approval section</div>
                    <div class="box content">
                        <?php
                        foreach ($emote_results as $row) {
                            echo '<a href="/system/emotes?id=' . $row["id"] . '">';
                            echo '<img src="/static/userdata/emotes/' . $row["id"] . '/1x.' . $row["ext"] . '">';
                            echo '<b>' . $row["code"] . '</b>';
                            echo '<span style="font-size:10px;"> by ';

                            if ($row["uploader_name"] == null) {
                                echo ANONYMOUS_DEFAULT_NAME . '*';
                            } else {
                                echo $row["uploader_name"];
                            }

                            echo '</span></a>';
                        }

                        if (empty($emote_results)) {
                            echo 'Everything is clear. Good job!';
                        }
                        ?>
                    </div>
                </section>
                <?php if ($emote != null): ?>
                    <section class="content">
                        <!-- Emote showcase -->
                        <section class="box">
                            <div class="box navtab">Emote - <?php echo $emote["code"] ?></div>
                            <div class="box content">
                                <div class="emote-showcase">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] . '/' . '1x.' . $emote["ext"] ?>"
                                        alt="<?php echo $emote["id"] ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] . '/' . '2x.' . $emote["ext"] ?>"
                                        alt="<?php echo $emote["id"] ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] . '/' . '3x.' . $emote["ext"] ?>"
                                        alt="<?php echo $emote["id"] ?>">
                                </div>
                            </div>
                        </section>
                        <!-- Emote actions -->
                        <section class="box items center row">
                            <form action="/system/emotes/manip.php" method="post">
                                <input type="text" name="id" value="<?php echo $emote["id"] ?>" style="display: none;">
                                <input type="text" name="action" value="approve" style="display: none;">
                                <button type="submit" class="green">Approve</button>
                            </form>
                            <form action="/system/emotes/manip.php" method="post">
                                <input type="text" name="id" value="<?php echo $emote["id"] ?>" style="display: none;">
                                <input type="text" name="action" value="reject" style="display: none;">
                                <button type="submit" class="red">Reject</button>
                            </form>
                        </section>
                        <!-- Emote information -->
                        <section class="box">
                            <table class="vertical">
                                <tr>
                                    <th>Uploader</th>
                                    <td><?php
                                    $username = ANONYMOUS_DEFAULT_NAME;
                                    $link = "#";

                                    if ($row["uploader_name"] != null) {
                                        $username = $row["uploader_name"];
                                        $link = '/users.php?id=' . $row["uploaded_by"];
                                    }

                                    echo "<a href=\"$link\">";
                                    echo $username;
                                    echo "</a>";

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", strtotime($row["created_at"]));
                                    echo ' UTC">about ' . format_timestamp(time() - strtotime($row["created_at"])) . " ago</span>";
                                    ?></td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td><i>Empty</i></td>
                                </tr>
                            </table>
                        </section>
                        <!-- Mod actions on emote -->
                        <section class="box">
                            <div class="box navtab">
                                Mod actions
                            </div>
                            <div class="box content">
                                <p>No one has done anything on this emote...</p>
                            </div>
                        </section>
                    </section>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>

</html>