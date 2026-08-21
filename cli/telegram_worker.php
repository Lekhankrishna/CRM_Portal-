<?php

set_time_limit(0);
require __DIR__ . '/../config/telegram.php';

// Force MadelineProto to deserialize and run the authorized session inside
// this long-lived worker. Without this flag it hands the session to its own
// Windows IPC child and our CRM listener below never gets a chance to start.
$_GET['MadelineSelfRestart'] = '1';

$serviceDirectory = realpath(__DIR__ . '/../Gas/telegram_php');
if ($serviceDirectory === false || !chdir($serviceDirectory)) {
    fwrite(STDERR, "Telegram service directory is unavailable." . PHP_EOL);
    exit(1);
}

require $serviceDirectory . '/madeline.php';

\Revolt\EventLoop::setErrorHandler(static function (Throwable $error): void {
    // QR expiration and DC hand-off intentionally cancel pending requests.
    // MadelineProto may surface that normal cancellation through the Windows
    // event loop; ignore only this expected type and preserve real failures.
    if ($error instanceof \Amp\CancelledException) return;
    throw $error;
});

function telegramRetryAfterCancellation(callable $operation, int $attempts = 12): mixed
{
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            return $operation();
        } catch (Throwable $error) {
            // Amp\TimeoutException added 2026-08-21: API::__construct()'s own
            // internal wakeup() does an automatic getSelf() with no retry
            // protection of its own, and that call's outer exception is a
            // plain Amp\TimeoutException ("Timeout while waiting for
            // users.getUsers") - not a CancelledException, and its own
            // ->getMessage() doesn't contain "operation was cancelled" (that
            // text is on the chained/previous exception, which ->getMessage()
            // doesn't see). Confirmed live: this crashed the whole worker
            // twice in a row at the exact same construction step, right
            // after a real, successful DC5/DC2 key exchange - a transient
            // hiccup, not a hard failure, so it belongs in the same
            // retryable bucket as an explicit cancellation.
            $cancelled = $error instanceof \Amp\CancelledException
                || $error instanceof \Amp\TimeoutException
                || stripos($error->getMessage(), 'operation was cancelled') !== false;
            if (!$cancelled || $attempt === $attempts) throw $error;
            echo "Telegram is completing the login hand-off; retrying..." . PHP_EOL;
            usleep(500000);
        }
    }
    throw new RuntimeException('Telegram login hand-off did not complete.');
}

function cleanTelegramResult(string $text): string
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $firstDataLine = null;
    foreach ($lines as $index => $line) {
        $plainLine = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $line);
        if (preg_match(
            '/^\s*(?:telephone|email|aadhaar(?:\s+number)?|document\s+number|adres|address|full\s+name|the\s+name\s+of\s+the\s+father|region)\s*:/i',
            (string) $plainLine
        )) {
            $firstDataLine = $index;
            break;
        }
    }

    // The bot sometimes prefixes records with a long source/leak description.
    // Display only the structured record beginning at its first actual field.
    if ($firstDataLine !== null) $lines = array_slice($lines, $firstDataLine);
    $cleaned = implode(PHP_EOL, $lines);
    $cleaned = preg_replace(
        '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u',
        '',
        $cleaned
    );

    // The bot's own field names, relabeled for display - these come from the
    // third-party bot's raw response text, not a form we control, so this is
    // a find-and-replace on the field label at the start of a line rather
    // than a real schema/property rename.
    $fieldLabels = [
        '/^(\s*)telephone(\s*:)/im'        => '$1Phone Number$2',
        '/^(\s*)region(\s*:)/im'           => '$1Operator$2',
        '/^(\s*)document\s+number(\s*:)/im' => '$1Aadhaar Number$2',
        '/^(\s*)adres(\s*:)/im'            => '$1Address$2',
        '/^(\s*)stat(\s*:)/im'             => '$1State$2',
    ];
    $cleaned = preg_replace(array_keys($fieldLabels), array_values($fieldLabels), $cleaned);

    return trim((string) $cleaned);
}

$settings = new \danog\MadelineProto\Settings;
$settings->setAppInfo(
    (new \danog\MadelineProto\Settings\AppInfo)
        ->setApiId((int) $TELEGRAM_API_ID)
        ->setApiHash((string) $TELEGRAM_API_HASH)
);

