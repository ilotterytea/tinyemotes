<?php
include_once "../src/config.php";
include_once "../src/utils.php";
include_once "../src/partials.php";
include_once "../src/accounts.php";

authorize_user();

$status = intval($_GET["error_status"] ?? "404");
http_response_code($status);

$reason = str_safe($_GET["error_reason"] ?? "Not found", 200);

?>
<html>

<head>
    <title>(Error) <?php echo sprintf("%s - %s", $reason, INSTANCE_NAME) ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <section class="content">
                <h1 style="color: red;"><?php echo $reason ?></h1>
                <a href="/">[ Back to home ]</a>
            </section>

            <section style="position: absolute; right: 6px; bottom: 6px;">
                <img src="/static/img/404/<?php
                $files = scandir(INSTANCE_STATIC_FOLDER . "/img/404");
                array_splice($files, 0, 2);
                echo $files[random_int(0, count($files) - 1)];
                ?>" alt=""></img>
            </section>
        </div>
    </div>
</body>

</html>