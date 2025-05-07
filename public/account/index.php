<?php
include_once "../../src/alert.php";
include "../../src/accounts.php";
include "../../src/partials.php";
include_once "../../src/config.php";
include_once "../../src/utils.php";
include_once "../../src/images.php";

authorize_user();

if (!isset($_SESSION["user_id"], $_SESSION["user_name"])) {
    header("Location: /account/login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $db = new PDO(DB_URL, DB_USER, DB_PASS);

    $username = str_safe($_POST["username"] ?? "", ACCOUNT_USERNAME_LENGTH[1]);

    if (!empty($username) && $username != $_SESSION["user_name"]) {
        if (!preg_match(ACCOUNT_USERNAME_REGEX, $username)) {
            generate_alert("/account", "Bad username");
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() == 0) {
            $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $_SESSION["user_id"]]);
        } else {
            generate_alert("/account", "The username has already taken");
            exit;
        }
    }

    if (isset($_FILES["pfp"])) {
        $pfp = $_FILES["pfp"];
        resize_image(
            $pfp["tmp_name"],
            "../static/userdata/avatars/" . $_SESSION["user_id"],
            ACCOUNT_PFP_MAX_SIZE[0],
            ACCOUNT_PFP_MAX_SIZE[1],
            false,
            true
        );
    }

    if (isset($_FILES["banner"])) {
        $banner = $_FILES["banner"];
        resize_image(
            $banner["tmp_name"],
            "../static/userdata/banners/" . $_SESSION["user_id"],
            ACCOUNT_BANNER_MAX_SIZE[0],
            ACCOUNT_BANNER_MAX_SIZE[1],
            false,
            true
        );
    }

    $db = null;
    generate_alert("/account", "Your changes have been applied!", 200);
    exit;
}

?>

<html>

<head>
    <title>Account management - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>

            <section class="content">
                <?php display_alert() ?>
                <section class="box accman">
                    <h1>Account management</h1>

                    <form action="/account" method="POST" enctype="multipart/form-data">
                        <h2>Profile</h2>
                        <h3>Profile picture</h3>
                        <?php
                        if (is_file("../static/userdata/avatars/" . $_SESSION["user_id"])) {
                            echo '<img src="/static/userdata/avatars/' . $_SESSION["user_id"] . '" id="pfp" width="64" height="64">';
                        } else {
                            echo "<p>You don't have profile picture</p>";
                        }
                        ?>
                        <input type="file" name="pfp">

                        <h3>Profile banner</h3>
                        <?php
                        if (is_file("../static/userdata/banners/" . $_SESSION["user_id"])) {
                            echo '<img src="/static/userdata/banners/' . $_SESSION["user_id"] . '" id="banner" width="192" height="108">';
                        } else {
                            echo "<p>You don't have profile banner</p>";
                        }
                        ?>
                        <input type="file" name="banner">

                        <h3>Username</h3>
                        <input type="text" name="username" id="username" value="<?php echo $_SESSION["user_name"] ?>">

                        <button type="submit">Save</button>
                    </form>

                    <hr>

                    <form action="/account/signout.php">
                        <h2>Security</h2>
                        <button type="submit">Sign out everywhere</button>
                    </form>

                    <form action="/account/delete.php">
                        <button class="red" type="submit">Delete me</button>
                    </form>
                </section>
            </section>
        </div>
    </div>
</body>

<script>
    const username = document.getElementById("username");
    let validUsername = "";

    username.addEventListener("input", (e) => {
        const regex = <?php echo ACCOUNT_USERNAME_REGEX ?>;

        if (regex.test(e.target.value) && e.target.value.length <= <?php echo ACCOUNT_USERNAME_LENGTH[1] ?>) {
            validUsername = e.target.value;
        } else {
            e.target.value = validUsername;
        }
    });
</script>

</html>