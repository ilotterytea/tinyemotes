<?php
include_once "../../src/partials.php";
include_once "../../src/accounts.php";
include_once "../../src/alert.php";
include_once "../../src/config.php";

if (!MOD_SYSTEM_DASHBOARD) {
    generate_alert("/404.php", "System dashboard is disabled", 405);
    exit;
}

if (!authorize_user(true) || (!$_SESSION["user_role"]["permission_approve_emotes"] && !$_SESSION["user_role"]["permission_report_review"])) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

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
            <section class="content">
                <section class="box">
                    <div class="box navtab">System panel</div>
                    <div class="box content">
                        <?php
                        if (MOD_EMOTES_APPROVE && $_SESSION["user_role"]["permission_approve_emotes"]) {
                            echo '<a href="/system/emotes">Emotes';

                            $results = $db->query("SELECT COUNT(*) FROM emotes WHERE visibility = 2")->fetch()[0];

                            if ($results > 0) {
                                echo " ($results pending)";
                            }

                            echo '</a>';
                        }

                        if (REPORTS_ENABLE && $_SESSION["user_role"]["permission_report_review"]) {
                            echo '<a href="/system/reports">Reports';

                            $results = $db->query("SELECT COUNT(*) FROM reports WHERE resolved_by IS NULL")->fetch()[0];

                            if ($results > 0) {
                                echo " ($results pending)";
                            }

                            echo '</a>';
                        }
                        ?>
                    </div>
                </section>
            </section>
        </div>
    </div>
</body>

</html>