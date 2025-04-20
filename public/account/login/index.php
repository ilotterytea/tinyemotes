<?php
include "../../../src/accounts.php";
authorize_user();

include "../../../src/partials.php";
?>

<html>

<head>
    <title>Log in to alright.party</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar(); ?>

            <section class="content">
                <section class="box" style="width: 400px;">
                    <div class="box navtab">
                        <p>Log in to alright.party</p>
                    </div>
                    <div class="box content">
                        <form action="/account/login/twitch.php" method="GET">
                            <button type="submit" class="purple" style="padding:8px 24px; font-size: 18px;">Login with
                                Twitch</button>
                        </form>
                    </div>
                </section>
            </section>
        </div>
    </div>
</body>

</html>