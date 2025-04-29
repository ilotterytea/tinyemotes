<?php
class Emote
{
    public string $id;
    public string $code;
    public string $ext;
    public mixed $uploaded_by;
    public int $created_at;
    public mixed $rating;
    public bool $is_in_user_set;
    public int $visibility;

    function __construct($id, $code, $ext, $created_at, $uploaded_by, $is_in_user_set, $rating, $visibility)
    {
        $this->id = $id;
        $this->code = $code;
        $this->ext = $ext;
        $this->created_at = $created_at;
        $this->uploaded_by = $uploaded_by;
        $this->is_in_user_set = $is_in_user_set;
        $this->rating = $rating;
        $this->visibility = $visibility;
    }

    function get_id()
    {
        return $this->id;
    }

    function get_code()
    {
        return $this->code;
    }

    function get_ext()
    {
        return $this->ext;
    }

    function get_created_at()
    {
        return $this->created_at;
    }

    function get_uploaded_by()
    {
        return $this->uploaded_by;
    }

    function is_added_by_user()
    {
        return $this->is_in_user_set;
    }

    function get_rating()
    {
        return $this->rating;
    }

    function get_visibility()
    {
        return $this->visibility;
    }
}

function html_random_emote(PDO &$db)
{
    $stmt = $db->prepare("SELECT id, ext, code FROM emotes WHERE visibility = 1 AND is_featured = false ORDER BY RAND() LIMIT 1");
    $stmt->execute();

    if ($row = $stmt->fetch()) {
        echo ''
            ?>
        <section class="box" id="box-random-emote">
            <div class="box navtab">
                <p>Random emote</p>
            </div>
            <div class="box content center">
                <a href="/emotes?id=<?php echo $row["id"] ?>">
                    <img src="/static/userdata/emotes/<?php echo $row["id"] . '/3x.' . $row["ext"] ?>"
                        alt="<?php echo $row["code"] ?>" width="192">
                </a>
            </div>
        </section>
        <?php
        ;
    }
}

function html_featured_emote(PDO &$db)
{
    $stmt = $db->prepare("SELECT id, ext, code FROM emotes WHERE visibility = 1 AND is_featured = true ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();

    if ($row = $stmt->fetch()) {
        echo ''
            ?>
        <section class="box" id="box-featured-emote">
            <div class="box navtab">
                <p>Featured emote</p>
            </div>
            <div class="box content center">
                <a href="/emotes?id=<?php echo $row["id"] ?>">
                    <img src="/static/userdata/emotes/<?php echo $row["id"] . '/3x.' . $row["ext"] ?>"
                        alt="<?php echo $row["code"] ?>" width="192">
                </a>
            </div>
        </section>
        <?php
        ;
    }
}