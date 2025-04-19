<html>

<head>
    <title>alright.party</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper center big-gap">
            <h1><img src="/static/img/brand/big.webp" alt="<?php echo $_SERVER['HTTP_HOST']; ?>"></h1>

            <div class="items row" style="gap:32px;">
                <a href="/emotes">Emotes</a>
                <a href="/users">Users</a>
                <a href="/emotes/upload.php">Upload</a>
                <a href="/account">Account</a>
                <a href="/software">Chat clients</a>
            </div>

            <form action="/emotes/search.php" method="get">
                <input type="text" name="q">
                <button type="submit">Search</button>
            </form>

            <div class="counter">
                <?php
                $db = new SQLite3("../database.db");
                $results = $db->query("SELECT COUNT(*) FROM emotes");
                $count = $results->fetchArray()[0];

                $db->close();

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