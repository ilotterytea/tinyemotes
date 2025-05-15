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

    public string|null $source;

    public array $tags;

    function __construct($id, $code, $ext, $created_at, $uploaded_by, $is_in_user_set, $rating, $visibility, $source, $tags)
    {
        $this->id = $id;
        $this->code = $code;
        $this->ext = $ext;
        $this->created_at = $created_at;
        $this->uploaded_by = $uploaded_by;
        $this->is_in_user_set = $is_in_user_set;
        $this->rating = $rating;
        $this->visibility = $visibility;
        $this->source = $source;
        $this->tags = $tags;
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

    function get_source()
    {
        return $this->source;
    }

    function get_tags(): array
    {
        return $this->tags;
    }
}

function fetch_all_emotes_from_emoteset(PDO &$db, string $emote_set_id, string $user_id, int|null $limit = null): array
{
    // fetching emotes
    $sql = "SELECT 
        e.id, e.created_at, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code,
        CASE WHEN up.private_profile = FALSE OR up.id = ? THEN e.uploaded_by ELSE NULL END AS uploaded_by
        FROM emotes e
        LEFT JOIN user_preferences up ON up.id = e.uploaded_by
        INNER JOIN emote_set_contents AS esc
        ON esc.emote_set_id = ?
        WHERE esc.emote_id = e.id";

    if ($limit) {
        $sql .= " LIMIT $limit";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $emote_set_id]);

    $emotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fetching uploaders
    foreach ($emotes as $e) {
        if ($e["uploaded_by"]) {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$e["uploaded_by"]]);

            $e["uploaded_by"] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    return $emotes;
}

function html_random_emote(PDO &$db)
{
    $stmt = $db->prepare("SELECT id, code FROM emotes WHERE visibility = 1 ORDER BY RAND() LIMIT 1");
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
                    <img src="/static/userdata/emotes/<?php echo $row["id"] ?>/3x.webp" alt="<?php echo $row["code"] ?>"
                        width="192">
                </a>
            </div>
        </section>
        <?php
        ;
    }
}

function html_featured_emote(PDO &$db)
{
    $stmt = $db->prepare("SELECT e.id, e.code FROM emotes e
    INNER JOIN emote_sets es ON es.is_featured = TRUE
    INNER JOIN emote_set_contents esc ON es.id = esc.emote_set_id
    WHERE e.visibility = 1 AND e.id = esc.emote_id ORDER BY esc.added_at DESC LIMIT 1");
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
                    <img src="/static/userdata/emotes/<?php echo $row["id"] ?>/3x.webp" alt="<?php echo $row["code"] ?>"
                        width="192">
                </a>
            </div>
        </section>
        <?php
        ;
    }
}