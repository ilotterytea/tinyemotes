<?php
include "../../src/accounts.php";
include_once "../../src/config.php";
include_once "../../src/alert.php";

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

                <section class="content" style="width: 50%;">
                    <?php display_alert() ?>
                    <section class="box">
                        <div class="box navtab">
                            <div>
                                <b>Upload a new emote</b>
                                <p style="font-size:8px;">You can just upload, btw. Anything you want.</p>
                            </div>
                        </div>
                        <div class="box content">
                            <form action="/emotes/upload.php" method="POST" enctype="multipart/form-data">
                                <h3>Emote name</h3>
                                <input type="text" name="code" id="code" required>
                                <h3>Image</h3>
                                <input type="file" name="file" id="file" accept=".gif,.jpg,.jpeg,.png,.webp" required>

                                <div>
                                    <label for="visibility">Emote visibility: </label>
                                    <select name="visibility" id="form-visibility">
                                        <option value="1">Public</option>
                                        <option value="0">Unlisted</option>
                                    </select><br>
                                    <p id="form-visibility-description" style="font-size: 10px;">test</p>
                                    <label for="tos">Do you accept <a href="/rules">the rules</a>?</label>
                                    <input type="checkbox" name="tos" required>
                                </div>

                                <button type="submit" id="upload-button">Upload as
                                    <?php echo $uploader_name ?></button>
                            </form>
                        </div>
                    </section>

                    <section class="box" id="emote-showcase" style="display: none;">
                        <div class="emote-showcase">
                            <div class="emote-image">
                                <img src="" alt="" id="emote-image-1x">
                                <p>1x</p>
                                <p class="size"></p>
                            </div>
                            <div class="emote-image">
                                <img src="" alt="" id="emote-image-2x">
                                <p>2x</p>
                                <p class="size"></p>
                            </div>
                            <div class="emote-image">
                                <img src="" alt="" id="emote-image-3x">
                                <p>3x</p>
                                <p class="size"></p>
                            </div>
                        </div>
                    </section>
                </section>
            </div>
        </div>
    </body>

    <script>
        const fileInput = document.getElementById("file");
        const showcase = document.getElementById("emote-showcase");
        const reader = new FileReader();
        let isImage = false;
        fileInput.addEventListener("change", (e) => {
            isImage = false;
            showcase.style.display = "flex";
            reader.readAsDataURL(e.target.files[0]);
            reader.onload = (e) => {
                const image = new Image();
                image.src = e.target.result;
                image.onload = () => {
                    let m = 1;
                    let max_width = <?php echo EMOTE_MAX_SIZE[0] ?>;
                    let max_height = <?php echo EMOTE_MAX_SIZE[1] ?>;
                    isImage = true;

                    for (let i = 3; i > 0; i--) {
                        let max_w = max_width / m;
                        let max_h = max_height / m;

                        const parentId = `emote-image-${i}x`;
                        const img = document.getElementById(parentId);
                        img.setAttribute("src", e.target.result);

                        let ratio = Math.min(max_w / image.width, max_h / image.height);

                        img.setAttribute("width", Math.floor(image.width * ratio));
                        img.setAttribute("height", Math.floor(image.height * ratio));

                        const sizeElement = document.querySelector(`.emote-image:has(#${parentId}) .size`);
                        sizeElement.innerHTML = `${img.getAttribute("width")}x${img.getAttribute("height")}`;

                        m *= 2;
                    }
                };
            };
        });

        const code = document.getElementById("code");
        let validCode = "";

        code.addEventListener("input", (e) => {
            const regex = <?php echo EMOTE_NAME_REGEX ?>;

            if (regex.test(e.target.value) && e.target.value.length <= <?php echo EMOTE_NAME_MAX_LENGTH ?>) {
                validCode = e.target.value;
            } else {
                e.target.value = validCode;
            }
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
    </script>

    </html>

    <?php
    exit;
}

if (!isset($_FILES["file"])) {
    http_response_code(400);
    echo json_encode([
        "status_code" => 400,
        "message" => "No file set",
        "data" => null
    ]);
    exit;
}

$code = str_safe($_POST["code"] ?? "", EMOTE_NAME_MAX_LENGTH);

if ($code == "" || !preg_match(EMOTE_NAME_REGEX, $code)) {
    http_response_code(400);
    echo json_encode([
        "status_code" => 400,
        "message" => "Invalid code",
        "data" => null
    ]);
    exit;
}

$image = $_FILES["file"];

$notes = str_safe($_POST["notes"] ?? "", EMOTE_COMMENT_MAX_LENGTH);
if (empty($notes)) {
    $notes = null;
}

$visibility = clamp(intval($_POST["visibility"], EMOTE_VISIBILITY_DEFAULT), 0, 2);

if (MOD_EMOTES_APPROVE && $visibility == 1 && EMOTE_VISIBILITY_DEFAULT != 1) {
    $visibility = 2;
}

// creating a new emote record
$db = new PDO(DB_URL, DB_USER, DB_PASS);

$id = bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO emotes(id, code, notes, uploaded_by, visibility) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$id, $code, $notes, $uploaded_by, $visibility]);

$path = "../static/userdata/emotes/$id";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

// resizing the image
if ($err = resize_image($image["tmp_name"], "$path/3x", $max_width, $max_height)) {
    error_log("Error processing image: $err");
    generate_alert("/emotes/upload.php", "Error occurred while processing the image ($err)", 500);
    abort_upload($path, $db, $id);
    exit;
}
if ($err = resize_image($image["tmp_name"], "$path/2x", $max_width / 2, $max_height / 2)) {
    error_log("Error processing image: $err");
    generate_alert("/emotes/upload.php", "Error occurred while processing the image ($err)", 500);
    abort_upload($path, $db, $id);
    exit;
}
if ($err = resize_image($image["tmp_name"], "$path/1x", $max_width / 4, $max_height / 4)) {
    error_log("Error processing image: $err");
    generate_alert("/emotes/upload.php", "Error occurred while processing the image ($err)", 500);
    abort_upload($path, $db, $id);
    exit;
}

$db = null;

if (CLIENT_REQUIRES_JSON) {
    http_response_code(201);
    echo json_encode([
        "status_code" => 201,
        "message" => null,
        "data" => [
            "id" => $id,
            "code" => $code,
            "ext" => $ext,
            "mime" => $mime,
            "uploaded_by" => $uploaded_by
        ]
    ]);
    exit;
}

header("Location: /emotes?id=$id", true, 307);