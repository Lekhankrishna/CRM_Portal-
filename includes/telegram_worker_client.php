<?php

const TELEGRAM_WORKER_ADDRESS = 'tcp://127.0.0.1:8091';

// 190s default - the search action's own worst-case budget is now bounded
// (see cli/telegram_worker.php's telegramCallWithRetry()): the pre-search
// history check is gone (the worker now caches the last-seen message ID
// across searches instead of re-fetching it each time), leaving up to 50s
// (2x25s tries) for sending the query, then a 40s polling window whose own
// per-call cap is 15s - roughly 90s worst case plus overhead. DC 5 (this
// peer's datacenter) has proven unpredictably slow from this network,
// confirmed live over many rounds of testing - not a fixed amount of lag,
// so retried with fresh attempts rather than just waiting longer on one. A
// client-side wait much shorter than the worker's own budget could time
// out on a search that's still genuinely in progress.
function telegramWorkerRequest(array $request, int $timeout = 190): array
{
    $errorNumber = 0;
    $errorMessage = '';
    $socket = @stream_socket_client(
        TELEGRAM_WORKER_ADDRESS,
        $errorNumber,
        $errorMessage,
        2,
        STREAM_CLIENT_CONNECT
    );
    if ($socket === false) {
        throw new RuntimeException(
            'Telegram connection is not running. Stop the current server and start it with run_crm_server.bat.'
        );
    }

    stream_set_timeout($socket, $timeout);
    fwrite($socket, json_encode($request, JSON_UNESCAPED_SLASHES) . "\n");
    $response = fgets($socket);
    $metadata = stream_get_meta_data($socket);
    fclose($socket);

    if ($response === false || !empty($metadata['timed_out'])) {
        throw new RuntimeException('Telegram worker did not respond in time.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Telegram worker returned an invalid response.');
    }
    return $decoded;
}
