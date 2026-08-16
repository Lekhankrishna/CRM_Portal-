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
require_once __DIR__ . '/includes/rcprint_archive.php';

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

// RC Print/HP Gas Advanced keep the exact same input normalization/
// validation their old standalone pages (rc_print_api.php/hp_gas_api.php)
// enforced, rather than the generic 1-100 char check below - preserves
// behavior parity now that they're reached through this shared endpoint.
if ($tool === 'rc-print') {
    $query = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $query));
    if ($query === '' || strlen($query) < 4 || strlen($query) > 15) {
        http_response_code(400);
        echo json_encode(['error' => 'Enter a valid vehicle registration number.']);
        exit;
    }
} elseif ($tool === 'hp-gas-advanced') {
    $query = preg_replace('/\D/', '', $query);
    if (strlen($query) !== 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Enter a valid 10-digit mobile number.']);
        exit;
    }
} elseif ($query === '' || mb_strlen($query) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Enter a value to search.']);
    exit;
}

// rc-print and hp-gas-advanced keep their OWN pre-existing access flag,
// monthly-limit column, and search_logs search_type (continuing the exact
// same usage count agents already had before these were folded into
// Locate Me as tabs) - see includes/locateme_tools.php's comment on why
// they don't share locate_me_access/locate_me_monthly_limit like every
// other tool here.
$requiresAccess = LOCATEME_TOOLS[$tool]['requiresAccess'] ?? null;

switch ($requiresAccess) {
    case 'rc_print':
        if (!hasRcPrintAccess()) {
            http_response_code(403);
            echo json_encode(['error' => 'RC Print access has not been granted for this account.']);
            exit;
        }
        $limitColumn = 'rc_print_monthly_limit';
        $searchType  = 'rc_print';
        $limitLabel  = 'RC Print';
        break;
    case 'hp_gas':
        if (!hasHpGasAccess()) {
            http_response_code(403);
            echo json_encode(['error' => 'HP LPG Search access has not been granted for this account.']);
            exit;
        }
        $limitColumn = 'hp_gas_monthly_limit';
        $searchType  = 'hp_gas';
        $limitLabel  = 'HP LPG Search';
        break;
    default:
        // Per-tool checklist (Admin > Agents > "Locate Me" -> expandable
        // tool list, see migrate_add_locate_me_tools.sql) - on top of the
        // page-level requireLocateMeAccess() check above, an agent can be
        // restricted to a subset of tools rather than all-or-nothing.
        if (!hasLocateMeToolAccess($tool)) {
            http_response_code(403);
            echo json_encode(['error' => LOCATEME_TOOLS[$tool]['label'] . ' access has not been granted for this account.']);
            exit;
        }
        $limitColumn = 'locate_me_monthly_limit';
        $searchType  = 'locate_me';
        $limitLabel  = 'Locate Me';
}

// Every tool spends real credits on the single shared locateme.services
// account, so every agent is capped per calendar month (Admin > Agents) -
// admins bypass this entirely, same as every other metered tool in this app.
if (($_SESSION['role'] ?? '') !== 'admin') {
    $stmt = $pdo->prepare("SELECT $limitColumn FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $limit = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = :type AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->execute(['id' => $_SESSION['user_id'], 'type' => $searchType]);
    $usedThisMonth = (int) $stmt->fetchColumn();

    if ($usedThisMonth >= $limit) {
        http_response_code(429);
        echo json_encode([
            'error' => "Monthly $limitLabel limit reached ($usedThisMonth/$limit this month). Contact your admin to increase it, or try again next month.",
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
        // RC Print's success shape has no "records" array (its result is a
        // PDF, not label/value fields) - count as 1 completed lookup on
        // success, matching the old rc_print_api.php's own logging exactly.
        if ($tool === 'rc-print') {
            $recordCount = !empty($decoded['pdfDataUri']) ? 1 : 0;
        } else {
            $recordCount = $decoded['found'] ? count($decoded['records'] ?? []) : 0;
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare(
            "INSERT INTO search_logs (user_id, search_type, search_query, result_count, ip_address)
             VALUES (:uid, :type, :q, :cnt, :ip)"
        )->execute([
            'uid' => $_SESSION['user_id'],
            'type' => $searchType,
            'q' => LOCATEME_TOOLS[$tool]['label'] . ': ' . $query,
            'cnt' => $recordCount,
            'ip' => substr($ip, 0, 45),
        ]);

        if (($_SESSION['role'] ?? '') !== 'admin') {
            $stmt = $pdo->prepare("SELECT $limitColumn FROM users WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $decoded['limit'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM search_logs WHERE user_id = :id AND search_type = :type AND searched_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            );
            $stmt->execute(['id' => $_SESSION['user_id'], 'type' => $searchType]);
            $decoded['used'] = (int) $stmt->fetchColumn();

            $response = json_encode($decoded);
        }
    } catch (PDOException $e) {}

    if ($tool === 'rc-print' && !empty($decoded['pdfDataUri'])) {
        archiveRcPrintResult($decoded['pdfDataUri'], $query, currentUser()['username'] ?? 'unknown');
    } elseif (!empty($decoded['found']) && !empty($decoded['records'])) {
        archiveLocateMeResults($decoded['records'], currentUser()['username'] ?? 'unknown', LOCATEME_TOOLS[$tool]['label'] . ': ' . $query);
    }
}

http_response_code($httpCode ?: 200);
echo $response;
