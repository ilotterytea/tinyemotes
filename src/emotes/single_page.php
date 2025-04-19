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
                        <div class="items row left full">
                            <form action="/emotes/add.php" method="POST">
                                <input type="text" name="id" value="<?php echo $emote->get_id() ?>"
                                    style="display: none;">
                                <button type="submit" class="green">Add to my channel</button>
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
                    </section>

                    <section class="box">
                        <table class="vertical">
                            <tr>
                                <th>Uploader</th>
                                <td><?php
                                echo '<a href="/users/' . "0" . '">' . "someone" . '</a>, ';
                                echo date("d M Y", $emote->get_created_at());
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
                            <p>Added in <?php echo 20 ?> channels</p>
                            <div class="items row">
                                <a href="/users/1">forsen</a>
                                <a href="/users/2">not_forsen</a>
                                <a href="/users/3">lidl_forsen</a>
                            </div>
                        </div>
                    </section>
                </section>
            </main>
        </div>
    </div>
</body>

</html>