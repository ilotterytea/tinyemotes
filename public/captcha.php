<?php
include_once "../src/config.php";
include_once "../src/alert.php";
include_once "../src/captcha.php";
include_once "../src/utils.php";

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["answer"])) {
    if ($_POST["answer"] == ($_SESSION["captcha_word"] ?? "")) {
        $_SESSION["captcha_solved"] = true;
        echo json_response([
            "status_code" => 200,
            "message" => "Solved!",
            "data" => null
        ]);
    } else {
        echo json_response([
            "status_code" => 400,
            "message" => "Wrong answer!",
            "data" => null
        ], 400);
    }
    exit;
}

$file_folder = $_SERVER["DOCUMENT_ROOT"] . '/static/img/captcha';

if (!CAPTCHA_ENABLE || ($_SESSION["captcha_solved"] ?? false) || !is_dir($file_folder)) {
    $_SESSION["captcha_solved"] = true;
    echo json_response([
        "status_code" => 200,
        "message" => "No need to solve captcha",
        "data" => null
    ]);
    exit;
}

$files = scandir($file_folder);
array_splice($files, 0, 2);

$filename = $files[random_int(0, count($files) - 1)];
$filename = basename($filename, ".png");

$_SESSION["captcha_word"] = $filename;

$image = generate_image_captcha(
    CAPTCHA_SIZE[0],
    CAPTCHA_SIZE[1],
    random_int(1, 3),
    $filename,
    $file_folder
);

echo json_response([
    "status_code" => 200,
    "message" => null,
    "data" => $image
]);