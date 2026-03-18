<?php
require_once __DIR__ . '/corsheaders.php';
require_once __DIR__ . '/batch/run_logger.php';
require_once __DIR__ . '/race_function.php';

// Hardcoded batch job id for this script
$BATCH_JOB_ID = 3;

// Accept event id via GET or default to 1
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 1;

try {
    $runId = start_batch_run($BATCH_JOB_ID);
    $startTime = microtime(true);

    $result = create_or_update_race($eventId);

    $durationMs = (int)( (microtime(true) - $startTime) * 1000 );

    if (isset($result['ok']) && $result['ok']) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        finish_batch_run($runId, $BATCH_JOB_ID, 'success', $durationMs, $result, null, null);
    } else {
        $code = isset($result['error']) ? 500 : 404;
        http_response_code($code);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $status = isset($result['error']) ? 'failed' : 'success';
        finish_batch_run($runId, $BATCH_JOB_ID, $status, $durationMs, $result, null, isset($result['error']) ? $result['error'] : null);
    }
} catch (Exception $e) {
    $durationMs = isset($startTime) ? (int)( (microtime(true) - $startTime) * 1000 ) : null;
    $errorMsg = $e->getMessage();
    if (isset($runId)) {
        finish_batch_run($runId, $BATCH_JOB_ID, 'failed', $durationMs, null, null, $errorMsg);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $errorMsg]);
}

?>
