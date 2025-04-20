<?php
include "../../src/accounts.php";
authorize_user();

function abort_upload(string $path, SQLite3 $db, string $id, string $response_text, int $response_code = 400)
{
    $stmt = $db->prepare("DELETE FROM emotes WHERE id = :id");
    $stmt->bindValue(":id", $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();

    array_map("unlink", glob("$path/*.*"));
    rmdir($path);
    http_response_code($response_code);
    exit($response_text);
}

include "../../src/utils.php";
include "../../src/images.php";

// TODO: make it configurable later
$max_width = max(128, 1);
$max_height = max(128, 1);

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    echo_upload_page();
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

$code = str_safe($_POST["code"] ?? "", 500);

if ($code == "") {
    http_response_code(400);
    echo json_encode([
        "status_code" => 400,
        "message" => "Invalid code",
        "data" => null
    ]);
    exit;
}

$image = $_FILES["file"];

if (is_null(list($mime, $ext) = get_mime_and_ext($image["tmp_name"]))) {
    http_response_code(400);
    echo json_encode([
        "status_code" => 400,
        "message" => "Not a valid image",
        "data" => null
    ]);
    exit;
}

// creating a new emote record
$db = new SQLite3("../../database.db");

$uploaded_by = $_SESSION["user_id"] ?? null;

$stmt = $db->prepare("INSERT INTO emotes(code, mime, ext, uploaded_by) VALUES (:code, :mime, :ext, :uploaded_by)");
$stmt->bindValue(":code", $code);
$stmt->bindValue(":mime", $mime);
$stmt->bindValue(":ext", $ext);
$stmt->bindValue(":uploaded_by", $uploaded_by);
$results = $stmt->execute();

$id = $db->lastInsertRowID();

if ($id == 0) {
    $db->close();
    http_response_code(500);
    echo json_encode([
        "status_code" => 500,
        "message" => "Failed to create an emote record",
        "data" => null
    ]);
    exit;
}

$path = "../static/userdata/emotes/$id";

if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

// resizing the image

// 3x image
$resized_image = resize_image($image["tmp_name"], "$path/3x", $max_width, $max_height);
if ($resized_image) {
    abort_upload($path, $db, $id, $resized_image);
}

// 2x image
$resized_image = resize_image($image["tmp_name"], "$path/2x", $max_width / 2, $max_height / 2);
if ($resized_image) {
    abort_upload($path, $db, $id, $resized_image);
}

// 1x image
$resized_image = resize_image($image["tmp_name"], "$path/1x", $max_width / 4, $max_height / 4);
if ($resized_image) {
    abort_upload($path, $db, $id, $resized_image);
}

$db->close();

if (isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json") {
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

header("Location: /emotes/$id", true, 307);

function echo_upload_page()
{
    include "../../src/partials.php";

    echo '' ?>
    <html>

    <head>
        <title>Upload an emote at alright.party</title>
        <link rel="stylesheet" href="/static/style.css">
    </head>

    <body>
        <div class="container">
            <div class="wrapper">
                <?php html_navigation_bar() ?>

                <section class="content" style="width: 50%;">
                    <section class="box">
                        <div class="box navtab">
                            <div>
                                <b>Upload a new emote</b>
                                <p style="font-size:8px;">Btw, you can upload anything. Anything you want.</p>
                            </div>
                        </div>
                        <div class="box content">
                            <form action="/emotes/upload.php" method="POST" enctype="multipart/form-data">
                                <h3>Emote name</h3>
                                <input type="text" name="code" id="code" required>
                                <h3>Image </h3>
                                <input type="file" name="file" id="file" accept=".gif,.jpg,.jpeg,.png,.webp" required>

                                <div>
                                    <label for="visibility">Emote visibility: </label>
                                    <select name="visibility">
                                        <option value="0">Public</option>
                                        <option value="1">Unlisted</option>
                                        <option value="0">Private</option>
                                    </select><br>
                                    <label for="visibility">Do you accept <a href="/rules">the rules</a>?</label>
                                    <input type="checkbox" name="tos" required>
                                </div>


                                <button type="submit" id="upload-button">Upload as
                                    <?php echo $_SESSION["user_name"] ?? "anonymous" ?></button>
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
                    let max_width = 128;
                    let max_height = 128;
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
            const regex = /^[a-zA-Z0-9]*$/;

            if (regex.test(e.target.value) && e.target.value.length <= 100) {
                validCode = e.target.value;
            } else {
                e.target.value = validCode;
            }
        });
    </script>

    </html>

    <?php
}