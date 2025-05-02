<?php
include_once "../src/accounts.php";
include_once "../src/config.php";
include_once "../src/partials.php";
include_once "../src/utils.php";

if (!authorize_user(true)) {
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT * FROM inbox_messages WHERE recipient_id = ? ORDER BY sent_at DESC");
$stmt->execute([$_SESSION["user_id"]]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("UPDATE inbox_messages SET has_read = true WHERE recipient_id = ?");
$stmt->execute([$_SESSION["user_id"]]);

?>

<html>

<head>
    <title>Inbox - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <section class="content">
                <section class="box" style="width: 50%;">
                    <section class="box navtab">
                        Inbox
                    </section>
                    <section class="box content">
                        <table>
                            <tr>
                                <th style="width: 16px;"></th>
                                <th>Contents</th>
                                <th style="min-width: 96px;"></th>
                            </tr>
                            <?php
                            foreach ($messages as $message) {
                                echo '<tr';
                                if (!$message["has_read"]) {
                                    echo ' style="background-color: yellow;"';
                                }
                                echo '>';

                                echo '<td><img src="/static/img/icons/inbox/' . $message["message_type"] . '.png"></td>';
                                echo '<td>' . $message["contents"];
                                echo ' <span style="font-size:12px; color: gray;">(' . format_timestamp(time() - strtotime($message["sent_at"])) . ' ago)</span>';
                                echo '</td>';

                                echo '<td style="text-align:center;">';
                                if ($message["link"]) {
                                    echo '<a  href="' . $message["link"] . '">[ View ]</a>';
                                }
                                echo '</td>';

                                echo '</tr>';
                            }
                            ?>
                        </table>
                    </section>
                </section>
            </section>
        </div>
    </div>
</body>

</html>