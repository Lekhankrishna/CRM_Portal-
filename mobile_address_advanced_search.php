<?php
// Server-side handler for "Mobile to Address Advanced" - same shape as
// aadhaar_family_api_search.php, calling includes/nexora_client.php's
// nexoraMobileAdvancedSearch() in-process (the paid Nexora API).
require __DIR__ . '/includes/auth.php';
requireMobileAddressAdvAccess();
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

nexoraEnforceMonthlyLimit($pdo, 'mobile_address_adv', 'Mobile to Address Advanced');

$cacheKey = searchCacheKey('mobile_address_adv', $mobile);
$cached = searchCacheGet($pdo, 'search_cache_mobile_address_adv', $cacheKey);
if ($cached !== null) {
    searchLogSavedResult($pdo, 'mobile_address_adv', $mobile, !empty($cached['found']) ? 1 : 0);
    echo json_encode($cached + ['usage' => nexoraUsage($pdo, 'mobile_address_adv')]);
    exit;
}

try {
    $result = nexoraMobileAdvancedSearch($mobile);
} catch (Throwable $e) {
    error_log('mobile_address_advanced_search.php: ' . $e->getMessage());
    nexoraLogSearch($pdo, 'mobile_address_adv', $mobile, false);
    http_response_code(502);
    echo json_encode(['error' => nexoraAgentError($e)]);
    exit;
}

nexoraLogSearch($pdo, 'mobile_address_adv', $mobile, $result['found']);

if ($result['found']) {
    searchCacheStore($pdo, 'search_cache_mobile_address_adv', $cacheKey, $mobile, $result, currentUser()['username'] ?? 'unknown');
}

echo json_encode($result + ['usage' => nexoraUsage($pdo, 'mobile_address_adv')]);
