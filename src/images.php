<?php
function resize_image(string $src_path, string $dst_path, int $max_width, int $max_height, bool $set_format = true, bool $stretch = false): string|null
{
    if ($src_path == "" || !getimagesize($src_path)) {
        return json_encode([
            "status_code" => 400,
            "message" => "Not an image",
            "data" => null
        ]);
    }

    $imagick = new Imagick();

    $imagick->readImage($src_path);
    $format = "." . strtolower($imagick->getImageFormat());

    if (!$set_format) {
        $format = "";
    }

    if ($imagick->getNumberImages() > 1) {
        $imagick = $imagick->coalesceImages();

        foreach ($imagick as $frame) {
            $width = $frame->getImageWidth();
            $height = $frame->getImageHeight();
            $ratio = min($max_width / $width, $max_height / $height);
            $new_width = (int) ($width * $ratio);
            $new_height = (int) ($height * $ratio);

            if ($stretch) {
                $new_width = $max_width;
                $new_height = $max_height;
            }

            $frame->resizeImage($new_width, $new_height, Imagick::FILTER_TRIANGLE, 1);
            $frame->setImagePage($new_width, $new_height, 0, 0);
        }

        $imagick = $imagick->deconstructImages();
        $imagick->writeImages("$dst_path$format", true);
    } else {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int) ($width * $ratio);
        $new_height = (int) ($height * $ratio);

        if ($stretch) {
            $new_width = $max_width;
            $new_height = $max_height;
        }

        $imagick->resizeImage($new_width, $new_height, Imagick::FILTER_TRIANGLE, 1);
        $imagick->writeImage("$dst_path$format");
    }

    $imagick->clear();
    $imagick->destroy();

    return null;
}

function get_mime_and_ext(string $src_path): array|null
{
    if ($src_path == "") {
        return null;
    }

    $imagick = new Imagick();

    $imagick->readImage($src_path);
    $ext = strtolower($imagick->getImageFormat());
    $mime = $imagick->getImageMimeType();

    $imagick->clear();
    $imagick->destroy();

    return [$mime, $ext];
}