// ROOT CAUSE FOUND AND FIXED (2026-08-19), after the settings-tweaking
// attempts below (protocol switches, timeout bump) all failed to help - it
// was never a network/firewall issue. It was a real MadelineProto 8.6.5
// library bug (confirmed still present in current upstream master and in
// 8.7.0 too, so not something a version bump would have fixed):
// Connection::getInputClientProxy() (Connection.php:240) dereferences its
// $chosenCtx property without a null check. $chosenCtx is only assigned
// inside connect(), once a TCP stream is actually established - but
// DataCenterConnection::initAuthorization()'s ENCRYPTED_NOT_INITED branch
// calls getInputClientProxy() eagerly, as part of building the
// invokeWithLayer/initConnection arguments, BEFORE the methodCallAsyncRead()
// call that would itself trigger connect(). Because this worker's session
// already has a persisted permanent auth key, every fresh process reaches
// ENCRYPTED_NOT_INITED as the very first state transition - so this crashed
// on literally every login attempt. The library's own ctx-fallback then
// silently opened a second connection (which succeeded at the raw TCP
// level) but never re-queued the actual handshake message on it, so it just
// sat idle until the ~30-65s timeout we kept seeing. Verified with
// protocol-level verbose logging (Settings\Logger at ULTRA_VERBOSE) against
// a throwaway copy of the phar.
//
// Fixed with a one-line vendor patch to the bundled
// madeline-8.6.5.phar (original backed up alongside it as
// madeline-8.6.5.phar.bak-preNullSafePatch): changed
// `return $this->chosenCtx->getInputClientProxy();` to
// `return $this->chosenCtx?->getInputClientProxy();` in
// vendor/danog/madelineproto/src/Connection.php - safe because the method's
// own return type was already declared nullable (?array), so "no chosen
// context yet" correctly reporting "no proxy" rather than crashing is
// exactly the intended contract, not a behavior change for the normal case.
//
// Fixing this crash immediately exposed a SECOND, previously-masked issue:
// Telegram's servers were rejecting this exact session's auth key with
// AUTH_KEY_DUPLICATED - a real server-side security rejection (almost
// certainly triggered by the 211-stuck-process incident from the same day,
// where many worker processes concurrently reused the same saved key,
// which is exactly what this Telegram protection exists to catch), not
// fixable from client-side settings. The old worker.session was backed up
// (worker.session.bak-authKeyDuplicated-<timestamp>) and a fresh QR login
// was required to get a new, valid key.
// Post-login (2026-08-21): even after fixing the crash and re-authenticating,
// getFullDialogs() kept failing on a fresh danog\MadelineProto\
// NothingInTheSocketException on every single retry (8 in a row) - the raw,
// unencrypted AbridgedStream connection to DC 2 kept getting cut mid-read.
// This is the same failure mode HttpsStream ran into before, except that
// attempt was blocked by the getInputClientProxy() crash (now fixed) before
// it could actually be evaluated on its own merits - a real TLS-wrapped
// transport (indistinguishable from ordinary HTTPS to anything on this
// network inspecting the raw framing) is the natural next thing to try now
// that it's actually testable.
$settings->setConnection(
    (new \danog\MadelineProto\Settings\Connection)
        ->setTimeout(30)
        ->setProtocol(\danog\MadelineProto\Stream\MTProtoTransport\HttpsStream::class)
);

