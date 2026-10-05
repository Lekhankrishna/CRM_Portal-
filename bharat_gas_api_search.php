<?php
// Server-side handler for "Bharat Gas Advanced" - same shape as
// aadhaar_family_api_search.php, calling includes/nexora_client.php's
// nexoraBharatGasSearch() in-process (the paid Nexora API).
require __DIR__ . '/includes/auth.php';
requireBharatGasApiAccess();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/nexora_client.php';
require_once __DIR__ . '/includes/search_cache.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$mobile = preg_replace('/\D+/', '', (string) ($data['mobile'] ?? ''));
if (strlen($mobile) !== 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Enter a valid 10-digit mobile number.']);
    exit;
}

nexoraEnforceMonthlyLimit($pdo, 'bharat_gas_api', 'Bharat Gas Advanced');

$cacheKey = searchCacheKey('bharat_gas_api', $mobile);
$cached = searchCacheGet($pdo, 'search_cache_bharat_gas_api', $cacheKey);
if ($cached !== null) {
    searchLogSavedResult($pdo, 'bharat_gas_api', $mobile, !empty($cached['found']) ? 1 : 0);
    echo json_encode($cached + ['usage' => nexoraUsage($pdo, 'bharat_gas_api')]);
    exit;
}

try {
    $result = nexoraBharatGasSearch($mobile);
} catch (Throwable $e) {
    error_log('bharat_gas_api_search.php: ' . $e->getMessage());
    nexoraLogSearch($pdo, 'bharat_gas_api', $mobile, false);
    http_response_code(502);
    echo json_encode(['error' => nexoraAgentError($e)]);
    exit;
}

nexoraLogSearch($pdo, 'bharat_gas_api', $mobile, $result['found']);

if ($result['found']) {
    searchCacheStore($pdo, 'search_cache_bharat_gas_api', $cacheKey, $mobile, $result, currentUser()['username'] ?? 'unknown');
}

echo json_encode($result + ['usage' => nexoraUsage($pdo, 'bharat_gas_api')]);
