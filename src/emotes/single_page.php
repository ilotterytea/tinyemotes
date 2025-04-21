<?php
include_once "../../src/config.php";
?>
<html>

<head>
    <title>AlrightTV</title>
    <link rel="stylesheet" href="/static/style.css">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar(); ?>
            <main class="page">
                <section class="sidebar">
                    <?php html_navigation_search(); ?>
                </section>
                <section class="content">
                    <?php display_alert() ?>
                    <section class="box">
                        <div class="box navtab">
                            Emote - <?php echo $emote->get_code() ?>
                        </div>
                        <div class="box content">
                            <div class="emote-showcase">
                                <img src="<?php echo '/static/userdata/emotes/' . $emote->get_id() . '/' . '1x.' . $emote->get_ext() ?>"
                                    alt="<?php echo $emote->get_code() ?>">
                                <img src="<?php echo '/static/userdata/emotes/' . $emote->get_id() . '/' . '2x.' . $emote->get_ext() ?>"
                                    alt="<?php echo $emote->get_code() ?>">
                                <img src="<?php echo '/static/userdata/emotes/' . $emote->get_id() . '/' . '3x.' . $emote->get_ext() ?>"
                                    alt="<?php echo $emote->get_code() ?>">
                            </div>
                        </div>
                    </section>

                    <section class="box items row">
                        <?php if (isset($_SESSION["user_id"])) {
                            echo '' ?>
                            <div class="items row left full">
                                <?php
                                $db = new PDO(DB_URL, DB_USER, DB_PASS);
                                $added = false;

                                if (isset($_SESSION["user_emote_set_id"])) {
                                    $stmt = $db->prepare("SELECT id FROM emote_set_contents WHERE emote_set_id = ? AND emote_id = ?");
                                    $stmt->execute([$_SESSION["user_emote_set_id"], $emote->get_id()]);
                                    $added = $stmt->rowCount() > 0;
                                }

                                $db = null;
                                ?>
                                <form action="/emotes/setmanip.php" method="POST">
                                    <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                        style="display: none;">
                                    <?php
                                    if ($added) { ?>
                                        <input type="text" name="action" value="remove" style="display: none;">
                                        <button type="submit" class="red">Remove from my channel</button>
                                        <?php
                                    } else { ?>
                                        <input type="text" name="action" value="add" style="display: none;">
                                        <button type="submit" class="green">Add to my channel</button>
                                        <?php
                                    }
                                    ?>
                                </form>
                            </div>
                            <div class="items row right full">
                                <form action="/emotes/rate.php" method="POST">
                                    <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                        style="display: none;">
                                    <input type="text" name="rate" value="5" style="display:none;">
                                    <button type="submit" class="transparent gem"><img src="/static/img/icons/gem.png"
                                            alt="GEM!" title="IT'S A GEM!"></button>
                                </form>
                                <form action="/emotes/rate.php" method="POST">
                                    <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                        style="display: none;">
                                    <input type="text" name="rate" value="1" style="display:none;">
                                    <button type="submit" class="transparent coal"><img src="/static/img/icons/coal.png"
                                            alt="COAL!" title="IT'S A COAL!"></button>
                                </form>
                                <a class="button red" href="/emotes/report.php?id=<?php echo $emote->get_id() ?>">Report
                                    emote</a>
                            </div>
                            <?php
                        } else {
                            echo '' ?>
                            <p><a href="/account/login">Log in</a> to get additional features...</p>
                            <?php
                        }
                        ?>
                    </section>

                    <section class="box">
                        <table class="vertical">
                            <tr>
                                <th>Uploader</th>
                                <td><?php
                                $username = "anonymous";
                                $link = "#";

                                if ($emote->get_uploaded_by()) {
                                    $db = new PDO(DB_URL, DB_USER, DB_PASS);
                                    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                                    $stmt->execute([$emote->get_uploaded_by()]);

                                    if ($row = $stmt->fetch()) {
                                        $username = $row["username"];
                                        $link = "/users.php?id=" . $emote->get_uploaded_by();
                                    }

                                    $db = null;
                                }

                                echo "<a href=\"$link\">";
                                echo $username;
                                echo "</a>";

                                echo ', <span title="';
                                echo date("M d, Y H:i:s", $emote->get_created_at());
                                echo ' UTC">about ' . format_timestamp(time() - $emote->get_created_at()) . " ago</span>";
                                ?></td>
                            </tr>
                            <tr>
                                <th>Rating</th>
                                <td>Not rated</td>
                            </tr>
                        </table>
                    </section>

                    <section class="box">
                        <div class="content">
                            <?php
                            $db = new PDO(DB_URL, DB_USER, DB_PASS);
                            $stmt = $db->prepare("SELECT users.id, users.username
                            FROM users
                            INNER JOIN emote_sets AS es ON es.owner_id = users.id
                            INNER JOIN emote_set_contents AS ec ON ec.emote_set_id = es.id
                            WHERE ec.emote_id = ?");

                            $stmt->execute([$emote->get_id()]);
                            $count = $stmt->rowCount();

                            $db = null;

                            if ($count > 0) {
                                echo "<p>Added in $count channels</p>";
                            } else {
                                echo "No one has added this emote yet... :'(";
                            }
                            ?>
                            <div class="items row">
                                <?php
                                while ($row = $stmt->fetch()) {
                                    echo '<a href="/users.php?id=' . $row["id"] . '">' . $row["username"] . '</a>';
                                }
                                ?>
                            </div>
                        </div>
                    </section>
                </section>
            </main>
        </div>
    </div>
</body>

</html>