<?php
include "../../src/accounts.php";
include_once "../../src/alert.php";

if (authorize_user()) {
    header("Location: /account");
    exit;
}

if (!ACCOUNT_REGISTRATION_ENABLE) {
    generate_alert("/404.php", "Account registration is disabled", 403);
    exit;
}

include "../../src/partials.php";
include_once "../../src/config.php";
include_once "../../src/utils.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["username"], $_POST["password"])) {
        generate_alert("/account/register.php", "Not enough POST fields");
        exit;
    }

    $username = $_POST["username"];
    $username_length = strlen($username);
    if (ACCOUNT_USERNAME_LENGTH[0] > $username_length || $username_length > ACCOUNT_USERNAME_LENGTH[1]) {
        generate_alert("/account/register.php", sprintf("Username must be between %d-%d characters long", ACCOUNT_USERNAME_LENGTH[0], ACCOUNT_USERNAME_LENGTH[1]));
        exit;
    }

    if (!preg_match(ACCOUNT_USERNAME_REGEX, $username)) {
        generate_alert("/account/register.php", "Bad username");
        exit;
    }

    $password = $_POST["password"];
    if (ACCOUNT_PASSWORD_MIN_LENGTH > strlen($password)) {
        generate_alert("/account/register.php", "Password must be at least " . ACCOUNT_PASSWORD_MIN_LENGTH . " characters");
        exit;
    }

    $db = new PDO(DB_URL, DB_USER, DB_PASS);

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->rowCount() != 0) {
        generate_alert("/account/register.php", "The username has already been taken");
        exit;
    }

    $secret_key = generate_random_string(ACCOUNT_SECRET_KEY_LENGTH);
    $password = password_hash($password, PASSWORD_DEFAULT);

    $id = bin2hex(random_bytes(16));

    $stmt = $db->prepare("INSERT INTO users(id, username, password, secret_key) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $username, $password, $secret_key]);

    setcookie("secret_key", $secret_key, time() + ACCOUNT_COOKIE_MAX_LIFETIME, "/");
    header("Location: /account");
    exit;
}
?>

<html>

<head>
    <title>Register an account - <?php echo INSTANCE_NAME ?></title>
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
                        <p>Register an account in <?php echo INSTANCE_NAME ?></p>
                    </div>
                    <div class="box content">
                        <form action="/account/register.php" method="post">
                            <div>
                                <label for="username">Username</label>
                                <input type="text" name="username" id="form-username" required>
                            </div>
                            <div>
                                <label for="password">Password</label>
                                <input type="password" name="password" id="form-password" required>
                            </div>
                            <div>
                                <button type="submit">Register</button>
                            </div>
                        </form>
                        <p style="font-size: 12px;">
                            Since <?php echo INSTANCE_NAME ?> doesn't require email and password reset via email is
                            not supported, please remember your passwords!
                        </p>
                    </div>
                </section>
            </section>
        </div>
    </div>
</body>

</html>