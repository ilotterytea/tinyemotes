<?php
include_once "../src/config.php";
include_once "../src/partials.php";

$software = [
    "Standalone clients" =>
        [
            [
                "name" => "Tinyrino",
                "author" => "ilotterytea",
                "desc" => "Tinyrino is a fork of Chatterino7 (which is a fork of Chatterino 2). This fork supports TinyEmotes, a software that allows you to host your emotes on your own instances.",
                "download_url" => "https://github.com/ilotterytea/tinyrino/releases",
                "source_url" => "https://github.com/ilotterytea/tinyrino"
            ]
        ],
    "Web extensions" => [],
    "Chatbots" => [],
    "Other tools" => []
];
?>

<html>

<head>
    <title>Software - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php html_navigation_bar() ?>
            <section class="content">
                <?php
                foreach ($software as $software_name => $sw) {
                    echo '<section class="box">';
                    echo "<div class='box navtab'>$software_name</div>";
                    echo '<div class="box content">';

                    if (empty($sw)) {
                        echo '<p>There are no software in this category! They will appear here as soon as they support TinyEmotes.</p>';
                    }

                    foreach ($sw as $s) {
                        $name_lower = strtolower($s["name"]);
                        echo '<div class="box row small-gap">';
                        echo "<div><img src='/static/img/software/$name_lower/icon.png' alt=''></div>";

                        echo '<div class="column grow small-gap">';
                        echo '<div class="row"><h1>' . $s["name"] . '</h1><p style="font-size:10px;">by ' . $s["author"] . '</p></div>';
                        echo '<p>' . $s["desc"] . '</p>';

                        $screenshot_path = "./static/img/software/$name_lower/screenshots";
                        if (is_dir($screenshot_path)) {
                            echo '<div class="row small-gap screenshots">';
                            foreach (new DirectoryIterator($screenshot_path) as $file) {
                                if ($file->isDot()) {
                                    continue;
                                }

                                echo "<a href='$screenshot_path/$file' target='_blank'><img src='$screenshot_path/$file' alt=''></a>";
                            }
                            echo '</div>';
                        }

                        echo '</div>';

                        echo '<div class="column small-gap items-center">';
                        echo '<a href="' . $s["download_url"] . '" target="_blank" class="button green big">Download</a>';
                        echo '<a href="' . $s["source_url"] . '" target="_blank">[ Source code ]</a>';
                        echo '</div></div>';
                    }

                    echo '</div></section>';
                }
                ?>
            </section>
        </div>
    </div>
</body>

</html>