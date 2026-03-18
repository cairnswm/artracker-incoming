<?php
/**
 * php/process_teamtrack.php
 *
 * Runs the `batch_job_update_team_track` batch job (from `php/batch/jobs.php`).
 * ID: 4 in `batch_jobs` table. Accepts optional `team_id`, `start`, and `end`
 */

require_once __DIR__ . '/batch/jobs.php';
require_once __DIR__ . '/batch/run_logger.php';

// Hardcoded batch job id for this script
$BATCH_JOB_ID = 4;

try {
    $runId = start_batch_run($BATCH_JOB_ID);
    $startTime = microtime(true);

    // Accept optional team_id, start, end via CLI args or $_GET when invoked via web
    $params = [];
    if (PHP_SAPI === 'cli') {
        $argv = $_SERVER['argv'];
        if (isset($argv[1])) $params['team_id'] = (int)$argv[1];
        if (isset($argv[2])) $params['start'] = $argv[2];
        if (isset($argv[3])) $params['end'] = $argv[3];
    } else {
        if (isset($_GET['team_id'])) $params['team_id'] = (int)$_GET['team_id'];
        if (isset($_GET['start'])) $params['start'] = $_GET['start'];
        if (isset($_GET['end'])) $params['end'] = $_GET['end'];
    }

    $result = batch_job_update_team_track($params);

    $durationMs = (int)((microtime(true) - $startTime) * 1000);
    echo json_encode($result) . PHP_EOL;
    $status = (!empty($result['success'])) ? 'success' : 'failed';
    finish_batch_run($runId, $BATCH_JOB_ID, $status, $durationMs, $result, null, isset($result['error']) ? $result['error'] : null);
    exit(0);
} catch (Throwable $e) {
    $durationMs = isset($startTime) ? (int)((microtime(true) - $startTime) * 1000) : null;
    $err = $e->getMessage();
    if (isset($runId)) {
        finish_batch_run($runId, $BATCH_JOB_ID, 'failed', $durationMs, null, null, $err);
    }
    echo json_encode(['success' => false, 'error' => $err]) . PHP_EOL;
    exit(1);
}

