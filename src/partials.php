<?php
function html_navigation_bar()
{
    include_once "config.php";

    echo '' ?>
    <section class="navbar">
        <a href="/" class="brand" style="color:black;text-decoration:none;">
            <img src="/static/img/brand/mini.webp" alt="">
            <h2 style="margin-left:8px;font-size:24px;"><b><?php echo INSTANCE_NAME ?></b></h2>
        </a>
        <div class="links">
            <a href="/emotes" class="button">Emotes</a>

            <?php if (EMOTESET_PUBLIC_LIST): ?>
                <a href="/emotesets.php">Emotesets</a>
            <?php endif; ?>

            <?php if (ACCOUNT_PUBLIC_LIST): ?>
                <a href="/users.php">Users</a>
            <?php endif; ?>

            <?php if (EMOTE_UPLOAD && (ANONYMOUS_UPLOAD || (isset($_SESSION["user_role"]) && $_SESSION["user_role"]["permission_upload"]))) {
                echo '<a href="/emotes/upload.php" class="button">Upload</a>';
            } ?>
            <a href="/account" class="button">Account</a>
            <?php
            if (isset($_SESSION["user_id"])) {
                $db = new PDO(DB_URL, DB_USER, DB_PASS);

                // getting inbox
                $stmt = $db->prepare("SELECT COUNT(*) FROM inbox_messages WHERE recipient_id = ? AND has_read = false");
                $stmt->execute([$_SESSION["user_id"]]);
                $unread_count = intval($stmt->fetch()[0]);
                echo '' ?>
                <a href="/inbox.php" class="button">
                    Inbox <?php echo $unread_count > 0 ? "($unread_count)" : "" ?>
                </a>
                <?php ;
                $stmt = null;

                if (isset($_SESSION["user_role"])) {
                    if (REPORTS_ENABLE && $_SESSION["user_role"]["permission_report"]) {
                        // getting reports
                        $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE sender_id = ? AND resolved_by IS NULL");
                        $stmt->execute([$_SESSION["user_id"]]);
                        $unread_count = intval($stmt->fetch()[0]);

                        echo '' ?>
                        <a href="/report/list.php" class="button">
                            Reports <?php echo $unread_count > 0 ? "($unread_count)" : "" ?>
                        </a>
                        <?php ;
                    }

                    if (MOD_SYSTEM_DASHBOARD && ($_SESSION["user_role"]["permission_approve_emotes"] || $_SESSION["user_role"]["permission_report_review"])) {
                        $system_count = 0;

                        if ($_SESSION["user_role"]["permission_approve_emotes"] && MOD_EMOTES_APPROVE) {
                            $system_count += intval($db->query("SELECT COUNT(*) FROM emotes WHERE visibility = 2")->fetch()[0]);
                        }

                        if ($_SESSION["user_role"]["permission_report_review"]) {
                            $system_count += intval($db->query("SELECT COUNT(*) FROM reports WHERE resolved_by IS NULL")->fetch()[0]);
                        }

                        echo '<a href="/system" class="button">System';
                        if ($system_count > 0) {
                            echo " ($system_count)";
                        }
                        echo '</a>';
                    }
                }

                $stmt = null;
                $db = null;
            }
            ?>
        </div>
        <?php
        if (isset($_SESSION["user_id"])) {
            echo '<a href="/users.php?id=' . $_SESSION["user_id"] . '" class="links" style="margin-left:auto;">';
            echo 'Signed in as ' . $_SESSION["user_name"] . ' ';
            echo '<img src="/static/';
            if (
                is_file($_SERVER['DOCUMENT_ROOT'] . "/static/userdata/avatars/" . $_SESSION["user_id"])
            ) {
                echo 'userdata/avatars/' . $_SESSION["user_id"];
            } else {
                echo 'img/defaults/profile_picture.png';
            }
            echo '" width="24" height="24" />';
            echo '</a>';
        }
        ?>
    </section>
    <?php ;
}

function html_navigation_search()
{
    echo '' ?>
    <section class="box">
        <div class="box navtab">
            Search...
        </div>
        <div class="box content">
            <form action="<?php echo $_SERVER["REQUEST_URI"] ?>" method="GET">
                <input type="text" name="q" style="padding:4px;" value="<?php echo $_GET["q"] ?? "" ?>"><br>
                <?php
                if (str_starts_with($_SERVER["REQUEST_URI"], "/emotes")) {
                    ?>
                    <label for="sort_by">Sort by</label>
                    <select name="sort_by">
                        <option value="high_ratings" <?php echo ($_GET["sort_by"] ?? "") == "high_ratings" ? "selected" : "" ?>>
                            High ratings</option>
                        <option value="low_ratings" <?php echo ($_GET["sort_by"] ?? "") == "low_ratings" ? "selected" : "" ?>>Low
                            ratings</option>
                        <option value="recent" <?php echo ($_GET["sort_by"] ?? "") == "recent" ? "selected" : "" ?>>Recent
                        </option>
                        <option value="oldest" <?php echo ($_GET["sort_by"] ?? "") == "oldest" ? "selected" : "" ?>>Oldest
                        </option>
                    </select>
                    <?php
                }
                ?>
                <button type="submit" style="width:100%;margin-top:6px;">Find</button>
            </form>
        </div>
    </section>
    <?php ;
}

function html_pagination(int $total_pages, int $current_page, string $redirect)
{
    if (str_contains($redirect, "?")) {
        $redirect .= "&p=";
    } else {
        $redirect .= "?p=";
    }

    if ($total_pages > 1) {
        echo '' ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo $redirect . ($current_page - 1) ?>">[ prev ]</a>
            <?php endif; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo $redirect . ($current_page + 1) ?>">[ next ]</a>
            <?php endif; ?>

        </div>
        <?php ;
    }
}