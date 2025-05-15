<?php
include "../../src/accounts.php";
include_once "../../src/config.php";
include_once "../../src/alert.php";
include_once "../../src/captcha.php";

if (!EMOTE_UPLOAD) {
    generate_alert("/404.php", "Emote upload is disabled", 403);
    exit;
}

authorize_user();

if (!ANONYMOUS_UPLOAD && isset($_SESSION["user_role"]) && !$_SESSION["user_role"]["permission_upload"]) {
    generate_alert("/404.php", "Not enough permissions", 403);
    exit;
}

$uploaded_by = null;
$uploader_name = ANONYMOUS_DEFAULT_NAME;

if (isset($_SESSION["user_role"]) && $_SESSION["user_role"]["permission_upload"]) {
    $uploaded_by = $_SESSION["user_id"] ?? null;
    $uploader_name = $_SESSION["user_name"] ?? ANONYMOUS_DEFAULT_NAME;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

function abort_upload(string $path, PDO $db, string $id)
{
    $stmt = $db->prepare("DELETE FROM emotes WHERE id = ?");
    $stmt->execute([$id]);
    $db = null;

    array_map("unlink", glob("$path/*.*"));
    rmdir($path);
}

include "../../src/utils.php";
include "../../src/images.php";

$max_width = EMOTE_MAX_SIZE[0];
$max_height = EMOTE_MAX_SIZE[1];

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    include "../../src/partials.php";

    echo '' ?>
    <html>

    <head>
        <title>Upload an emote - <?php echo INSTANCE_NAME ?></title>
        <link rel="stylesheet" href="/static/style.css">
        <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    </head>

    <body>
        <div class="container">
            <div class="wrapper">
                <?php html_navigation_bar() ?>
                <?php display_alert() ?>

                <section class="content row">
                    <div class="column small-gap">
                        <section class="box">
                            <div class="box navtab">
                                <div>
                                    <b>Upload a new emote</b>
                                    <p style="font-size:8px;">You can just upload, btw. Anything you want.</p>
                                </div>
                            </div>
                            <div class="box content">
                                <form action="/emotes/upload.php" method="POST" enctype="multipart/form-data">
                                    <h3>Image<span style="color:red;">*</span></h3>

                                    <input type="file" name="file" id="form-file" accept=".gif,.jpg,.jpeg,.png,.webp"
                                        required>

                                    <div id="form-manual-files" style="display:none;">
                                        <input type="file" name="file-1x" id="form-file-1x"
                                            accept=".gif,.jpg,.jpeg,.png,.webp">
                                        <label class="inline"
                                            for="file-1x"><?php echo sprintf("%dx%d", EMOTE_MAX_SIZE[0] / 4, EMOTE_MAX_SIZE[1] / 4) ?></label>
                                        <input type="file" name="file-2x" id="form-file-2x"
                                            accept=".gif,.jpg,.jpeg,.png,.webp">
                                        <label class="inline"
                                            for="file-2x"><?php echo sprintf("%dx%d", EMOTE_MAX_SIZE[0] / 2, EMOTE_MAX_SIZE[1] / 2) ?></label>
                                        <input type="file" name="file-3x" id="form-file-3x"
                                            accept=".gif,.jpg,.jpeg,.png,.webp">
                                        <label class="inline"
                                            for="file-3x"><?php echo sprintf("%dx%d", EMOTE_MAX_SIZE[0], EMOTE_MAX_SIZE[1]) ?></label>
                                    </div>

                                    <div>
                                        <label for="manual" class="inline">Manual resize</label>
                                        <input type="checkbox" name="manual" value="1" onchange="display_manual_resize()">
                                    </div>

                                    <h3>Emote name<span style="color:red;">*</span></h3>
                                    <input type="text" name="code" id="code" required>

                                    <div>
                                        <label for="visibility" class="inline">Emote visibility: </label>
                                        <select name="visibility" id="form-visibility">
                                            <option value="1">Public</option>
                                            <option value="0">Unlisted</option>
                                        </select><br>
                                        <p id="form-visibility-description" style="font-size: 10px;">test</p>
                                    </div>

                                    <label for="notes">Approval notes</label>
                                    <textarea name="notes" id="form-notes"></textarea>

                                    <table class="vertical left font-weight-normal">
                                        <tr>
                                            <th>Emote source:</th>
                                            <td class="flex"><input class="grow" name="source" id="form-source"></input>
                                            </td>
                                        </tr>
                                        <?php if (TAGS_ENABLE && TAGS_MAX_COUNT != 0): ?>
                                            <tr>
                                                <th>Tags <span class="font-small" style="cursor: help;" title="<?php
                                                echo 'Tags are used for fast search. ';
                                                if (TAGS_MAX_COUNT > 0) {
                                                    echo 'You can use ' . TAGS_MAX_COUNT . ' tags. ';
                                                }
                                                echo 'They are space-separated o algo.';
                                                ?>">[?]</span>:
                                                </th>
                                                <td class="flex"><input class="grow" name="tags" id="form-tags"></input></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>

                                    <div>
                                        <label for="tos" class="inline">Do you accept <a href="/rules.php"
                                                target="_BLANK">the
                                                rules</a>?<span style="color:red;">*</span></label>
                                        <input type="checkbox" name="tos" value="1" required>
                                    </div>

                                    <button type="submit" id="upload-button">Upload as
                                        <?php echo $uploader_name ?></button>
                                </form>
                            </div>
                        </section>

                        <?php
                        if (CAPTCHA_ENABLE && (CAPTCHA_FORCE_USERS || !isset($_SESSION["user_id"]))) {
                            html_captcha_form();
                        }
                        ?>
                    </div>

                    <div class="column small-gap grow" id="emote-showcase" style="display: none;">
                        <!-- Emote Preview -->
                        <section class="box">
                            <div class="box navtab">
                                Emote Preview - <span id="emote-name"><i>Empty</i></span>
                            </div>
                            <div class="box content">
                                <div class="emote-showcase items-bottom">
                                    <div class="emote-image column items-center small-gap">
                                        <img src="" alt="" class="emote-image-1x">
                                        <p class="size font-small"></p>
                                    </div>
                                    <div class="emote-image column items-center small-gap">
                                        <img src="" alt="" class="emote-image-2x">
                                        <p class="size font-small"></p>
                                    </div>
                                    <div class="emote-image column items-center small-gap">
                                        <img src="" alt="" class="emote-image-3x">
                                        <p class="size font-small"></p>
                                    </div>
                                </div>
                                <p style="font-size: 12px;">The result may differ.</p>
                            </div>
                        </section>

                        <!-- Chat Preview -->
                        <section class="box">
                            <div class="box navtab">
                                Chat Preview
                            </div>
                            <div class="box content no-gap column chat rounded">
                                <?php
                                $stmt = $db->query("SELECT u.username,
                                        CASE
                                            WHEN ub.badge_id IS NOT NULL THEN ub.badge_id
                                            WHEN r.badge_id IS NOT NULL THEN r.badge_id
                                            ELSE NULL
                                        END AS badge_id
                                    FROM users u
                                    LEFT JOIN user_badges ub ON ub.user_id = u.id
                                    LEFT JOIN role_assigns ra ON ra.user_id = u.id
                                    LEFT JOIN roles r ON r.id = ra.role_id
                                    ORDER BY RAND() LIMIT 3
                                ");

                                while ($row = $stmt->fetch()) {
                                    echo '<div class="row small-gap items-center chat-message">';

                                    if ($row["badge_id"]) {
                                        echo '<img src="/static/userdata/badges/' . $row["badge_id"] . '/1x.webp" alt="" title="" /> ';
                                    }

                                    echo '<span style="color: rgb(' . random_int(128, 255) . ', ' . random_int(128, 255) . ', ' . random_int(128, 255) . ')">';
                                    echo $row["username"];
                                    echo ': </span>';

                                    echo '<img src="" alt="" class="emote-image-1x">';

                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </div>
    </body>

    <script>
        const max_width = <?php echo EMOTE_MAX_SIZE[0] ?>;
        const max_height = <?php echo EMOTE_MAX_SIZE[1] ?>;

        const fileInput = document.getElementById("form-file");
        const showcase = document.getElementById("emote-showcase");
        const reader = new FileReader();

        let manual = false;

        fileInput.addEventListener("change", (e) => {
            if (manual) return;

            showcase.style.display = "flex";
            reader.readAsDataURL(e.target.files[0]);
            reader.onload = (e) => {
                const image = new Image();
                image.src = e.target.result;
                image.onload = () => {
                    let m = 1;

                    for (let i = 3; i > 0; i--) {
                        place_image(i, m, e, image);
                        m *= 2;
                    }
                };
            };
        });

        const code = document.getElementById("code");

        code.addEventListener("input", (e) => {
            const regex = <?php echo EMOTE_NAME_REGEX ?>;

            if (regex.test(e.target.value) && e.target.value.length <= <?php echo EMOTE_NAME_MAX_LENGTH ?>) {
                validCode = e.target.value;
            } else {
                e.target.value = validCode;
            }

            document.getElementById("emote-name").innerHTML = e.target.value ? e.target.value : "<i>Empty</i>";
        });

        const visibility = document.getElementById("form-visibility");
        visibility.addEventListener("change", (e) => {
            set_form_visibility_description(visibility.value);
        });

        function set_form_visibility_description(visibility) {
            const p = document.getElementById("form-visibility-description");

            if (visibility == 1) {
                p.innerHTML = "Emote won't appear on the public list until it passes a moderator's review. It still can be added to chats.";
            } else {
                p.innerHTML = "Emote doesn't appear on the public list and won't be subject to moderation checks. It still can be added to chats.";
            }
        }

        set_form_visibility_description(visibility.value);

        // Manual resize
        function display_manual_resize() {
            const manual_files = document.getElementById("form-manual-files");

            // resetting previous values
            const files = document.querySelectorAll("input[type=file]");

            for (let file of files) {
                file.value = null;
                file.removeAttribute("required");
            }

            const fileImages = document.querySelectorAll(".emote-image img");

            for (let file of fileImages) {
                file.setAttribute("src", "");
                file.setAttribute("width", "0");
                file.setAttribute("height", "0");
            }

            const fileSizes = document.querySelectorAll(".emote-image .size");

            for (let file of fileImages) {
                file.innerHTML = "";
            }

            manual = !manual;

            if (manual) {
                manual_files.style.display = "block";
                fileInput.style.display = "none";
                const elements = document.querySelectorAll("#form-manual-files input[type=file]");
                for (let elem of elements) {
                    elem.setAttribute("required", "true");
                }
            } else {
                manual_files.style.display = "none";
                fileInput.style.display = "block";
                fileInput.setAttribute("required", "true");
            }

            showcase.style.display = "none";
        }

        document.getElementById("form-file-1x").addEventListener("change", (e) => {
            showcase.style.display = "flex";
            place_image(1, 4, e, null);
        });

        document.getElementById("form-file-2x").addEventListener("change", (e) => {
            showcase.style.display = "flex";
            place_image(2, 2, e, null);
        });

        document.getElementById("form-file-3x").addEventListener("change", (e) => {
            showcase.style.display = "flex";
            place_image(3, 1, e, null);
        });

        function place_image(image_index, multiplier, e, image) {
            let ee = e;

            if (image == null) {
                reader.readAsDataURL(e.target.files[0]);
                reader.onload = (e) => {
                    const image = new Image();
                    image.src = e.target.result;
                    image.onload = () => {
                        insert_image(image_index, multiplier, e, image);
                    };
                }
            } else {
                insert_image(image_index, multiplier, e, image);
            }

            function insert_image(i, m, e, image) {
                const max_w = max_width / multiplier;
                const max_h = max_height / multiplier;

                const parentId = `.emote-image-${image_index}x`;
                const imgs = document.querySelectorAll(parentId);

                for (const img of imgs) {
                    img.setAttribute("src", e.target.result);

                    let ratio = Math.min(max_w / image.width, max_h / image.height);

                    img.setAttribute("width", Math.floor(image.width * ratio));
                    img.setAttribute("height", Math.floor(image.height * ratio));

                    const sizeElement = document.querySelector(`.emote-image:has(${parentId}) .size`);
                    sizeElement.innerHTML = `${img.getAttribute("width")}x${img.getAttribute("height")}`;
                }
            }
        }
    </script>

    </html>

    <?php
    exit;
}

if (!CLIENT_REQUIRES_JSON && CAPTCHA_ENABLE && !isset($_SESSION["captcha_solved"])) {
    generate_alert("/404.php", "You haven't solved captcha yet.", 403);
    exit;
}

$is_manual = intval($_POST["manual"] ?? "0") == 1;

if ($is_manual && !isset($_FILES["file-1x"], $_FILES["file-2x"], $_FILES["file-3x"])) {
    generate_alert("/emotes/upload.php", "No files set");
    exit;
}

if (!$is_manual && !isset($_FILES["file"])) {
    generate_alert("/emotes/upload.php", "No file set");
    exit;
}

$code = str_safe($_POST["code"] ?? "", EMOTE_NAME_MAX_LENGTH);

if ($code == "" || !preg_match(EMOTE_NAME_REGEX, $code)) {
    generate_alert("/emotes/upload.php", "Invalid code");
    exit;
}

$notes = str_safe($_POST["notes"] ?? "", EMOTE_COMMENT_MAX_LENGTH);
if (empty($notes)) {
    $notes = null;
}

$source = str_safe($_POST["source"] ?? "", null);
if (empty($source)) {
    $source = null;
}

$visibility = clamp(intval($_POST["visibility"], EMOTE_VISIBILITY_DEFAULT), 0, 2);

if (MOD_EMOTES_APPROVE && $visibility == 1 && EMOTE_VISIBILITY_DEFAULT != 1) {
    $visibility = 2;
}

// creating a new emote record
$id = bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO emotes(id, code, notes, source, uploaded_by, visibility) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$id, $code, $notes, $source, $uploaded_by, $visibility]);

