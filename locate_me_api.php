<?php
// Server-side proxy between the browser and the locateme.services tool
// automations, same shape as hp_gas_api.php/rc_print_api.php: runs
// centrally via Gas/lpg_web's Flask service (the generic /api/locate-tool
// route, backed by Gas/lpg_web/locate_tools.py), browser never talks to
// locateme.services or Flask directly, and the locateme.services login
// lives in Gas/lpg_web/rc_print.py, not in this app's database.
require __DIR__ . '/includes/auth.php';
requireLocateMeAccess();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/locateme_archive.php';
require_once __DIR__ . '/includes/locateme_tools.php';

header('Content-Type: application/json');

const FLASK_BASE = 'http://127.0.0.1:9197';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true) ?: [];
$tool  = (string) ($data['tool'] ?? '');
$query = trim((string) ($data['query'] ?? ''));

if (!isset(LOCATEME_TOOLS[$tool])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown Locate Me tool.']);
    exit;
}
if ($query === '' || mb_strlen($query) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Enter a value to search.']);
    exit;
}

// Every tool spends real credits on the single shared locateme.services
// account, so every agent is capped per calendar month across ALL Locate Me
// tools combined (Admin > Agents > "Locate Me Monthly Limit") - one shared
// budget line for the whole feature rather than a separate cap per tool,
// since per-tool credit costs already vary wildly (1 to 150). Admins bypass
// this entirely, same as every other metered tool in this app.
if (($_SESSION['role'] ?? '') !== 'admin') {
    $stmt = $pdo->prepare('SELECT locate_me_monthly_limit FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $limit = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = 'locate_me' AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $usedThisMonth = (int) $stmt->fetchColumn();

    if ($usedThisMonth >= $limit) {
        http_response_code(429);
        echo json_encode([
            'error' => "Monthly Locate Me limit reached ($usedThisMonth/$limit this month). Contact your admin to increase it, or try again next month.",
            'used' => $usedThisMonth,
            'limit' => $limit,
        ]);
        exit;
    }
}

$ch = curl_init(FLASK_BASE . '/api/locate-tool');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Generous timeout - a fresh locateme.services login plus their own search,
// same reasoning as hp_gas_api.php.
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['toolSlug' => $tool, 'query' => $query]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => "Could not reach the Locate Me service: $err"]);
    exit;
}

// Only a genuinely completed lookup counts against the monthly limit and
// shows up in Admin > Audit Log - a failed login, timeout, or unreachable
// service isn't the agent's fault. "found": false (a clean not-found result)
// still counts as a completed search - same reasoning as hp_gas_api.php
// (both spend locateme.services credits regardless of hit/miss).
$decoded = json_decode($response, true);
if ($httpCode === 200 && is_array($decoded) && array_key_exists('found', $decoded)) {
    try {
        $recordCount = $decoded['found'] ? count($decoded['records'] ?? []) : 0;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare(
            "INSERT INTO search_logs (user_id, search_type, search_query, result_count, ip_address)
             VALUES (:uid, 'locate_me', :q, :cnt, :ip)"
        )->execute([
            'uid' => $_SESSION['user_id'],
            'q' => LOCATEME_TOOLS[$tool]['label'] . ': ' . $query,
            'cnt' => $recordCount,
            'ip' => substr($ip, 0, 45),
        ]);

        if (($_SESSION['role'] ?? '') !== 'admin') {
            $stmt = $pdo->prepare('SELECT locate_me_monthly_limit FROM users WHERE id = :id');
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $decoded['limit'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = 'locate_me' AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            );
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $decoded['used'] = (int) $stmt->fetchColumn();

            $response = json_encode($decoded);
        }
    } catch (PDOException $e) {}

    if (!empty($decoded['found']) && !empty($decoded['records'])) {
        archiveLocateMeResults($decoded['records'], currentUser()['username'] ?? 'unknown', LOCATEME_TOOLS[$tool]['label'] . ': ' . $query);
    }
}

http_response_code($httpCode ?: 200);
echo $response;
