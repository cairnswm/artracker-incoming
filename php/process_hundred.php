<?php
/**
 * php/process_hundred.php
 *
 * Wrapper that calls the batch `batch_job_process_hundred` implementation
 * in `php/batch/jobs.php` so we keep the optimized logic in one place.
 */

require_once __DIR__ . '/batch/jobs.php';
require_once __DIR__ . '/batch/run_logger.php';

// Hardcoded batch job id for this script
$BATCH_JOB_ID = 1;

try {
    $limit = 300;
    if (isset($argv) && count($argv) > 1) {
        $arg = intval($argv[1]);
        if ($arg > 0) $limit = $arg;
    }

    $runId = start_batch_run($BATCH_JOB_ID);
    $startTime = microtime(true);

    $result = batch_job_process_hundred(['limit' => $limit]);

    $durationMs = (int)( (microtime(true) - $startTime) * 1000 );
    $output = null;
    echo json_encode($result);

    finish_batch_run($runId, $BATCH_JOB_ID, 'success', $durationMs, $result, $output, null);
    exit(0);
} catch (Throwable $e) {
    $durationMs = isset($startTime) ? (int)( (microtime(true) - $startTime) * 1000 ) : null;
    $errorMsg = $e->getMessage();
    if (isset($runId)) {
        finish_batch_run($runId, $BATCH_JOB_ID, 'failed', $durationMs, null, null, $errorMsg);
    }
    echo json_encode(['success' => false, 'error' => 'exception', 'message' => $errorMsg]);
    exit(1);
}
