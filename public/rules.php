<?php
include_once "../src/config.php";
include_once "../src/partials.php";
include_once "../src/accounts.php";

authorize_user();

$contents = "";

$path = sprintf("%s/%s/txt/RULES", $_SERVER["DOCUMENT_ROOT"], INSTANCE_STATIC_FOLDER);

if (is_file($path)) {
    $contents = file_get_contents($path);
    $contents = explode("\n", $contents);
}
?>

<html>

<head>
    <title>The Rules of <?php echo INSTANCE_NAME ?></title>
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <div class="content row">
                <div class="sidebar" style="min-width: 300px;"></div>
                <div class="content">
                    <h1>The Rules of <?php echo INSTANCE_NAME ?></h1>
                    <ol>
                        <?php
                        foreach ($contents as $line) {
                            echo "<li>$line</li>";
                        }
                        if (empty($contents)) {
                            echo "<i>No rules!</i>";
                        }
                        ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>

</html>