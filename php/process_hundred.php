<?php
/**
 * php/process_hundred.php
 *
 * Wrapper that calls the batch `batch_job_process_hundred` implementation
 * in `php/batch/jobs.php` so we keep the optimized logic in one place.
 */

require_once __DIR__ . '/batch/jobs.php';

try {
    $limit = 100;
    if (isset($argv) && count($argv) > 1) {
        $arg = intval($argv[1]);
        if ($arg > 0) $limit = $arg;
    }

    $result = batch_job_process_hundred(['limit' => $limit]);
    echo json_encode($result);
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
    exit(1);
}
