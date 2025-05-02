<?php
include "../src/config.php";
http_response_code(404);
?>
<html>

<head>
    <title>Not found - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper center">
            <section class="box center big-gap" style="display: flex;flex-direction:column; padding: 16px;">
                <h1 style="color: red;">404 Not Found</h1>
                <img src="/static/img/404/<?php
                $files = scandir("static/img/404");
                $count = count($files) - 2;
                echo random_int(1, $count);
                ?>.webp">
                <a href=" /">Back to home</a>
            </section>
        </div>
    </div>
</body>

</html>