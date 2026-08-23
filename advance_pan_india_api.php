<?php
// Server-side handler for "Advance Pan India" (theeagleeye.biz's Advanced
// Search) - same monthly-limit/logging shape as rc_print_api.php/
// hp_gas_api.php, but calls includes/eagleeye_client.php directly in-process
// rather than proxying to a separate Flask/Selenium service, since
// theeagleeye.biz needs nothing more than plain HTTP+cookies (see that
// file's own comment on why).
require __DIR__ . '/includes/auth.php';
requireEagleEyeAccess();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/eagleeye_client.php';
require_once __DIR__ . '/includes/eagleeye_archive.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$params = [
    'name'      => trim($data['name'] ?? ''),
    'fname'     => trim($data['fname'] ?? ''),
    'mobile'    => trim($data['mobile'] ?? ''),
    'email'     => trim($data['email'] ?? ''),
    'address'   => trim($data['address'] ?? ''),
    'master_id' => trim($data['master_id'] ?? ''),
];

if (!array_filter($params, fn($v) => $v !== '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Enter at least one search field.']);
    exit;
}

// No monthly cap (removed 2026-08-12, per explicit instruction) - every
// agent with access gets unlimited Advance Pan India searches. Still logged
// to search_logs below for Admin > Audit Log either way.
try {
    $result = eagleEyeSearch($params);
} catch (Throwable $e) {
    // The real message (credentials, session-limit parsing, curl errors,
    // etc.) can name internal file paths - logged for diagnosis, but never
    // shown to the agent, who'd just see it as a confusing raw error rather
    // than something actionable.
    error_log('advance_pan_india_api.php: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'Server Down. Please try again later.']);
    exit;
}

// A completed search (found or not) still gets logged for Admin > Audit
// Log (no monthly limit to enforce anymore).
try {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $queryText = implode(' ', array_filter($params, fn($v) => $v !== ''));
    $pdo->prepare(
        "INSERT INTO search_logs (user_id, search_type, search_query, result_count, ip_address)
         VALUES (:uid, 'eagle_eye', :q, :cnt, :ip)"
    )->execute([
        'uid' => $_SESSION['user_id'],
        'q' => substr($queryText, 0, 512),
        'cnt' => $result['totalResults'],
        'ip' => substr($ip, 0, 45),
    ]);

    archiveEagleEyeResults($result['tables'] ?? [], currentUser()['username'] ?? 'unknown', $queryText);
} catch (PDOException $e) {}

echo json_encode($result);
