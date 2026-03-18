<?php
// Shared helpers to record batch job runs into `batch_job_runs` and
// update `batch_jobs` timestamps/status. Callers must provide the
// numeric batch job id (hardcoded in scripts where desired).

require_once __DIR__ . '/../dbconnection.php';

function start_batch_run($batchJobId)
{
    $pid = getmypid();
    $stmt = executeSQL("INSERT INTO batch_job_runs (batch_job_id, status, scheduled_at, started_at, worker_pid, created_at, modified_at) VALUES (?, 'running', NOW(), NOW(), ?, NOW(), NOW())", [$batchJobId, $pid]);
    $insertId = getConnection()->insert_id;
    $stmt->close();
    executeSQL("UPDATE batch_jobs SET last_started_at = NOW(), last_status = 'running' WHERE id = ?", [$batchJobId])->close();
    return $insertId;
}

function finish_batch_run($runId, $batchJobId, $status, $durationMs = null, $result = null, $output = null, $error = null)
{
    $resultJson = $result !== null ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt = executeSQL("UPDATE batch_job_runs SET status = ?, finished_at = NOW(), duration_ms = ?, result_json = ?, output = ?, error_message = ?, modified_at = NOW() WHERE id = ?", [$status, $durationMs, $resultJson, $output, $error, $runId]);
    $stmt->close();
    executeSQL("UPDATE batch_jobs SET last_finished_at = NOW(), last_status = ? WHERE id = ?", [$status, $batchJobId])->close();
}

?>
