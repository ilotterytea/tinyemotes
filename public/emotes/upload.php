<?php
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

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    echo 'imagine there is a page';
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

$stmt = $db->prepare("INSERT INTO emotes(code, mime, ext) VALUES (:code, :mime, :ext)");
$stmt->bindValue(":code", $code);
$stmt->bindValue(":mime", $mime);
$stmt->bindValue(":ext", $ext);
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

// TODO: make it configurable later
$max_width = max(128, 1);
$max_height = max(128, 1);

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
            "mime" => $mime
        ]
    ]);
    exit;
}

header("Location: /emotes/$id", true, 307);