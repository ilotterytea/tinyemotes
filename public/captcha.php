<?php
include_once "../src/config.php";
include_once "../src/alert.php";

session_start();

if (!HCAPTCHA_ENABLE) {
    $_SESSION["captcha_solved"] = true;
    header("Location: /");
    exit;
}

if (isset($_SESSION["captcha_solved"]) && $_SESSION["captcha_solved"]) {
    header("Location: /");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["h-captcha-response"])) {
    // sending a request to captcha api
    $request = curl_init("https://hcaptcha.com/siteverify");
    curl_setopt($request, CURLOPT_POST, 1);
    curl_setopt($request, CURLOPT_HTTPHEADER, [sprintf("User-Agent: %s/1.0", INSTANCE_NAME)]);
    curl_setopt(
        $request,
        CURLOPT_POSTFIELDS,
        http_build_query(array("secret" => HCAPTCHA_SECRETKEY, "response" => $_POST["h-captcha-response"]))
    );
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($request);
    curl_close($request);

    $json = json_decode($response);

    if ($json->success) {
        $_SESSION["captcha_solved"] = true;
        header("Location: /");
        exit;
    }
}
?>

<html>

<head>
    <title>Resolving a hCaptcha - <?php echo INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <script src='https://www.hCaptcha.com/1/api.js' async defer></script>
</head>

<body>
    <noscript>JavaScript is required to solve hCaptcha</noscript>
    <div class="container">
        <div class="wrapper">
            <section class="row" style="padding: 4px; justify-content: center;">
                <section class="box">
                    <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITEKEY ?>"></div>
                </section>
            </section>
        </div>
    </div>
</body>

</html>