<?php
/**
 * Proxies /timetable/api/* → Node Express on port 9000.
 * Keeps PHP apps on the same XAMPP host untouched.
 *
 * Optional: set TIMETABLE_API_ORIGIN in Apache/env, e.g. http://127.0.0.1:9000
 */
declare(strict_types=1);

$backendOrigin = getenv('TIMETABLE_API_ORIGIN') ?: 'http://127.0.0.1:9000';
$backendOrigin = rtrim($backendOrigin, '/');

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$query = parse_url($requestUri, PHP_URL_QUERY);

// /timetable/api/v1/... → /api/v1/...
if (preg_match('#/api(/.*)?$#', $path, $m)) {
    $apiPath = isset($m[1]) && $m[1] !== '' ? '/api' . $m[1] : '/api';
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid API proxy path.']);
    exit;
}

$target = $backendOrigin . $apiPath;
if ($query) {
    $target .= '?' . $query;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$headers = [];

foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') !== 0) {
        continue;
    }
    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
    if (in_array(strtolower($name), ['host', 'connection', 'content-length'], true)) {
        continue;
    }
    $headers[] = $name . ': ' . $value;
}

if (!empty($_SERVER['CONTENT_TYPE'])) {
    $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}

$body = null;
if (!in_array($method, ['GET', 'HEAD'], true)) {
    $body = file_get_contents('php://input');
}

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 120,
]);

if ($body !== null && $body !== false && $body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Node API unreachable. Start backend on port 9000 (npm run start:dev).',
        'detail' => curl_error($ch),
    ]);
    curl_close($ch);
    exit;
}

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$rawHeaders = substr($response, 0, $headerSize);
$rawBody = substr($response, $headerSize);

http_response_code($status ?: 502);

foreach (explode("\r\n", $rawHeaders) as $line) {
    if ($line === '' || stripos($line, 'HTTP/') === 0) {
        continue;
    }
    $lower = strtolower($line);
    if (str_starts_with($lower, 'transfer-encoding:') ||
        str_starts_with($lower, 'connection:') ||
        str_starts_with($lower, 'content-length:')) {
        continue;
    }
    header($line, false);
}

echo $rawBody;
