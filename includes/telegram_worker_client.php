<?php

const TELEGRAM_WORKER_ADDRESS = 'tcp://127.0.0.1:8091';

// 75s default - the search action's own worst-case budget is now bounded
// (see cli/telegram_worker.php's telegramCallWithTimeout()): up to 10s for
// the pre-search history check, 25s for sending the query (confirmed live
// this can genuinely take over 10s on this network), then a 20s polling
// window whose own per-call cap is 8s - roughly 63s worst case, so a
// client-side wait much shorter than that could time out on a search
// that's still genuinely in progress rather than actually stuck.
function telegramWorkerRequest(array $request, int $timeout = 75): array
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
