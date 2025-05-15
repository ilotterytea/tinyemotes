<?php
include_once "user.php";

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

    public static function from_array(array $arr): Emote
    {
        $e = new Emote();

        $e->id = $arr["id"];
        $e->code = $arr["code"];
        $e->ext = $arr["ext"] ?? "webp";
        $e->uploaded_by = $arr["uploaded_by"];
        $e->created_at = strtotime($arr["created_at"] ?? 0);
        $e->is_in_user_set = $arr["is_in_user_set"] ?? false;
        $e->visibility = $arr["visibility"];
        $e->source = $arr["source"] ?? null;
        $e->tags = $arr["tags"] ?? [];

        if (isset($arr["total_rating"], $arr["average_rating"])) {
            $e->rating = [
                "total" => $arr["total_rating"],
                "average" => $arr["average_rating"]
            ];
        } else {
            $e->rating = $arr["rating"] ?? null;
        }

        return $e;
    }

    public static function from_array_with_user(array $arr, PDO &$db): Emote
    {
        if ($arr["uploaded_by"]) {
            $arr["uploaded_by"] = User::get_user_by_id($db, $arr["uploaded_by"]);
        }

        return Emote::from_array($arr);
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

class Emoteset
{
    public string $id;
    public string $name;
    public User|null $owner;
    public array $emotes;

    public bool $is_default;

    public static function from_array(array $arr): Emoteset
    {
        $s = new Emoteset();

        $s->id = $arr["id"];
        $s->name = $arr["name"];
        $s->owner = $arr["owner_id"];
        $s->emotes = $arr["emotes"] ?? [];
        $s->is_default = $arr["is_default"] ?? false;

        return $s;
    }

    public static function from_array_extended(array $arr, string $user_id, PDO &$db): Emoteset
    {
        if ($arr["owner_id"]) {
            $arr["owner_id"] = User::get_user_by_id($db, $arr["owner_id"]);
        }

        $arr["emotes"] = fetch_all_emotes_from_emoteset($db, $arr["id"], $user_id);

        return Emoteset::from_array($arr);
    }

    public static function get_all_user_emotesets(PDO &$db, string $user_id): array
    {
        $stmt = $db->prepare("SELECT es.*, aes.is_default FROM emote_sets es
            INNER JOIN acquired_emote_sets aes ON aes.emote_set_id = es.id
            WHERE aes.user_id = ?
        ");
        $stmt->execute([$user_id]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $emote_sets = [];

        foreach ($rows as $row) {
            array_push($emote_sets, Emoteset::from_array_extended($row, $user_id, $db));
        }

        return $emote_sets;
    }
}

function fetch_all_emotes_from_emoteset(PDO &$db, string $emote_set_id, string $user_id, int|null $limit = null): array
{
    // fetching emotes
    $sql = "SELECT 
        e.id, e.created_at, e.visibility,
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $emotes = [];

    // fetching uploaders
    foreach ($rows as $row) {
        if ($row["uploaded_by"]) {
            $row["uploaded_by"] = User::get_user_by_id($db, $row["uploaded_by"]);
        }

        array_push($emotes, Emote::from_array($row));
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

function html_display_emotes(array $emotes)
{
    foreach ($emotes as $e) {
        echo "<a class='box emote column justify-center items-center' href='/emotes?id={$e->id}'>";

        if ($e->is_added_by_user()) {
            echo '<img src="/static/img/icons/yes.png" class="emote-check" />';
        }

        // icon
        echo '<div class="flex justify-center items-center grow emote-icon">';
        echo "<img src='/static/userdata/emotes/{$e->id}/2x.webp' alt='{$e->code}' />";
        echo '</div>';

        // info
        echo '<div class="flex column justify-bottom items-center emote-desc">';

        echo "<h1 title='{$e->code}'>{$e->code}</h1>";
        if ($e->get_uploaded_by()) {
            echo "<p>{$e->uploaded_by->username}</p>";
        }

        echo '</div></a>';
    }
}

function html_display_emoteset(array $emotesets)
{
    foreach ($emotesets as $es) {
        echo "<a href='/emotesets.php?id={$es->id}' class='box column small-gap'>";

        echo '<div>';
        echo "<p>$es->name</p>";
        echo '</div>';

        echo '<div class="small-gap row">';

        foreach ($es->emotes as $e) {
            echo "<img src='/static/userdata/emotes/{$e->id}/1x.webp' alt='{$e->code}' title='{$e->code}' height='16' />";
        }

        echo '</div></a>';

    }
}