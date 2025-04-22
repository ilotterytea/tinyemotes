<?php
include_once "../../src/accounts.php";
include_once "../../src/config.php";
include_once "../../src/partials.php";
include_once "../../src/utils.php";
include_once "../../src/alert.php";

if (!authorize_user(true)) {
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);
$report = null;
$report_id = $_GET["id"] ?? "";

if ($report_id != "") {
    $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? AND sender_id = ?");
    $stmt->execute([$report_id, $_SESSION["user_id"]]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $report = $row;

        if (CLIENT_REQUIRES_JSON) {
            json_response([
                "status_code" => 201,
                "message" => null,
                "data" => $report
            ], 201);
            exit;
        }
    } else {
        generate_alert("/report", "Report ID #" . $_GET["id"] . " not found or not accessable");
        exit;
    }
}

$contents = "";

if ($contents == "") {
    if (isset($_GET["user_id"])) {
        $contents = "Hi! I want to report user ID #" . $_GET["user_id"] . " because...";
    } else if (isset($_GET["emote_id"])) {
        $contents = "Hi! I want to report emote ID #" . $_GET["emote_id"] . " because...";
    }
}
?>

<html>

<head>
    <title><?php echo $report == null ? "Send a message to MODS" : "A message to MODS" ?> - alright.party</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>

            <section class="content" style="width: 25%;">
                <?php display_alert() ?>
                <section class="box">
                    <div class="box navtab">
                        <?php echo $report == null ? "Send a message to MODS" : "A message to MODS" ?>
                    </div>
                    <?php if ($report == null) {
                        echo '' ?>
                        <div class="box content">
                            <form action="/report/send.php" method="POST">
                                <textarea name="contents" style="resize: none;height:250px;" autofocus
                                    required><?php echo $contents; ?></textarea>
                                <button type="submit">Send</button>
                            </form>
                        </div> <?php ;
                    } else {
                        echo '' ?>
                        <div class="box content">
                            <textarea name="contents" style="resize: none;height:250px;"
                                disabled><?php echo $report["contents"]; ?></textarea>
                        </div>
                    </section>
                    <section class="box">
                        <p>Reported <?php echo format_timestamp(time() - strtotime($report["sent_at"])) ?> ago</p>
                        <p>Status:
                            <?php echo $report["resolved_by"] == null ? "<b style='color:red;'>Unresolved</b>" : "<b style='color:green;'>Resolved</b>" ?>
                        </p>
                    </section>
                    <?php
                    if ($report["response_message"]) {
                        ?>
                        <section class="box">
                            <div class="box navtab">
                                Response from MOD
                            </div>
                            <div class="box content">
                                <textarea name="contents" style="resize: none;height:250px;"
                                    disabled><?php echo $report["response_message"]; ?></textarea>
                            </div>
                        </section>
                        <?php
                    }
                    ?>
                    <?php ;
                    }
                    ?>
            </section>
            </section>
        </div>
    </div>
</body>

</html>