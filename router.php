<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// rotas da API
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

// uploads
if (str_starts_with($path, '/storage/')) {
    $file = __DIR__ . '/' . $path;

    if (is_file($file)) {
        header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
        readfile($file);
        exit;
    }
}

// arquivos públicos
$file = __DIR__ . '/public' . ($path === '/' ? '/index.html' : $path);

if (is_file($file)) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
    ];

    if (isset($types[$extension])) {
        header('Content-Type: ' . $types[$extension]);
    }

    readfile($file);
    exit;
}

http_response_code(404);
echo 'Not found';
