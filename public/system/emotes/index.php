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

$current_user_id = $_SESSION["user_id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);
$emote_results = $db->prepare("SELECT e.*,
CASE WHEN up.private_profile = FALSE OR up.id = ? THEN e.uploaded_by ELSE NULL END AS uploaded_by,
CASE WHEN up.private_profile = FALSE OR up.id = ? THEN u.username ELSE NULL END AS uploader_name,
r.name AS role_name,
r.badge_id AS role_badge_id,
ub.badge_id AS custom_badge_id
FROM emotes e
LEFT JOIN users u ON u.id = e.uploaded_by
LEFT JOIN user_preferences up ON up.id = u.id
LEFT JOIN role_assigns ra ON ra.user_id = u.id
LEFT JOIN roles r ON r.id = ra.role_id
LEFT JOIN user_badges ub ON ub.user_id = u.id
WHERE e.visibility = 2
ORDER BY e.created_at DESC
LIMIT 25
");
$emote_results->execute([$current_user_id, $current_user_id]);

$emote_results = $emote_results->fetchAll(PDO::FETCH_ASSOC);

$emote = $emote_results[0] ?? null;

if (isset($_GET["id"])) {
    $stmt = $db->prepare("SELECT e.*,
        CASE WHEN up.private_profile = FALSE OR up.id = ? THEN e.uploaded_by ELSE NULL END AS uploaded_by,
        CASE WHEN up.private_profile = FALSE OR up.id = ? THEN u.username ELSE NULL END AS uploader_name,
        r.name AS role_name,
        r.badge_id AS role_badge_id,
        ub.badge_id AS custom_badge_id
        FROM emotes e
        LEFT JOIN users u ON u.id = e.uploaded_by
        LEFT JOIN user_preferences up ON up.id = u.id
        LEFT JOIN role_assigns ra ON ra.user_id = u.id
        LEFT JOIN roles r ON r.id = ra.role_id
        LEFT JOIN user_badges ub ON ub.user_id = u.id
        WHERE e.visibility = 2 AND e.id = ?
        LIMIT 1");

    $stmt->execute([$current_user_id, $current_user_id, $_GET["id"]]);
    $emote = $stmt->fetch(PDO::FETCH_ASSOC) ?? null;
}

?>

<html>

<head>
    <title>System panel - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
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
                            echo '<img src="/static/userdata/emotes/' . $row["id"] . '/1x.webp">';
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
                            <div class="box navtab row">
                                <?php
                                echo "Emote - " . $emote["code"];
                                echo '<div class="row small-gap" style="margin-left:auto">';

                                $original_path = "/static/userdata/emotes/" . $emote["id"];
                                $files = glob($_SERVER["DOCUMENT_ROOT"] . $original_path . "/original.*");

                                if (!empty($files)) {
                                    $filename = basename($files[0]);
                                    echo "<a href='$original_path/$filename' target='_BLANK'><img src='/static/img/icons/emotes/emote.png' alt='[Show original]' title='Show original' /></a>";
                                }
                                echo '</div>';
                                ?>
                            </div>
                            <div class="box content">
                                <div class="emote-showcase">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] ?>/1x.webp"
                                        alt="<?php echo $emote["id"] ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] ?>/2x.webp"
                                        alt="<?php echo $emote["id"] ?>">
                                    <img src="/static/userdata/emotes/<?php echo $emote["id"] ?>/3x.webp"
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

                                    if ($emote["uploader_name"] != null) {
                                        $username = $emote["uploader_name"];
                                        $link = '/users.php?id=' . $emote["uploaded_by"];
                                    }

                                    echo "<a href=\"$link\">";
                                    echo $username;
                                    echo "</a>";

                                    if ($emote["role_badge_id"]) {
                                        echo ' <img src="/static/userdata/badges/' . $emote["role_badge_id"] . '/1x.webp" alt="## ' . $emote["role_name"] . '" title="' . $emote["role_name"] . '" />';
                                    }

                                    if ($emote["custom_badge_id"]) {
                                        echo ' <img src="/static/userdata/badges/' . $emote["custom_badge_id"] . '/1x.webp" alt="" title="Personal badge" />';
                                    }

                                    echo ', <span title="';
                                    echo date("M d, Y H:i:s", strtotime($emote["created_at"]));
                                    echo ' UTC">about ' . format_timestamp(time() - strtotime($emote["created_at"])) . " ago</span>";
                                    ?></td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td><?php echo isset($emote["notes"]) == true ? $emote["notes"] : '<i>Empty</i>' ?></td>
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