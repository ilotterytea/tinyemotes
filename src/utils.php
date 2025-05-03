<?php
function json_response(mixed $response, int $status = 200)
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($response);
}

function generate_random_string(int $length): string
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    $output = "";

    for ($i = 0; $i < $length; $i++) {
        $charindex = random_int(0, strlen($chars) - 1);
        $output .= $chars[$charindex];
    }

    return $output;
}

function str_safe(string $s, int|null $max_length, bool $remove_new_lines = true): string
{
    $output = $s;

    if ($remove_new_lines) {
        $output = str_replace(PHP_EOL, "", $output);
    }

    $output = htmlspecialchars($output);
    $output = strip_tags($output);

    if ($max_length) {
        $output = substr($output, 0, $max_length);
    }

    $output = trim($output);

    return $output;
}

function format_timestamp(int $timestamp_secs)
{
    $days = (int) floor($timestamp_secs / (60.0 * 60.0 * 24.0));
    $hours = (int) floor(round($timestamp_secs / (60 * 60)) % 24);
    $minutes = (int) floor(round($timestamp_secs % (60 * 60)) / 60);
    $seconds = (int) floor($timestamp_secs % 60);

    if ($days == 0 && $hours == 0 && $minutes == 0) {
        return "$seconds second" . ($seconds > 1 ? "s" : "");
    } else if ($days == 0 && $hours == 0) {
        return "$minutes minute" . ($minutes > 1 ? "s" : "");
    } else if ($days == 0) {
        return "$hours hour" . ($hours > 1 ? "s" : "");
    } else {
        return "$days day" . ($days > 1 ? "s" : "");
    }
}

function clamp(int $current, int $min, int $max): int
{
    return max($min, min($max, $current));
}

function in_range(float $value, float $min, float $max): bool
{
    return $min <= $value && $value <= $max;
}