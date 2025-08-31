<?php
// Simple signed HTTP client for WP-Migrate HMAC auth
// Usage:
//   php signed-request.php METHOD BASE_URL PATH [BODY_JSON]
// Env:
//   SHARED_KEY (required), PEER (required)

if ($argc < 4) {
    fwrite(STDERR, "Usage: php signed-request.php METHOD BASE_URL PATH [BODY_JSON]\n");
    exit(2);
}

$method = strtoupper($argv[1]);
$baseUrl = rtrim($argv[2], '/');
$path = $argv[3]; // should start with /wp-json
$body = $argv[4] ?? '';

$sharedKey = getenv('SHARED_KEY') ?: '';
$peer = getenv('PEER') ?: '';
if ($sharedKey === '' || $peer === '') {
    fwrite(STDERR, "Missing env: SHARED_KEY and PEER are required.\n");
    exit(3);
}

// Normalize path to include /wp-json and preserve query
if (strpos($path, '/wp-json') !== 0) {
    $path = '/wp-json' . $path;
}

$timestampMs = (int) round(microtime(true) * 1000);
$nonce = bin2hex(random_bytes(8));
$bodyHash = hash('sha256', $body);
$payload = $timestampMs . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
$signature = base64_encode(hash_hmac('sha256', $payload, $sharedKey, true));

$headers = [
    'Content-Type: application/json',
    'X-MIG-Timestamp: ' . $timestampMs,
    'X-MIG-Nonce: ' . $nonce,
    'X-MIG-Peer: ' . $peer,
    'X-MIG-Signature: ' . $signature,
];

$opts = [
    'http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'content' => $body,
        'timeout' => 30,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
];

$context = stream_context_create($opts);
$url = $baseUrl . $path;
$resp = @file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? 'HTTP/1.1 000';

echo json_encode([
    'request' => [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
        'payload' => $payload,
    ],
    'response' => [
        'status' => $statusLine,
        'headers' => $http_response_header ?? [],
        'body' => $resp,
    ],
], JSON_PRETTY_PRINT) . "\n";