$path = "../static/userdata/emotes/$id";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

if ($is_manual) {
    $image_1x = $_FILES["file-1x"];
    $image_2x = $_FILES["file-2x"];
    $image_3x = $_FILES["file-3x"];

    $file_1x = does_file_meet_requirements($image_1x["tmp_name"], $max_width / 4, $max_height / 4);
    $file_2x = does_file_meet_requirements($image_2x["tmp_name"], $max_width / 2, $max_height / 2);
    $file_3x = does_file_meet_requirements($image_3x["tmp_name"], $max_width, $max_height);

    if (!$file_1x[0] || !$file_2x[0] || !$file_3x[0]) {
        generate_alert("/emotes/upload.php", "Files don't meet requirements");
        abort_upload($path, $db, $id);
        exit;
    }

    if (
        !move_uploaded_file($image_1x["tmp_name"], "$path/1x.$file_1x[1]") ||
        !move_uploaded_file($image_2x["tmp_name"], "$path/2x.$file_2x[1]") ||
        !move_uploaded_file($image_3x["tmp_name"], "$path/3x.$file_3x[1]")
    ) {
        generate_alert("/emotes/upload.php", "Failed to move the uploaded files");
        abort_upload($path, $db, $id);
        exit;
    }
} else {
    $image = $_FILES["file"];
    // resizing the image
    if ($err = create_image_bundle($image["tmp_name"], $path, $max_width, $max_height)) {
        generate_alert("/emotes/upload.php", "Error occurred while processing images ($err)", 500);
        abort_upload($path, $db, $id);
        exit;
    }

    if (EMOTE_STORE_ORIGINAL) {
        $ext = get_file_extension($image["tmp_name"]) ?? "";
        move_uploaded_file($image["tmp_name"], "$path/original.$ext");
    }
}

