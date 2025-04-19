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
                            <?php echo isset($emotes) ? "Emotes" : "Emote" ?>
                        </div>
                        <div class="box content items">
                            <?php
                            if (isset($emotes)) {
                                foreach ($emotes as $e) {
                                    echo "<a class=\"box emote\" href=\"/emotes/" . $e->get_id() . "\">";
                                    echo "<img src=\"/static/userdata/emotes/" . $e->get_id() . "/2x." . $e->get_ext() . "\" alt=\"" . $e->get_code() . "\"/>";
                                    echo "<p>" . $e->get_code() . "</p>";
                                    echo "</a>";
                                }
                            } else {
                                // info
                                echo "";
                            }
                            ?>
                        </div>
                    </section>
                </section>
            </main>
        </div>
    </div>
</body>

</html>