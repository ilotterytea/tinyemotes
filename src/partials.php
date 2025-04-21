<?php
function html_navigation_bar()
{
    echo '' ?>
    <section class="navbar">
        <a href="/" class="brand" style="color:black;text-decoration:none;">
            <img src="/static/img/brand/mini.webp" alt="">
            <h2 style="margin-left:8px;font-size:24px;"><b><?php echo "alright.party" ?></b></h2>
        </a>
        <div class="links">
            <a href="/emotes" class="button">Emotes</a>
            <a href="/users.php" class="button">Users</a>
            <a href="/emotes/upload.php" class="button">Upload</a>
            <a href="/account" class="button">Account</a>
        </div>
        <?php
        if (isset($_SESSION["user_id"])) {
            echo '' ?>
            <a href="/users.php?id=<?php echo $_SESSION["user_id"] ?>" class="links" style="margin-left:auto;">
                Signed in as <?php echo $_SESSION["user_name"] ?> <img
                    src="/static/userdata/avatars/<?php echo $_SESSION["user_id"] ?>" width="24" height="24" />
            </a>
            <?php ;
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
            <form action="/emotes/search.php" method="get">
                <input type="text" name="q" style="padding:4px;"><br>
                <button type="submit" style="width:100%;margin-top:6px;">Find</button>
            </form>
        </div>
    </section>
    <?php ;
}