<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    exit('Invalid token');
}

$url = $exchange->processClick($token);
if (!$url) {
    http_response_code(404);
    exit('Not found');
}

header('Location: ' . $url);
