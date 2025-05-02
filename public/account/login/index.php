<?php
include "../../../src/accounts.php";
authorize_user();

include "../../../src/partials.php";
include_once "../../../src/config.php";
include_once "../../../src/alert.php";

if (!ACCOUNT_REGISTRATION_ENABLE) {
    generate_alert("/404.php", "Account registration is disabled", 403);
    exit;
}

?>

<html>

<head>
    <title>Login - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar(); ?>

            <section class="content">
                <section class="box" style="width: 400px;">
                    <div class="box navtab">
                        <p>Log in to <?php echo INSTANCE_NAME ?></p>
                    </div>
                    <div class="box content">
                        <?php if (TWITCH_REGISTRATION_ENABLE): ?>
                            <form action="/account/login/twitch.php" method="GET">
                                <button type="submit" class="purple" style="padding:8px 24px; font-size: 18px;">Login with
                                    Twitch</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </section>
        </div>
    </div>
</body>

</html>