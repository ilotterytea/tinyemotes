<?php
function generate_image_captcha(int $width, int $height, int $difficulty, string $file_name, string $file_folder): string
{
    $image = imagecreatetruecolor($width, $height);

    $background = imagecolorallocate($image, 0xDD, 0xDD, 0xDD);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    $files = scandir($file_folder);
    array_splice($files, 0, 2);

    for ($i = 0; $i < 50 * $difficulty; $i++) {
        $unprocessed = imagecreatefrompng("$file_folder/" . $files[random_int(0, count($files) - 1)]);

        $oldw = imagesx($unprocessed);
        $oldh = imagesy($unprocessed);

        $w = random_int(round($oldw / 4), round($oldw / 2));
        $h = random_int(round($oldh / 4), round($oldh / 2));

        $file = imagecreatetruecolor($w, $h);
        imagealphablending($file, false);
        $transparent = imagecolorallocatealpha($file, 0, 0, 0, 127);
        imagefill($file, 0, 0, $transparent);
        imagesavealpha($file, true);

        imagecopyresampled($file, $unprocessed, 0, 0, 0, 0, $w, $h, $oldw, $oldh);

        $angle = random_int(0, 360);

        $file = imagerotate($file, $angle, $transparent);

        for ($j = 0; $j < random_int(2, 5 * $difficulty); $j++) {
            imagefilter($file, IMG_FILTER_GAUSSIAN_BLUR);
        }

        if (random_int(0, 15) % 3 == 0) {
            imagefilter($file, IMG_FILTER_NEGATE);
        }

        if (random_int(0, 20) % 4 == 0) {
            imagefilter($file, IMG_FILTER_PIXELATE, 4);
        }

        $w = imagesx($file);
        $h = imagesy($file);

        imagecopy(
            $image,
            $file,
            random_int(0, $width - $w),
            random_int(0, $height - $h),
            0,
            0,
            $w,
            $h
        );
    }

    $foreground = imagecreatefrompng("$file_folder/$file_name.png");
    $transparent = imagecolorallocatealpha($foreground, 0, 0, 0, 127);
    $angle = random_int(0, max: 180);
    $foreground = imagerotate($foreground, $angle, $transparent);
    $w = imagesx($foreground);
    $h = imagesy($foreground);
    imagecopy(
        $image,
        $foreground,
        random_int(0, $width - $w),
        random_int(0, $height - $h),
        0,
        0,
        $w,
        $h
    );

    ob_start();
    imagepng($image);
    $source = ob_get_contents();
    ob_clean();

    return "data:image/png;base64," . base64_encode($source);
}

function html_captcha_form()
{
    echo '' ?>
    <div class="box" id="form-captcha-wrapper" style="display: none;">
        <div class="box navtab">
            Solve captcha
        </div>
        <div class="box content">
            <noscript>JavaScript is required for captcha!</noscript>
            <form id="form-captcha">
                <img src="" alt="Generating captcha..." id="form-captcha-img" width="256">
                <div class="column small-gap">
                    <div class="row small-gap">
                        <input type="text" name="answer" placeholder="Enter emote name..." class="grow"
                            id="form-captcha-answer">
                        <button type="submit" class="green" id="form-captcha-solve">Solve</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        const formElement = document.getElementById("form-captcha");
        const formWrapper = document.getElementById("form-captcha-wrapper");

        function get_captcha() {
            fetch("/captcha.php")
                .then((response) => response.json())
                .then((json) => {
                    if (json.data == null) {
                        formWrapper.style.display = "none";
                        return;
                    }

                    document.getElementById("form-captcha-answer").value = null;

                    formWrapper.style.display = "flex";

                    document.getElementById("form-captcha-img").setAttribute("src", json.data);
                });
        }

        get_captcha();

        formElement.addEventListener("submit", (e) => {
            e.preventDefault();

            const answer = document.getElementById("form-captcha-answer");
            const body = new FormData(formElement);

            fetch("/captcha.php", {
                "method": "POST",
                "body": body
            })
                .then((response) => response.json())
                .then((json) => {
                    if (json.status_code == 200 && json.data == null) {
                        formWrapper.style.display = "none";
                        return;
                    }

                    get_captcha();
                });
        });
    </script>
    <?php ;
}