<?php
include_once "../src/config.php";
include_once "../src/accounts.php";
include_once "../src/version.php";

authorize_user();

?>
<html>

<head>
    <title><?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper center big-gap">
            <h1><img src="/static/img/brand/big.webp" alt="<?php echo INSTANCE_NAME; ?>"></h1>

            <div class="items row" style="gap:32px;">
                <a href="/emotes">Emotes</a>
                <a href="/emotesets.php">Emotesets</a>
                <a href="/users.php">Users</a>
                <?php if (EMOTE_UPLOAD && (ANONYMOUS_UPLOAD || (isset($_SESSION["user_role"]) && $_SESSION["user_role"]["permission_upload"]))) {
                    echo '<a href="/emotes/upload.php">Upload</a>';
                } ?>
                <a href="/account">Account</a>
                <a href="/software.php">Chat clients & Tools</a>
            </div>

            <form action="/emotes/search.php" method="get" class="row">
                <input type="text" name="q">
                <button type="submit">Search</button>
            </form>

            <div class="counter">
                <?php
                $db = new PDO(DB_URL, DB_USER, DB_PASS);
                $results = $db->query("SELECT COUNT(*) FROM emotes WHERE visibility = 1");
                $count = $results->fetch()[0];

                foreach (str_split($count) as $c) {
                    echo "<img src=\"/static/img/counter/$c.png\" alt=\"\" />";
                }
                ?>
            </div>

            <p style="font-size:12px;">
                Serving <?php echo $count ?> gorillion emotes -
                Running
                <?php
                echo '<a href="' . TINYEMOTES_LINK . '">';
                echo sprintf("%s v%s", TINYEMOTES_NAME, TINYEMOTES_VERSION);
                echo '</a> ';

                if (TINYEMOTES_COMMIT != null) {
                    echo '<a href="' . sprintf("%s/tree/%s", TINYEMOTES_LINK, TINYEMOTES_COMMIT) . '">(Commit ';
                    echo substr(TINYEMOTES_COMMIT, 0, 7);
                    echo ')</a>';
                }
                ?>
            </p>
        </div>
    </div>
</body>

</html>