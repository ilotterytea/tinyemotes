<?php
include "../../src/accounts.php";
authorize_user();

if (!isset($_SESSION["user_id"], $_SESSION["user_name"])) {
    header("Location: /account/login");
    exit;
}

include "../../src/partials.php";

?>

<html>

<head>
    <title>Account management - alright.party</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>

            <section class="content">
                <section class="box accman">
                    <h1>Account management</h1>

                    <form action="/account.php" method="POST" enctype="multipart/form-data">
                        <h2>Profile</h2>
                        <h3>Profile picture</h3>
                        <img src="/static/userdata/avatars/<?php echo $_SESSION["user_id"] ?>" id="pfp" width="64"
                            height="64">
                        <input type="file" name="pfp" id="pfp">

                        <h3>Username</h3>
                        <input type="text" name="username" value="<?php echo $_SESSION["user_name"] ?>">

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

</html>