<?php
function recordLoginAttempt(): void {
    $directory = sys_get_temp_dir() . '/minisocial-auth-' . hash('sha256', __DIR__);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        failRequest(503, 'Login is temporarily unavailable.');
    }
    $path = $directory . '/' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local') . '.json';
    $file = @fopen($path, 'c+');
    if (!$file || !flock($file, LOCK_EX)) {
        if ($file) { fclose($file); }
        failRequest(503, 'Login is temporarily unavailable.');
    }
    $now = time();
    $attempts = json_decode(stream_get_contents($file), true);
    $attempts = array_values(array_filter(is_array($attempts) ? $attempts : [],
        static fn ($at) => is_int($at) && $at > $now - 900));
    if (count($attempts) >= 20) {
        $retry = max(1, $attempts[0] + 900 - $now);
        flock($file, LOCK_UN);
        fclose($file);
        header('Retry-After: ' . $retry);
        failRequest(429, 'Too many login attempts. Please try again later.');
    }
    $attempts[] = $now;
    rewind($file);
    ftruncate($file, 0);
    fwrite($file, json_encode($attempts));
    fflush($file);
    flock($file, LOCK_UN);
    fclose($file);
}
