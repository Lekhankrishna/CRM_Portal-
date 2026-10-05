<?php
// Generic read-through cache for the live vendor-lookup tools (Tata Sky,
// Pan India, RC Print, Advanced Search, Night Out, HP Gas, Advance Pan
// India, LPG Search, Tracing 2.0) - added 2026-08-27 per explicit request
// to speed up a repeat search instead of re-running the full live fetch
// every time. Caches the exact JSON response payload each API was about to
// send back anyway, keyed by a hash of the normalized search parameters -
// this means a hit can replay byte-for-byte without this file needing to
// understand any tool's own field shape. Cached forever, no expiration -
// explicit choice (speed over staleness protection). The existing
// D:-drive CSV archives (includes/*_archive.php) are untouched and keep
// serving their own separate disaster-recovery purpose - this cache lives
// in the same MySQL database as everything else in the app.

// Builds the fixed-width cache key from one or more raw search parameters.
// Always hashed (never the raw value) so callers never have to worry about
// VARCHAR length limits regardless of how many/how long the input fields
// are - a single mobile number and a 6-field OR search both produce the
// same 32-char key shape. Case/whitespace are NOT normalized here - each
// caller already normalizes its own parameters (e.g. trimming, digit-only
// mobile numbers) before this is called, since what counts as "the same
// search" is tool-specific.
function searchCacheKey(string ...$parts): string {
    return md5(implode("\x1f", $parts));
}

// Returns the previously-cached result array for this exact search, or
// null on a miss (including any DB error - a caching problem must never
// block the actual search).
function searchCacheGet(PDO $pdo, string $table, string $searchKey): ?array {
    try {
        $stmt = $pdo->prepare("SELECT result_json FROM `$table` WHERE search_key = :k LIMIT 1");
        $stmt->execute(['k' => $searchKey]);
        $json = $stmt->fetchColumn();
        if ($json === false) return null;
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : null;
    } catch (Throwable $e) {
        return null;
    }
}

// Stores (or refreshes) the result for this search. Best-effort - never
// throws, so a caching failure can't turn a successful search into an
// error response.
function searchCacheStore(PDO $pdo, string $table, string $searchKey, string $searchKeyDisplay, array $result, string $searchedBy): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `$table` (search_key, search_key_display, result_json, searched_by, created_at)
             VALUES (:k, :kd, :j, :u, NOW())
             ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), searched_by = VALUES(searched_by)"
        );
        $stmt->execute([
            'k'  => $searchKey,
            'kd' => mb_substr($searchKeyDisplay, 0, 255),
            'j'  => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'u'  => $searchedBy,
        ]);
    } catch (Throwable $e) {
        // best-effort - see comment above
    }
}

// A cache hit still delivers a real result to the agent, so it must still
// log/count exactly like a live search would - shared by every caller that
// needs this (the Nexora-backed tools' own *_search.php endpoints), rather
// than each one repeating the same INSERT inline.
function searchLogSavedResult(PDO $pdo, string $type, string $query, int $count): void {
    if (!isset($_SESSION['user_id'])) return;
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare(
            "INSERT INTO search_logs (user_id, search_type, search_query, result_count, ip_address)
             VALUES (:uid, :type, :q, :cnt, :ip)"
        )->execute([
            'uid' => $_SESSION['user_id'], 'type' => $type, 'q' => mb_substr($query, 0, 512),
            'cnt' => $count, 'ip' => substr($ip, 0, 45),
        ]);
    } catch (Throwable $e) {}
}
