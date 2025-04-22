<?php
function generate_alert(string $path, string $error, int $status = 400)
{
    http_response_code($status);

    if (isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json") {
        echo json_encode([
            "status_code" => $status,
            "message" => $error,
            "data" => null
        ]);
    } else {
        header("Location: $path" . (str_contains($path, "?") ? "&" : "?") . "error_status=$status&error_reason=$error");
    }
}

function display_alert()
{
    if (!isset($_GET["error_status"], $_GET["error_reason"])) {
        return;
    }

    $status = $_GET["error_status"];
    $reason = str_safe($_GET["error_reason"], 50);
    $ok = substr($status, 0, 1) == '2';

    echo '' ?>
    <div class="box row alert <?php echo $ok ? '' : 'red' ?>" style="gap:8px;" id="alert-box">
        <img src="/static/img/icons/<?php echo $ok ? 'yes' : 'no' ?>.png" alt="">
        <p><b><?php echo $reason ?></b></p>
    </div>
    <script>
        setTimeout(() => {
            const alertBox = document.getElementById("alert-box");
            alertBox.remove();
        }, 5000);
    </script>
    <?php
}