<?php
// Server-side handler for "Aadhaar to Family Advanced" - same shape as
// all_gas_api.php, calling includes/nexora_client.php's
// nexoraAadhaarFamilySearch() in-process (the paid Nexora API).
require __DIR__ . '/includes/auth.php';
requireAadhaarFamilyApiAccess();
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
// The Aadhaar number (kept in $mobile so the rest matches the other API
// endpoints).
$mobile = preg_replace('/\D+/', '', (string) ($data['aadhaar'] ?? ''));
if (strlen($mobile) !== 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Enter a valid 12-digit Aadhaar number.']);
    exit;
}

// Monthly limit - checked before the cache (a saved result still counts as
// a result handed out, per NEXORA_COUNT_EVERY_SEARCH).
nexoraEnforceMonthlyLimit($pdo, 'aadhaar_family_api', 'Aadhaar to Family Advanced');

// Read-through cache - a repeat of the same number is served from our own
// database instead of paying for another API call.
$cacheKey = searchCacheKey('aadhaar_family_api', $mobile);
$cached = searchCacheGet($pdo, 'search_cache_aadhaar_family_api', $cacheKey);
if ($cached !== null) {
    searchLogSavedResult($pdo, 'aadhaar_family_api', $mobile, !empty($cached['found']) ? 1 : 0);
    echo json_encode($cached + ['usage' => nexoraUsage($pdo, 'aadhaar_family_api')]);
    exit;
}

try {
    $result = nexoraAadhaarFamilySearch($mobile);
} catch (Throwable $e) {
    // The real message can name internal details - logged, never shown.
    error_log('aadhaar_family_api_search.php: ' . $e->getMessage());
    // Counts as a used search too (NEXORA_COUNT_EVERY_SEARCH).
    nexoraLogSearch($pdo, 'aadhaar_family_api', $mobile, false);
    http_response_code(502);
    echo json_encode(['error' => nexoraAgentError($e)]);
    exit;
}

nexoraLogSearch($pdo, 'aadhaar_family_api', $mobile, $result['found']);

// Only a real hit is cached - a miss may be temporary and must not hide a
// later real result.
if ($result['found']) {
    searchCacheStore($pdo, 'search_cache_aadhaar_family_api', $cacheKey, $mobile, $result, currentUser()['username'] ?? 'unknown');
}

echo json_encode($result + ['usage' => nexoraUsage($pdo, 'aadhaar_family_api')]);
