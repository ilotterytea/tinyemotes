<?php
function resize_image(string $src_path, string $dst_path, int $max_width, int $max_height, bool $set_format = true, bool $stretch = false): int|null
{
    if ($src_path == "") {
        return -2;
    }

    $image = getimagesize($src_path);

    if ($image == false) {
        return -1;
    }

    $format = $set_format ? ".webp" : "";

    $width = $image[0];
    $height = $image[1];
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = $stretch ? $max_width : (int) ($width * $ratio);
    $new_height = $stretch ? $max_height : (int) ($height * $ratio);

    $input_path = escapeshellarg($src_path);
    $output_path = escapeshellarg("$dst_path$format");

    $result_code = null;

    exec(command: "magick convert $input_path -coalesce -resize {$new_width}x$new_height -layers optimize -loop 0 $output_path", result_code: $result_code);

    return $result_code;
}

function does_file_meet_requirements(string $path, int $max_width, int $max_height): array
{
    $file = getimagesize($path);
    if (!$file) {
        return [false, null];
    }

    return [$file[0] <= $max_width && $file[1] <= $max_height, image_type_to_extension(intval($file[2]), false)];
}