$tags = str_safe($_POST["tags"] ?? "", null);
$tags_processed = [];

if (!empty($tags) && TAGS_ENABLE) {
    $tags = explode(" ", $tags);

    $count = 0;

    foreach ($tags as $tag) {
        if (TAGS_MAX_COUNT > 0 && $count >= TAGS_MAX_COUNT) {
            break;
        }

        if (!preg_match(TAGS_CODE_REGEX, $tag)) {
            continue;
        }

        $tag_id = null;

        $stmt = $db->prepare("SELECT id FROM tags WHERE code = ?");
        $stmt->execute([$tag]);

        if ($row = $stmt->fetch()) {
            $tag_id = $row["id"];
        } else {
            $tag_id = bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO tags(id, code) VALUES (?, ?)")->execute([$tag_id, $tag]);
        }

        $db->prepare("INSERT INTO tag_assigns(tag_id, emote_id) VALUES (?, ?)")->execute([$tag_id, $id]);

        $count++;
        array_push($tags_processed, $tag);
    }
}

$emote_data = [
    "id" => $id,
    "code" => $code,
    "visibility" => $visibility,
    "uploaded_by" => match ($uploaded_by == null) {
        true => null,
        false => [
            "id" => $uploaded_by,
            "username" => $uploader_name
        ]
    },
    "notes" => $notes,
    "source" => $source,
    "tags" => $tags_processed
];

if (ACCOUNT_LOG_ACTIONS && $uploaded_by != null) {
    $db->prepare("INSERT INTO actions(user_id, action_type, action_payload) VALUES (?, ?, ?)")
        ->execute([
            $uploaded_by,
            "EMOTE_CREATE",
            json_encode([
                "emote" => $emote_data
            ])
        ]);
}


$db = null;

if (CLIENT_REQUIRES_JSON) {
    json_response([
        "status_code" => 201,
        "message" => null,
        "data" => $emote_data
    ], 201);
    exit;
}

header("Location: /emotes?id=$id", true, 307);