<?php
include_once "../src/config.php";
include_once "../src/accounts.php";
authorize_user();

?>
<html>

<head>
    <title><?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
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
                <a href="/software">Chat clients</a>
            </div>

            <form action="/emotes/search.php" method="get" class="row">
                <input type="text" name="q">
                <button type="submit">Search</button>
            </form>

            <div class="counter">
                <?php
                $db = new PDO(DB_URL, DB_USER, DB_PASS);
                $results = $db->query("SELECT COUNT(*) FROM emotes");
                $count = $results->fetch()[0];

                foreach (str_split($count) as $c) {
                    echo "<img src=\"/static/img/counter/$c.png\" alt=\"\" />";
                }
                ?>
            </div>

            <p style="font-size:12px;">Serving <?php echo $count ?> gorillion emotes - Running <a
                    href="https://github.com/ilotterytea/alrighttv">AlrightTV v0.1</a></p>
        </div>
    </div>
</body>

</html>