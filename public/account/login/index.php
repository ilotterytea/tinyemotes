<?php
include "../../../src/accounts.php";

if (authorize_user()) {
    header("Location: /account");
    exit;
}

include "../../../src/partials.php";
include_once "../../../src/config.php";
include_once "../../../src/alert.php";
include_once "../../../src/utils.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["username"], $_POST["password"])) {
        generate_alert("/account/login", "Not enough POST fields");
        exit;
    }

    $username = $_POST["username"];
    $password = $_POST["password"];
    $remember = intval($_POST["remember"] ?? "0") != 0;

    $db = new PDO(DB_URL, DB_USER, DB_PASS);
    $stmt = $db->prepare("SELECT secret_key, password FROM users WHERE username = ? AND password IS NOT NULL");
    $stmt->execute([$username]);

    if ($row = $stmt->fetch()) {
        if (password_verify($password, $row["password"])) {
            setcookie("secret_key", $row["secret_key"], $remember ? (time() + ACCOUNT_COOKIE_MAX_LIFETIME) : 0, "/");
            header("Location: /account");
            exit;
        } else {
            generate_alert("/account/login", "Passwords do not match!", 403);
            exit;
        }
    } else {
        generate_alert("/account/login", "User not found or is not accessable", 404);
        exit;
    }
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
            <section class="content" style="width: 400px;">
                <?php display_alert() ?>
                <section class="box">
                    <div class="box navtab">
                        <p>Log in to <?php echo INSTANCE_NAME ?></p>
                    </div>
                    <div class="box content">
                        <form action="/account/login" method="post">
                            <div>
                                <label for="username">Username</label>
                                <input type="text" name="username" id="form-username" required>
                            </div>
                            <div>
                                <label for="password">Password</label>
                                <input type="password" name="password" id="form-password" required>
                            </div>
                            <div>
                                <input type="checkbox" name="remember" value="1" id="form-remember">
                                <label for="remember" class="inline">Remember me</label>
                            </div>
                            <div>
                                <button type="submit">Log in</button>
                                <?php if (ACCOUNT_REGISTRATION_ENABLE): ?>
                                    <a href="/account/register.php">Register</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <?php if (TWITCH_REGISTRATION_ENABLE): ?>
                    <section class="box column">
                        <a href="/account/login/twitch.php" class="button purple"
                            style="padding:8px 24px; font-size: 18px;">Login with Twitch</a>
                        <p style="font-size: 12px;">Logging in via Twitch gives you the ability to use
                            <?php echo INSTANCE_NAME ?> emotes in your Twitch chat.
                        </p>
                    </section>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>

</html>