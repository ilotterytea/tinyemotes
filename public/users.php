<?php
include_once "../src/config.php";
include_once "../src/user.php";
include_once "../src/partials.php";
include_once "../src/utils.php";
include_once "../src/accounts.php";
include_once "../src/alert.php";

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
                                        if (is_file("static/userdata/avatars/" . $row["id"])) {
                                            echo 'userdata/avatars/' . $row["id"];
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

$stmt = null;

if ($id != "") {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
} else if ($alias_id != "") {
    $stmt = $db->prepare("SELECT u.* FROM users u
    INNER JOIN connections co ON (co.alias_id = ? AND co.platform = 'twitch')
    WHERE co.user_id = u.id
    ");
    $stmt->execute([$alias_id]);
}

$user = null;

if ($row = $stmt->fetch()) {
    $user = new User($row);
}

if ($user == null) {
    generate_alert("/404.php", "The user you requested cannot be found", 404);
    exit;
}

// --- EMOTE SETS ---
// TODO: OPTIMIZE IT ASAP!!!
$emote_sets = [];
$active_emote_set = null;

// gathering acquired emote sets
$stmt = $db->prepare("SELECT emote_set_id, is_default FROM acquired_emote_sets WHERE user_id = ?");
$stmt->execute([$user->id()]);

while ($row = $stmt->fetch()) {
    // getting more info about set
    $set_stmt = $db->prepare("SELECT id, name FROM emote_sets WHERE id = ?");
    $set_stmt->execute([$row["emote_set_id"]]);
    $set = $set_stmt->fetch();

    // getting info about emote set content
    $em_stmt = $db->prepare(
        "SELECT e.id, e.created_at, e.uploaded_by, 
        CASE 
            WHEN esc.code IS NOT NULL THEN esc.code 
            ELSE e.code
        END AS code,
        CASE 
            WHEN esc.code IS NOT NULL THEN e.code 
            ELSE NULL 
        END AS original_code
        FROM emotes e
        INNER JOIN emote_set_contents AS esc
        ON esc.emote_set_id = ?
        WHERE esc.emote_id = e.id
        " . ($row["is_default"] ? '' : ' LIMIT 5')
    );
    $em_stmt->execute([$row["emote_set_id"]]);

    $emote_set_emotes = $em_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($emote_set_emotes as &$e) {
        $e["ext"] = "webp";
        if ($e["uploaded_by"]) {
            $uploaded_by_stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $uploaded_by_stmt->execute([$e["uploaded_by"]]);
            $e["uploaded_by"] = $uploaded_by_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    $emote_set = [
        "id" => $set["id"],
        "name" => $set["name"],
        "emotes" => $emote_set_emotes
    ];

    if ($row["is_default"]) {
        $active_emote_set = count($emote_sets);
    }

    array_push($emote_sets, $emote_set);
}

$active_emote_set = &$emote_sets[$active_emote_set];

// gathering uploaded emotes
$stmt = $db->prepare("SELECT e.*,
    CASE WHEN EXISTS (
        SELECT 1
        FROM emote_set_contents ec
        INNER JOIN emote_sets es ON es.id = ec.emote_set_id
        WHERE ec.emote_id = e.id AND es.owner_id = ?
    ) THEN 1 ELSE 0 END AS is_in_user_set
    FROM emotes e
    WHERE e.uploaded_by = ?
    ORDER BY e.created_at ASC
    ");
$stmt->execute([$user->id(), $user->id()]);

$uploaded_emotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// gathering actions
$stmt = $db->prepare("SELECT a.* FROM actions a WHERE a.user_id = ? ORDER BY a.created_at DESC LIMIT 15");
$stmt->execute([$user->id()]);

$actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TODO: add functionality

// calculating contributions
$stmt = $db->prepare("SELECT COUNT(*) FROM emotes WHERE uploaded_by = ?");
$stmt->execute([$user->id()]);
$contributions = intval($stmt->fetch()[0]);

$stmt = $db->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = ?");
$stmt->execute([$user->id()]);

$contributions += intval($stmt->fetch()[0]);

// getting status
$status = "... i don't know who am i";

$stmt = $db->prepare("SELECT * FROM roles r INNER JOIN role_assigns ra ON ra.user_id = ? WHERE ra.role_id = r.id");
$stmt->execute([$user->id()]);

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = '<span class="badge" style="color: rgba('
        . $row["foreground_color"] . ');';

    $bg_color_parts = explode(":", $row["background_color"]);

    switch ($bg_color_parts[0]) {
        case "solid": {
            $status .= "background: rgba($bg_color_parts[1]);";
            break;
        }
        case "gradient": {
            $status .= "background: linear-gradient(0deg, rgba($bg_color_parts[1]), rgba($bg_color_parts[2]));";
            break;
        }
        case "img": {
            $status .= "background-image: url('$bg_color_parts[1]');";
            break;
        }
        default:
            break;
    }

    $status .= '">';
    $status .= '<img src="/static/img/icons/badges/' . $row["badge_id"] . '.webp" alt="">';
    $status .= $row["name"];
    $status .= '</span>';
}

// getting reactions
$stmt = $db->prepare("SELECT rate, COUNT(*) AS c FROM ratings WHERE user_id = ? GROUP BY rate ORDER BY c DESC");
$stmt->execute([$user->id()]);

$fav_reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// getting favorite emote
$fav_emote = 1;

if ($is_json) {
    header("Content-type: application/json");
    echo json_encode([
        "status_code" => 200,
        "message" => null,
        "data" => [
            "id" => $user->id(),
            "username" => $user->username(),
            "joined_at" => $user->joined_at(),
            "last_active_at" => $user->last_active_at(),
            "stats" => [
                "status_id" => $status,
                "contributions" => $contributions,
                "favorite_reaction_id" => $fav_reaction,
                "favorite_emote_id" => $fav_emote
            ],
            "active_emote_set_id" => $active_emote_set["id"],
            "emote_sets" => $emote_sets,
            "uploaded_emotes" => $uploaded_emotes,
            "actions" => $actions
        ]
    ]);
    exit;
}
?>

<html>

<head>
    <title><?php echo sprintf("%s - %s", $user->username(), INSTANCE_NAME) ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="wrapper">
            <?php echo html_navigation_bar() ?>
            <section class="content row">
                <!-- User information -->
                <section class="user-bar column small-gap">
                    <section class="box">
                        <div class="box navtab">
                            <p>User</p>
                        </div>
                        <?php
                        echo '<div class="box content background"';

                        if (is_file("static/userdata/banners/" . $user->id())) {
                            echo ' style="background-image: url(\'/static/userdata/banners/' . $user->id() . '\');">';
                        } else {
                            echo '>';
                        }

                        echo '<img src="/static/';
                        if (is_file("static/userdata/avatars/" . $user->id())) {
                            echo 'userdata/avatars/' . $user->id();
                        } else {
                            echo 'img/defaults/profile_picture.png';
                        }
                        echo '" width="96" height="96">';
                        echo '<h1>' . $user->username() . '</h1>';

                        echo '</div>';
                        ?>
                    </section>

                    <!-- STATS -->
                    <section class="box">
                        <table class="vertical left">
                            <tr>
                                <th><img src="/static/img/icons/user.png"> I am </th>
                                <td><?php echo $status ?></td>
                            </tr>
                            <tr>
                                <th><img src="/static/img/icons/door_in.png"> Joined</th>
                                <?php
                                echo '<td title="';
                                echo date("M d, Y H:i:s", $user->joined_at());
                                echo ' UTC">about ' . format_timestamp(time() - $user->joined_at()) . " ago</td>";
                                ?>
                            </tr>
                            <tr>
                                <th><img src="/static/img/icons/clock.png"> Last activity</th>
                                <?php
                                $diff = time() - $user->last_active_at();
                                if ($diff > 60) {
                                    echo '<td title="';
                                    echo date("M d, Y H:i:s", $user->last_active_at());
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

                    <!-- ACTIONS -->
                    <section class="box column">
                        <a href="/message/send.php?user=<?php echo $user->id() ?>">Send a message</a>
                        <?php
                        if (isset($_SESSION["user_role"]) && $_SESSION["user_role"]["permission_report"]) {
                            echo '<a href="/report?user_id=' . $user->id() . '">Report user</a>';
                        }
                        ?>
                    </section>
                </section>

                <!-- Emotes -->
                <section class="column small-gap flex">
                    <!-- Emoteset -->
                    <section class="box">
                        <div class="box navtab">
                            <p>Emotes</p>
                        </div>
                        <div class="box content items">
                            <?php
                            if (!empty($emote_sets)) {
                                foreach ($emote_sets as $set_row) { ?>
                                    <a href="/emotesets.php?id=<?php echo $set_row["id"] ?>" class="box">
                                        <div>
                                            <?php
                                            echo '<p>' . $set_row["name"] . '</p>';
                                            ?>
                                        </div>

                                        <div>
                                            <?php
                                            for ($i = 0; $i < clamp(count($set_row["emotes"]), 0, 5); $i++) {
                                                $e = &$set_row["emotes"][$i];
                                                echo '<img src="/static/userdata/emotes/' . $e["id"] . '/1x.webp">';
                                            }
                                            ?>
                                        </div>
                                    </a>
                                <?php }
                            } else {
                                echo '<p>No emote sets found... ' . ((($_SESSION["user_id"] ?? "") == $id) ? 'Start adding emotes and you will have one! :)</p>' : '</p>');
                            }
                            ?>
                        </div>
                    </section>

                    <!-- Active emoteset -->
                    <?php
                    if ($active_emote_set != null) {
                        echo '' ?>
                        <section class="box">
                            <div class="content items">
                                <?php
                                if (!empty($active_emote_set["emotes"])) {
                                    foreach ($active_emote_set["emotes"] as $emote_row) {
                                        echo '<a class="box emote" href="/emotes?id=' . $emote_row["id"] . '">';
                                        echo '<img src="/static/userdata/emotes/' . $emote_row["id"] . '/2x.webp" alt="' . $emote_row["code"] . '"/>';
                                        echo '<h1>' . $emote_row["code"] . '</h1>';
                                        echo '<p>' . ($emote_row["uploaded_by"] == null ? (ANONYMOUS_DEFAULT_NAME . "*") : $emote_row["uploaded_by"]["username"]) . '</p>';
                                        echo '</a>';
                                        echo '</a>';
                                    }
                                } else {
                                    echo '<p>No emotes found... ' . ((($_SESSION["user_id"] ?? "") == $id) ? 'Start adding emotes and they will appear here! :)</p>' : '</p>');
                                }
                                ?>
                            </div>
                        </section><?php
                    }
                    ?>

                    <!-- Uploaded emotes -->
                    <?php
                    if (!empty($uploaded_emotes)) {
                        echo '' ?>
                        <section class="box">
                            <div class="box navtab">
                                <p>Uploaded emotes</p>
                            </div>
                            <div class="box content items">
                                <?php
                                foreach ($uploaded_emotes as $emote_row) {
                                    echo '<a class="box emote" href="/emotes?id=' . $emote_row["id"] . '">';
                                    echo '<img src="/static/userdata/emotes/' . $emote_row["id"] . '/2x.webp" alt="' . $emote_row["code"] . '"/>';
                                    echo '<h1>' . $emote_row["code"] . '</h1>';
                                    echo '</a>';
                                }
                                ?>
                            </div>
                        </section><?php
                    }
                    ?>

                    <!-- Actions -->
                    <section class="box">
                        <div class="box navtab">
                            <p>Actions</p>
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
                                echo '<i>' . $user->username() . '</i> ';

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
                </section>
            </section>
        </div>
    </div>
</body>

</html>