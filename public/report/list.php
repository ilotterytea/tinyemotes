<?php
include_once "../../src/accounts.php";
include_once "../../src/config.php";
include_once "../../src/partials.php";
include_once "../../src/utils.php";
include_once "../../src/alert.php";

if (!REPORTS_ENABLE) {
    generate_alert("/404.php", "Reports are disabled", 403);
    exit;
}

if (!authorize_user(true)) {
    exit;
}

if (isset($_SESSION["user_role"]) && !$_SESSION["user_role"]["permission_report"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$stmt = $db->prepare("SELECT * FROM reports WHERE sender_id = ? ORDER BY sent_at DESC");
$stmt->execute([$_SESSION["user_id"]]);

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<html>

<head>
    <title>Report list - <?php echo INSTANCE_NAME ?></title>
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
                        Report list
                    </section>
                    <section class="box content">
                        <table>
                            <tr>
                                <th>Contents</th>
                                <th>Status</th>
                                <th style="min-width: 96px;"></th>
                            </tr>
                            <?php
                            foreach ($reports as $report) {
                                echo '<tr>';

                                echo '<td>' . substr($report["contents"], 0, 20) . "...";
                                echo ' <span style="font-size:12px; color: gray;">(' . format_timestamp(time() - strtotime($report["sent_at"])) . ' ago)</span>';
                                echo '</td>';

                                echo '<td>';
                                echo $report["resolved_by"] == null ? "<b style='color:red;'>Unresolved</b>" : "<b style='color:green;'>Resolved</b>";
                                echo '</td>';

                                echo '<td style="text-align:center;">';
                                echo '<a href="/report?id=' . $report["id"] . '">[ View ]</a>';
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