try {
    // Wrapped in the same retry helper as every other call below (2026-08-21)
    // - construction itself was the one place a transient timeout during
    // MadelineProto's own internal post-login wakeup() check had zero retry
    // protection, and that's exactly what crashed the worker twice in a row.
    $api = telegramRetryAfterCancellation(
        fn() => new \danog\MadelineProto\API($serviceDirectory . '/worker.session', $settings),
        5
    );
    echo PHP_EOL . "ONE-TIME TELEGRAM QR LOGIN" . PHP_EOL;
    echo "On the logged-in phone: Telegram > Settings > Devices > Link Desktop Device." . PHP_EOL;
    echo "Scan the terminal QR code below. It refreshes automatically when expired." . PHP_EOL . PHP_EOL;

    do {
        $qr = $api->qrLogin();
        if ($qr === null) break;
        echo $qr->getQRText(2) . PHP_EOL;
        // ASCII QR text renders unreliably in most viewers (proportional
        // fonts, line-height distorting the square modules) - an actual
        // image scans far more reliably (found 2026-07-29).
        file_put_contents($serviceDirectory . '/qr_login.svg', $qr->getQRSvg(400, 4));
        echo "Waiting for the QR scan..." . PHP_EOL;
        try {
            $qr = $qr->waitForLoginOrQrCodeExpiration();
        } catch (\Amp\CancelledException $e) {
            echo "QR expired or Telegram changed data centre; generating a fresh code..." . PHP_EOL;
            $qr = $api->qrLogin();
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            // AUTH_TOKEN_EXPIRED added 2026-08-21: confirmed live - a real
            // scan can still lose the race against the token's own ~30s
            // lifetime (network/UI delay on the phone side), which Telegram
            // reports as this RPC error, not the Amp\CancelledException the
            // catch above already handles. Previously uncaught, crashing the
            // whole worker instead of just retrying with a fresh code like
            // any other expiration.
            if (stripos($e->getMessage(), 'AUTH_TOKEN_EXPIRED') === false) throw $e;
            echo "Login token expired before the scan was confirmed; generating a fresh code..." . PHP_EOL;
            $qr = $api->qrLogin();
        }
    } while ($qr !== null);

    if ($api->getAuthorization() === \danog\MadelineProto\API::WAITING_PASSWORD) {
        $password = \danog\MadelineProto\Tools::readLine('Telegram two-step verification password: ');
        $api->complete2faLogin($password);
    }

    $self = telegramRetryAfterCancellation(fn() => $api->getSelf());
    if (!$self) {
        throw new RuntimeException('Telegram login was not completed.');
    }

    $needle = strtolower((string) $TELEGRAM_BOT_MATCH);
    $peer = null;
    $dialogs = telegramRetryAfterCancellation(fn() => $api->getDialogIds());
    foreach ($dialogs as $dialog) {
        try {
            $info = telegramRetryAfterCancellation(fn() => $api->getInfo($dialog));
            $user = $info['User'] ?? $info['Chat'] ?? [];
            $name = implode(' ', [
                $user['first_name'] ?? '',
                $user['last_name'] ?? '',
                $user['title'] ?? '',
                $user['username'] ?? '',
            ]);
            if (str_contains(strtolower($name), $needle)) {
                $peer = $dialog;
                break;
            }
        } catch (Throwable $ignored) {
        }
    }
    if ($peer === null) {
        throw new RuntimeException("No Telegram chat matching '{$TELEGRAM_BOT_MATCH}' was found.");
    }

    $server = stream_socket_server('tcp://127.0.0.1:8091', $errorNumber, $errorMessage);
    if ($server === false) {
        throw new RuntimeException("Could not start Telegram worker: {$errorMessage}");
    }
    echo PHP_EOL . "Telegram connected. Worker ready on 127.0.0.1:8091." . PHP_EOL;
    echo "Keep this window open." . PHP_EOL;

    while ($connection = @stream_socket_accept($server, -1)) {
        $line = fgets($connection);
        $request = is_string($line) ? json_decode($line, true) : null;
        try {
            if (!is_array($request)) {
                throw new RuntimeException('Invalid worker request.');
            }
            if (($request['action'] ?? '') === 'status') {
                $response = ['ok' => true];
            } elseif (($request['action'] ?? '') === 'search') {
                $query = trim((string) ($request['query'] ?? ''));
                if ($query === '') throw new RuntimeException('Search query is required.');

                $history = $api->messages->getHistory(peer: $peer, limit: 1);
                $beforeId = (int) ($history['messages'][0]['id'] ?? 0);
                $api->messages->sendMessage(peer: $peer, message: $query);

                $pattern = '/telephone\s*:|email\s*:|aadhaar(?:\s+number)?\s*:|(?:address|adres)\s*:|full\s*name\s*:|name\s+of\s+the\s+father\s*:/i';
                $deadline = microtime(true) + 20;
                $results = [];
                $seen = [];
                $quietSince = null;
                // Polling messages.getHistory this often used to trip Telegram's
                // own flood control - confirmed 2026-07-30 in
                // telegram-worker-output.log ("Flood, waiting 11 seconds before
                // repeating async call of messages.getHistory..."), which cost
                // far more time than it saved. 800ms is the fastest cadence
                // observed to stay clear of that penalty, so it's untouched -
                // do NOT lower it without re-testing against the live bot.
                // The quiet-confirmation window (2026-08-17, "too slow") is a
                // pure local wait *after* the bot has already replied, so it's
                // safe to trim independently: 1.5s -> 1.0s, still comfortably
                // more than one poll cycle (so a second confirmation poll
                // always happens), just shaves ~0.5s off every search.
                do {
                    $history = $api->messages->getHistory(peer: $peer, min_id: $beforeId, limit: 30);
                    foreach ($history['messages'] ?? [] as $message) {
                        $id = (int) ($message['id'] ?? 0);
                        $text = trim((string) ($message['message'] ?? ''));
                        if (!$id || isset($seen[$id]) || !empty($message['out']) || !preg_match($pattern, $text)) continue;
                        $seen[$id] = true;
                        $results[] = [
                            'id' => $id,
                            'text' => cleanTelegramResult($text),
                            'hasMedia' => isset($message['media']),
                        ];
                        $quietSince = microtime(true);
                    }
                    if ($results && $quietSince !== null && microtime(true) - $quietSince >= 1.0) break;
                    usleep(800000);
                } while (microtime(true) < $deadline);

                usort($results, fn(array $a, array $b): int => $a['id'] <=> $b['id']);
                $response = ['ok' => true, 'results' => $results];
            } else {
                throw new RuntimeException('Unknown worker action.');
            }
        } catch (Throwable $error) {
            $response = ['ok' => false, 'error' => $error->getMessage()];
        }
        fwrite($connection, json_encode($response, JSON_UNESCAPED_SLASHES) . "\n");
        fclose($connection);
    }
} catch (Throwable $error) {
    fwrite(STDERR, PHP_EOL . '[ERROR] ' . $error->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Press Enter to close.' . PHP_EOL);
    fgets(STDIN);
    exit(1);
}
