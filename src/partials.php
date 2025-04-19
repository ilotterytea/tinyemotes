<?php
function html_navigation_bar()
{
    echo '' ?>
    <section class="navbar">
        <h1>AlrightTV</h1>
        <div class="links">
            <a href="/emotes" class="button">Emotes</a>
            <a href="/emotes/upload.php" class="button">Upload</a>
            <a href="/login" class="button">Log in...</a>
        </div>
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