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

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
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

    if (isset($_FILES["pfp"]) && !empty($_FILES["pfp"]["tmp_name"])) {
        $pfp = $_FILES["pfp"];

        if (
            $err = create_image_bundle(
                $pfp["tmp_name"],
                $_SERVER["DOCUMENT_ROOT"] . "/static/userdata/avatars/" . $_SESSION["user_id"],
                ACCOUNT_PFP_MAX_SIZE[0],
                ACCOUNT_PFP_MAX_SIZE[1],
                true,
                true
            )
        ) {
            generate_alert("/account", sprintf("Error occurred while processing the profile picture (%d)", $err));
            exit;
        }
    }

    if (isset($_FILES["banner"]) && !empty($_FILES["banner"]["tmp_name"])) {
        $banner = $_FILES["banner"];

        if (
            $err = create_image_bundle(
                $banner["tmp_name"],
                $_SERVER["DOCUMENT_ROOT"] . "/static/userdata/banners/" . $_SESSION["user_id"],
                ACCOUNT_BANNER_MAX_SIZE[0],
                ACCOUNT_BANNER_MAX_SIZE[1],
                true,
                true
            )
        ) {
            generate_alert("/account", sprintf("Error occurred while processing the profile banner (%d)", $err));
            exit;
        }
    }

    if (isset($_FILES["badge"]) && !empty($_FILES["badge"]["tmp_name"])) {
        $badge = $_FILES["badge"];
        $badge_id = bin2hex(random_bytes(16));
        if (
            $err = create_image_bundle(
                $badge["tmp_name"],
                $_SERVER["DOCUMENT_ROOT"] . "/static/userdata/badges/" . $badge_id,
                ACCOUNT_BADGE_MAX_SIZE[0],
                ACCOUNT_BADGE_MAX_SIZE[1],
                true,
                true
            )
        ) {
            generate_alert("/account", sprintf("Error occurred while processing the personal badge (%d)", $err));
            exit;
        }

        $db->prepare("DELETE FROM user_badges WHERE badge_id != ? AND user_id = ?")->execute([$badge_id, $_SESSION["user_id"]]);
        $db->prepare("INSERT INTO badges(id, uploaded_by) VALUES (?, ?)")->execute([$badge_id, $_SESSION["user_id"]]);
        $db->prepare("INSERT INTO user_badges(badge_id, user_id) VALUES (?, ?)")->execute([$badge_id, $_SESSION["user_id"]]);
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

                    <form action="/account/" method="POST" enctype="multipart/form-data">
                        <h2>Profile</h2>
                        <h3>Profile picture</h3>
                        <?php
                        if (is_dir("../static/userdata/avatars/" . $_SESSION["user_id"])) {
                            echo '<img src="/static/userdata/avatars/' . $_SESSION["user_id"] . '/2x.webp" id="pfp" width="64" height="64">';
                        } else {
                            echo "<p>You don't have profile picture</p>";
                        }
                        ?>
                        <input type="file" name="pfp">

                        <h3>Profile banner</h3>
                        <?php
                        if (is_dir("../static/userdata/banners/" . $_SESSION["user_id"])) {
                            echo '<img src="/static/userdata/banners/' . $_SESSION["user_id"] . '/2x.webp" id="banner" width="256">';
                        } else {
                            echo "<p>You don't have profile banner</p>";
                        }
                        ?>
                        <input type="file" name="banner">

                        <h3>Personal badge</h3>
                        <?php
                        $stmt = $db->prepare("SELECT badge_id FROM user_badges WHERE user_id = ?");
                        $stmt->execute([$_SESSION["user_id"]]);

                        if ($row = $stmt->fetch()) {
                            echo '<div class="box row items-center justify-between">';
                            echo '<img src="/static/userdata/badges/' . $row["badge_id"] . '/1x.webp" id="badge">';
                            echo '<img src="/static/userdata/badges/' . $row["badge_id"] . '/2x.webp" id="badge">';
                            echo '<img src="/static/userdata/badges/' . $row["badge_id"] . '/3x.webp" id="badge">';
                            echo '</div>';
                        } else {
                            echo "<p>You don't have personal badge</p>";
                        }
                        ?>
                        <input type="file" name="badge">

                        <h3>Username</h3>
                        <input type="text" name="username" id="username" value="<?php echo $_SESSION["user_name"] ?>">

                        <button type="submit">Save</button>
                    </form>

                    <hr>

                    <div>
                        <h2>Connections</h2>
                        <div>
                            <?php
                            $stmt = $db->prepare("SELECT * FROM connections WHERE user_id = ?");
                            $stmt->execute([$_SESSION["user_id"]]);
                            $connections = $stmt->fetchAll();
                            $platforms = ["twitch"];

                            foreach ($platforms as $platform) {
                                $connection = null;
                                $key = array_search($platform, array_column($connections, "platform"));

                                if (!is_bool($key)) {
                                    $connection = $connections[$key];
                                }

                                echo "<div class='box $platform row small-gap items-center'>";
                                echo "<div><img src='/static/img/icons/connections/$platform.webp' alt='' width='52' height='52' /></div>";

                                echo "<div class='column grow'>";
                                echo "<b>" . ucfirst($platform) . "</b>";

                                // TODO: check if connection is still alive
                                if ($connection == null) {
                                    echo "<i>Not connected</i>";
                                } else {
                                    echo "<i>" . $connection["alias_id"] . "</i>";
                                }

                                echo "</div>";

                                echo "<div class='column'>";

                                if ($connection == null) {
                                    echo "<a href='/account/login/$platform.php'>";
                                    echo '<img src="/static/img/icons/disconnect.png" alt="Connect" title="Connect" />';
                                    echo "</a>";
                                } else {
                                    echo "<a href='/account/login/$platform.php?disconnect'>";
                                    echo '<img src="/static/img/icons/connect.png" alt="Disconnect" title="Disconnect" />';
                                    echo "</a>";
                                }

                                echo "</div></div>";
                            }
                            ?>
                        </div>
                    </div>

                    <hr>

                    <form action="/account/security.php" method="post">
                        <h2>Security & Privacy</h2>
                        <div>
                            <?php
                            $stmt = $db->prepare("SELECT CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END as set_password FROM users WHERE id = ?");
                            $stmt->execute([$_SESSION["user_id"]]);
                            $set_password = $stmt->fetch()[0];
                            if ($set_password): ?>
                                <label for="password-current">Current password:</label>
                                <input type="password" name="password-current" id="form-password-current" required>
                            <?php endif; ?>
                            <label for="password-new">New password:</label>
                            <input type="password" name="password-new" id="form-password-new">
                        </div>
                        <div>
                            <input type="checkbox" name="make-private" value="1" id="form-make-private" <?php
                            $stmt = $db->prepare("SELECT private_profile FROM user_preferences WHERE id = ?");
                            $stmt->execute([$_SESSION["user_id"]]);
                            if (intval($stmt->fetch()[0]) == 1) {
                                echo 'checked';
                            }
                            ?>>
                            <label for="make-private" class="inline">Make profile private</label>
                            <p class="font-small">Enabling this feature will hide your authorship of uploaded emotes and
                                actions.</p>

                        </div>
                        <div>
                            <input type="checkbox" name="signout-everywhere" value="1" id="form-signout-everywhere">
                            <label for="signout-everywhere" class="inline">Sign out everywhere</label>
                        </div>

                        <button type="submit">Apply</button>
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