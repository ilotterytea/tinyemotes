<?php
include_once "../src/config.php";
include_once "../src/user.php";
include_once "../src/partials.php";
include_once "../src/utils.php";
include_once "../src/accounts.php";
include_once "../src/alert.php";
include_once "../src/emote.php";

authorize_user();

$is_json = isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json";

$id = $_GET["id"] ?? "";
$alias_id = $_GET["alias_id"] ?? "";

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if ($id == "" && $alias_id == "") {
    if (!ACCOUNT_PUBLIC_LIST) {
        generate_alert("/404.php", "The public list of accounts is disabled", 403);
        exit;
    }

    $page = max(1, intval($_GET["p"] ?? "1"));
    $limit = 25;
    $offset = ($page - 1) * $limit;
    $search = "%" . ($_GET["q"] ?? "") . "%";
    $stmt = $db->prepare("SELECT id, username, joined_at, last_active_at
    FROM users
    WHERE username LIKE ?
    ORDER BY last_active_at DESC LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $search, PDO::PARAM_STR);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ?");
    $count_stmt->execute([$search]);

    $total_users = $count_stmt->fetch()[0];
    $total_pages = ceil($total_users / $limit);

    if ($is_json) {
        header("Content-Type: application/json");
        echo json_encode([
            "status_code" => 200,
            "message" => null,
            "data" => [
                "all_user_count" => intval($all_user_count),
                "users" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        ]);
        exit;
    }

    echo '' ?>
    <html>

    <head>
        <title>User list - <?php echo INSTANCE_NAME ?></title>
        <link rel="stylesheet" href="/static/style.css">
        <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    </head>

    <body>
        <div class="container">
            <div class="wrapper">
                <?php html_navigation_bar() ?>
                <section class="content row">
                    <section class="sidebar">
                        <?php html_navigation_search(); ?>
                    </section>
                    <section class="content">
                        <section class="box">
                            <div class="box navtab">
                                <p><?php echo $total_users ?> Users - <?php echo "Page $page/$total_pages" ?></p>
                            </div>
                            <div class="box content">
                                <?php
                                if ($total_users != 0) {
                                    echo '<table>';
                                    echo '<tr>';
                                    echo '<th></th><th style="width:80%;">Username</th><th>Last active</th>';
                                    echo '<tr>';
                                    while ($row = $stmt->fetch()) {
                                        $diff = time() - strtotime($row["last_active_at"]);

                                        $last_active = "moments";

                                        if ($diff > 5) {
                                            $last_active = format_timestamp($diff);
                                        }

                                        echo '<tr><td>';
                                        echo '<img src="/static/';
                                        if (is_dir("static/userdata/avatars/" . $row["id"])) {
                                            echo 'userdata/avatars/' . $row["id"] . '/1x.webp';
                                        } else {
                                            echo 'img/defaults/profile_picture.png';
                                        }
                                        echo '" width="24" height="24">';
                                        echo '</td><td><a href="/users.php?id=' . $row["id"] . '">' . $row["username"] . '</a></td>';
                                        echo "<td>$last_active ago</td>";
                                        echo '</tr>';
                                    }
                                    echo '</table>';
                                } else {
                                    echo '<p>Nothing found...</p>';
                                }
                                ?>
                            </div>
                        </section>
                        <?php if ($total_pages > 1) {
                            echo '' ?>

                            <section class="box center row">
                                <?php
                                html_pagination($total_pages, $page, "/users.php?q=" . substr($search, 1, strlen($search) - 2));
                                ?>
                            </section>
                            <?php
                        }
                        ?>
                    </section>
            </div>
        </div>
    </body>

    </html>
    <?php ;
    exit;
}

// --- fetching user
$user = null;

// fetching user by connection
if (isset($_GET["alias_id"])) {
    $alias_id = $_GET["alias_id"];
    $platform = $_GET["platform"] ?? "twitch";

    $stmt = $db->prepare("SELECT u.id FROM users u
        INNER JOIN connections co ON co.alias_id = ? AND co.platform = ?
        WHERE co.user_id = u.id
    ");
    $stmt->execute([$alias_id, $platform]);

    if ($row = $stmt->fetch()) {
        $user = User::get_user_by_id($db, $row["id"]);
    }
}
// fetching user by internal id
else if (isset($_GET["id"])) {
    $user = User::get_user_by_id($db, $_GET["id"]);
}

if (!$user) {
    generate_alert("/404.php", "The user you requested cannot be found", 404);
    exit;
}

// User preferences
$stmt = $db->prepare("SELECT * FROM user_preferences WHERE id = ?");
$stmt->execute([$user->id]);

$user_preferences = $stmt->fetch(PDO::FETCH_ASSOC);

$public_profile = !$user_preferences["private_profile"] || $user->id == ($_SESSION["user_id"] ?? "");

// fetching emote sets
$emote_sets = Emoteset::get_all_user_emotesets($db, $user->id);
$active_emote_set = null;
foreach ($emote_sets as $es) {
    if ($es->is_default) {
        $active_emote_set = $es;
        break;
    }
}

// gathering uploaded emotes
$uploaded_emotes = [];

if ($public_profile) {
    $stmt = $db->prepare("SELECT e.id, e.code, e.uploaded_by, e.source, e.visibility
    FROM emotes e
    WHERE e.uploaded_by = ?
    ORDER BY e.created_at ASC
    ");
    $stmt->execute([$user->id]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        array_push($uploaded_emotes, Emote::from_array_with_user($row, $db));
    }
}

// gathering actions
$actions = [];

if ($public_profile) {
    $stmt = $db->prepare("SELECT a.* FROM actions a WHERE a.user_id = ? ORDER BY a.created_at DESC LIMIT 15");
    $stmt->execute([$user->id]);
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// TODO: add functionality

// calculating contributions
$stmt = $db->prepare("SELECT COUNT(*) FROM emotes WHERE uploaded_by = ?");
$stmt->execute([$user->id]);
$contributions = intval($stmt->fetch()[0]);

$stmt = $db->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = ?");
$stmt->execute([$user->id]);

$contributions += intval($stmt->fetch()[0]);

// getting status
$stmt = $db->prepare("SELECT * FROM roles r INNER JOIN role_assigns ra ON ra.user_id = ? WHERE ra.role_id = r.id");
$stmt->execute([$user->id]);

$role = $stmt->fetch(PDO::FETCH_ASSOC) ?? null;

// getting reactions
$stmt = $db->prepare("SELECT rate, COUNT(*) AS c FROM ratings WHERE user_id = ? GROUP BY rate ORDER BY c DESC");
$stmt->execute([$user->id]);

$fav_reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// getting favorite emote
$fav_emote = 1;

// getting custom badge
$stmt = $db->prepare("SELECT b.* FROM badges b
    INNER JOIN user_badges ub ON ub.user_id = ?
    WHERE b.id = ub.badge_id
");
$stmt->execute([$user->id]);

$custom_badge = null;
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $custom_badge = $row;
}

if ($is_json) {
    $user_data = (array) $user;

    unset($user_data["private_profile"]);

    $user_data["stats"] = [
        "contributions" => $contributions,
        "favorite_reaction_id" => $fav_reactions,
        "favorite_emote_id" => $fav_emote
    ];

    $user_data["active_emote_set_id"] = $active_emote_set->id;
    $user_data["emote_sets"] = $emote_sets;
    $user_data["uploaded_emotes"] = $uploaded_emotes;
    $user_data["actions"] = $actions;

    json_response([
        "status_code" => 200,
        "message" => null,
        "data" => $user_data
    ]);
    exit;
}
?>

<html>

<head>
    <title><?php echo sprintf("%s - %s", $user->username, INSTANCE_NAME) ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="background" style="background-image: url('/static/userdata/banners/<?php echo $user->id ?>/3x.webp');">
        <div class="background-layer"></div>
    </div>

    <div class="container">
        <div class="wrapper">
            <?php echo html_navigation_bar() ?>
            <section class="content row">
                <section class="sidebar flex column small-gap">
                    <!-- User -->
                    <section class="box">
                        <div class="box navtab flex items-center small-gap">
                            <?php
                            echo $user->username;

                            if ($custom_badge) {
                                echo ' <img src="/static/userdata/badges/' . $custom_badge["id"] . '/1x.webp" alt="" title="Personal badge" />';
                            }
                            ?>
                        </div>
                        <div class="box content justify-center items-center">
                            <?php
                            echo '<img src="/static/';
                            if (is_dir("static/userdata/avatars/" . $user->id)) {
                                echo 'userdata/avatars/' . $user->id . '/3x.webp';
                            } else {
                                echo 'img/defaults/profile_picture.png';
                            }
                            echo '" width="192" height="192">';
                            ?>
                        </div>
                    </section>
                    <!-- Role -->
                    <?php
                    if ($role) {
                        $bg_color_split = explode(":", $role["background_color"]);
                        $bg_color = match ($bg_color_split[0]) {
                            "solid" => sprintf("background: rgba(%s);", $bg_color_split[1]),
                            "gradient" => sprintf("background: linear-gradient(0deg, rgba(%s), rgba(%s));", $bg_color_split[1], $bg_color_split[2]),
                            "img" => sprintf("background-image: url('%s')", $bg_color_split[1]),
                            default => ""
                        };

                        if ($role["badge_id"]): ?>
                            <div class="box row small-gap items-center" style="<?php echo $bg_color; ?>">
                                <div>
                                    <img src="/static/userdata/badges/<?php echo $role["badge_id"] ?>/3x.webp"
                                        alt="<?php echo $role["name"] ?>" width="54" height="54">
                                </div>
                                <div class="column">
                                    <p><?php echo $role["name"] ?></p>
                                    <i style="color: gray">Role</i>
                                </div>
                            </div>
                        <?php endif;
                    } ?>
                    <!-- Stats -->
                    <section class="box">
                        <table class="vertical left">
                            <tr>
                                <th><img src="/static/img/icons/door_in.png"> Joined</th>
                                <?php
                                echo '<td title="';
                                echo date("M d, Y H:i:s", $user->joined_at);
                                echo ' UTC">about ' . format_timestamp(time() - $user->joined_at) . " ago</td>";
                                ?>
                            </tr>
                            <tr>
                                <th><img src="/static/img/icons/clock.png"> Last activity</th>
                                <?php
                                $diff = time() - $user->last_active_at;
                                if ($diff > 60) {
                                    echo '<td title="';
                                    echo date("M d, Y H:i:s", $user->last_active_at);
                                    echo ' UTC">about ' . format_timestamp($diff) . " ago</td>";
                                } else {
                                    echo '<td>Online</td>';
                                }
                                ?>
                            </tr>
                            <tr>
                                <th><img src="/static/img/icons/star.png"> Contributions</th>
                                <td><?php echo $contributions ?></td>
                            </tr>
                            <?php
                            if ($fav_reactions != null) { ?>
                                <tr>
                                    <th><img src="/static/img/icons/emoticon_happy.png"> Reactions</th>
                                    <td>
                                        <?php
                                        foreach ($fav_reactions as $reaction) {
                                            echo $reaction["c"] . ' <img src="/static/img/icons/ratings/' . $reaction["rate"] . '.png" alt="' . RATING_NAMES[$reaction["rate"]] . '" title="' . RATING_NAMES[$reaction["rate"]] . '">';
                                        }
                                        ?>
                                    </td>
                                </tr><?php
                            }
                            ?>
                            <?php
                            $stmt = $db->prepare("SELECT code FROM emotes WHERE id = ?");
                            $stmt->execute([$fav_emote]);

                            if ($row = $stmt->fetch()) {
                                echo '<tr>';
                                echo '<th><img src="/static/img/icons/heart.png"> Favorite emote</th>';
                                echo '<td>';
                                echo "<a href=\"/emotes?id=$fav_emote\">";
                                echo $row["code"] . ' <img src="/static/userdata/emotes/' . $fav_emote . '/1x.webp" width="16" height="16">';
                                echo '</a></td></tr>';
                            }
                            ?>
                        </table>
                    </section>
                    <!-- Buttons -->
                    <section class="box flex column small-gap" style="display: inline-block;">
                        <button onclick="open_tab('user-emotes')" id="user-emotes-button"><img
                                src="/static/img/icons/emotes/emote.png" alt=""> Emotes</button>
                        <button onclick="open_tab('user-emotesets')" id="user-emotesets-button"><img
                                src="/static/img/icons/emotes/emote_folder.png" alt=""> Emote
                            sets</button>
                        <?php if ($public_profile): ?>
                            <button onclick="open_tab('user-actions')" id="user-actions-button"><img
                                    src="/static/img/icons/tag_blue.png" alt=""> Actions</button>
                            <button onclick="open_tab('user-uploadedemotes')" id="user-uploadedemotes-button"><img
                                    src="/static/img/icons/emotes/emote_go.png" alt=""> Uploaded
                                emotes</button>
                        <?php endif; ?>
                    </section>
                </section>
                <section class="content" style="display: inline-block;">
                    <!-- Current emoteset -->
                    <section class="box grow user-tab" id="user-emotes">
                        <div class="box navtab row">
                            <div class="grow">
                                <?php echo !empty($active_emote_set) ? $active_emote_set->name : "Emotes" ?>
                            </div>
                            <?php html_emotelist_mode() ?>
                        </div>
                        <div class="box content items flex">
                            <?php if (!empty($active_emote_set)) {
                                if (!empty($active_emote_set->emotes)) {
                                    html_display_emotes($active_emote_set->emotes);
                                } else {
                                    echo '<p>No emotes found... ' . ((($_SESSION["user_id"] ?? "") == $id) ? 'Start adding emotes and they will appear here! :)</p>' : '</p>');
                                }
                            } else {
                                echo "<p>This user doesn't have active emote set.</p>";
                            }
                            ?>
                        </div>
                    </section>
                    <!-- Emote sets -->
                    <section class="box grow user-tab" id="user-emotesets">
                        <div class="box navtab">
                            Emote sets
                        </div>
                        <div class="box content items">
                            <?php
                            if (!empty($emote_sets)) {
                                html_display_emoteset($emote_sets);
                            } else {
                                echo '<p>No emote sets found... ' . ((($_SESSION["user_id"] ?? "") == $id) ? 'Start adding emotes and you will have one! :)</p>' : '</p>');
                            }
                            ?>
                        </div>
                    </section>
                    <?php if ($public_profile): ?>
                        <!-- Actions -->
                        <section class="box grow user-tab" id="user-actions">
                            <div class="box navtab">
                                Actions
                                <?php echo $user_preferences["private_profile"] ? " <img src='/static/img/icons/eye.png' alt='(Private)' title='You are the only one who sees this' />" : "" ?>
                            </div>
                            <div class="box content">
                                <?php
                                if (empty($actions)) {
                                    echo "<p>This user has done nothing bad or good...</p>";
                                }

                                foreach ($actions as $action) {
                                    echo '<div class="row">';

                                    list($action_name, $preposition, $icon_name) = match ($action["action_type"]) {
                                        "EMOTESET_ADD" => ["added", "to", "yes.png"],
                                        "EMOTESET_REMOVE" => ["removed", "from", "no.png"],
                                        "EMOTESET_ALIAS" => ["renamed", "in", "pencil.png"],
                                        "EMOTE_CREATE" => ["created", null, "new_emote.png"],
                                        "EMOTE_DELETE" => ["deleted", null, "deleted_emote.png"],
                                        "EMOTE_RENAME" => ["renamed", null, "renamed_emote.png"]
                                    };

                                    echo "<div><img src='/static/img/icons/$icon_name' width='16' /></div>";

                                    echo '<div class="column">';
                                    echo '<p>';
                                    echo '<i>' . $user->username . '</i> ';

                                    $payload = json_decode($action["action_payload"], true);

                                    list($action_root, $action_sub) = explode("_", $action["action_type"]);

                                    switch ($action_root) {
                                        case "EMOTESET": {
                                            $e_stmt = $db->prepare("SELECT COUNT(*) FROM emotes WHERE id = ?");
                                            $e_stmt->execute([$payload["emote"]["id"]]);

                                            echo "$action_name emote <a href=\"";

                                            if ($e_stmt->rowCount() == 1) {
                                                echo '/emotes?id=' . $payload["emote"]["id"] . '">';
                                                echo '<img src="/static/userdata/emotes/' . $payload["emote"]["id"] . '/1x.webp" height="16" /> ';
                                            } else {
                                                echo '">';
                                            }

                                            if (isset($payload["emote"]["original_code"])) {
                                                echo $payload["emote"]["original_code"] . '</a> to ';
                                                echo "<a href=\"";

                                                if ($e_stmt->rowCount() == 1) {
                                                    echo '/emotes?id=' . $payload["emote"]["id"] . '">';
                                                    echo '<img src="/static/userdata/emotes/' . $payload["emote"]["id"] . '/1x.webp" height="16" /> ';
                                                } else {
                                                    echo '">';
                                                }

                                                echo $payload["emote"]["code"] . '</a>';
                                            } else {
                                                echo $payload["emote"]["code"] . '</a>';
                                            }

                                            $es_stmt = $db->prepare("SELECT COUNT(*) FROM emote_sets WHERE id = ?");
                                            $es_stmt->execute([$payload["emoteset"]["id"]]);

                                            echo " $preposition <a href=\"";
                                            if ($es_stmt->rowCount() == 1) {
                                                echo '/emotesets.php?id=' . $payload["emoteset"]["id"];
                                            }

                                            echo '">' . $payload["emoteset"]["name"] . '</a>';
                                            break;
                                        }
                                        case "EMOTE": {
                                            $e_stmt = $db->prepare("SELECT COUNT(*) FROM emotes WHERE id = ?");
                                            $e_stmt->execute([$payload["emote"]["id"]]);

                                            echo "$action_name emote <a href=\"";

                                            if ($e_stmt->rowCount() == 1) {
                                                echo '/emotes?id=' . $payload["emote"]["id"] . '">';
                                                echo '<img src="/static/userdata/emotes/' . $payload["emote"]["id"] . '/1x.webp" height="16" /> ';
                                            } else {
                                                echo '">';
                                            }

                                            echo $payload["emote"]["code"] . '</a>';
                                            break;
                                        }
                                        default: {
                                            echo "something that we don't know";
                                            break;
                                        }
                                    }

                                    echo '</p>';
                                    echo '<span class="font-small" style="color: gray;">[' . format_timestamp(time() - strtotime($action["created_at"])) . ' ago]</span> ';
                                    echo '</div></div>';
                                }
                                ?>
                            </div>
                        </section>

                        <!-- Uploaded emotes -->
                        <section class="box grow user-tab" id="user-uploadedemotes">
                            <div class="box navtab row">
                                <div class="grow">
                                    Uploaded emotes
                                    <?php echo $user_preferences["private_profile"] ? " <img src='/static/img/icons/eye.png' alt='(Private)' title='You are the only one who sees this' />" : "" ?>
                                </div>
                            </div>
                            <div class="box content items">
                                <?php html_display_emotes($uploaded_emotes); ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </section>
            </section>
        </div>
    </div>
</body>

<script>
    function open_tab(name) {
        const body = document.getElementById(name);
        const tabs = document.querySelectorAll(".user-tab");

        for (let tab of tabs) {
            tab.style.display = (tab.getAttribute("id") == name) ? "flex" : "none";
        }
    }

    open_tab("user-emotes");

    document.getElementById("sidebar").style.display = "block";
</script>

</html>