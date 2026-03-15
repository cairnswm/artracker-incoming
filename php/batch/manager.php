<?php

require_once __DIR__ . '/../dbconnection.php';

function batch_stmt_rows($stmt)
{
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    $meta = $stmt->result_metadata();
    if (!$meta) {
        return [];
    }

    $row = [];
    $binds = [];
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $binds[] = &$row[$field->name];
    }

    call_user_func_array([$stmt, 'bind_result'], $binds);
    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }

    return $rows;
}

function batch_query_rows($sql, $params = [])
{
    $stmt = executeSQL($sql, $params);
    $rows = batch_stmt_rows($stmt);
    $stmt->close();
    return $rows;
}

function batch_query_one($sql, $params = [])
{
    $rows = batch_query_rows($sql, $params);
    return empty($rows) ? null : $rows[0];
}

function batch_now_ms()
{
    return (int) round(microtime(true) * 1000);
}

function batch_get_due_jobs()
{
    return batch_query_rows(
        'SELECT * FROM batch_jobs WHERE is_active = 1 AND next_run_at <= NOW() ORDER BY next_run_at ASC, id ASC'
    );
}

function batch_acquire_job_lock($jobId)
{
    $lockName = 'batch_job_' . (string)$jobId;
    $row = batch_query_one('SELECT GET_LOCK(?, 0) AS got_lock', [$lockName]);
    return !empty($row) && (int)$row['got_lock'] === 1;
}

function batch_release_job_lock($jobId)
{
    $lockName = 'batch_job_' . (string)$jobId;
    executeSQL('SELECT RELEASE_LOCK(?)', [$lockName])->close();
}

function batch_mark_timed_out_runs($job)
{
    $maxSeconds = isset($job['max_execution_seconds']) ? (int)$job['max_execution_seconds'] : 0;
    if ($maxSeconds <= 0) {
        return 0;
    }

    $timeoutDurationMs = $maxSeconds * 1000;

    $sql = "UPDATE batch_job_runs
            SET status = 'timeout',
                finished_at = NOW(),
                duration_ms = ?,
                error_message = COALESCE(error_message, 'max_execution_time_exceeded'),
                modified_at = NOW()
            WHERE batch_job_id = ?
              AND status = 'running'
              AND TIMESTAMPDIFF(SECOND, started_at, NOW()) >= ?";

    $stmt = executeSQL($sql, [(string)$timeoutDurationMs, (string)$job['id'], (string)$maxSeconds]);
    $affected = getConnection()->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        executeSQL(
            "UPDATE batch_jobs
             SET last_finished_at = NOW(), last_status = 'timeout', modified_at = NOW()
             WHERE id = ?",
            [(string)$job['id']]
        )->close();
    }

    return $affected;
}

function batch_has_running_instance($jobId)
{
    $row = batch_query_one(
        "SELECT COUNT(*) AS cnt
         FROM batch_job_runs
         WHERE batch_job_id = ? AND status = 'running'",
        [(string)$jobId]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function batch_insert_run($job)
{
    $scheduledAt = $job['next_run_at'] ?? date('Y-m-d H:i:s');
    $stmt = executeSQL(
        'INSERT INTO batch_job_runs (batch_job_id, status, scheduled_at, started_at, created_at, modified_at) VALUES (?, ?, ?, NOW(), NOW(), NOW())',
        [(string)$job['id'], 'running', $scheduledAt]
    );
    $runId = (int)getConnection()->insert_id;
    $stmt->close();
    return $runId;
}

function batch_mark_job_dispatched($jobId)
{
    $sql = 'UPDATE batch_jobs
            SET last_started_at = NOW(),
                next_run_at = DATE_ADD(NOW(), INTERVAL run_every_seconds SECOND),
                modified_at = NOW()
            WHERE id = ?';
    executeSQL($sql, [(string)$jobId])->close();
}

function batch_get_php_binary()
{
    $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';

    if (DIRECTORY_SEPARATOR === '\\') {
        $lower = strtolower($phpBinary);
        if (substr($lower, -11) === 'php-cgi.exe') {
            $candidate = substr($phpBinary, 0, -11) . 'php.exe';
            if (is_file($candidate)) {
                return $candidate;
            }
        }
    }

    return $phpBinary;
}

function batch_run_worker_inline($runId)
{
    require_once __DIR__ . '/run_job.php';
    batch_worker_main((int)$runId);
}

function batch_spawn_run_worker($runId)
{
    $reason = null;

    $phpBinary = batch_get_php_binary();
    $workerPath = realpath(__DIR__ . '/run_job.php');
    if ($workerPath === false) {
        return ['ok' => false, 'reason' => 'worker_file_not_found'];
    }

    // Quote paths to allow spaces
    $escapedPhp = '"' . str_replace('"', '\\"', $phpBinary) . '"';
    $escapedWorker = '"' . str_replace('"', '\\"', $workerPath) . '"';
    $runArg = (string)(int)$runId;

    // On Windows: run synchronously and capture stdout+stderr
    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = $escapedPhp . ' ' . $escapedWorker . ' ' . $runArg . ' 2>&1';
        $output = [];
        $exitCode = 0;
        try {
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0) {
                $reason = 'exit_code=' . (string)$exitCode . ' output=' . implode("\n", $output);
                error_log('batch_spawn_run_worker: ' . $reason . ' run_id=' . (string)$runId);

                // Attempt inline fallback when spawn returned non-zero exit code
                try {
                    batch_run_worker_inline($runId);
                    return ['ok' => true, 'reason' => 'spawn_failed_but_inline_ok: ' . $reason];
                } catch (Throwable $ex) {
                    $fallbackErr = 'spawn_failed_and_inline_failed: ' . $ex->getMessage();
                    error_log('batch_spawn_run_worker inline fallback failed for run_id=' . (string)$runId . ' error=' . $ex->getMessage());
                    return ['ok' => false, 'reason' => $fallbackErr];
                }
            }
            return ['ok' => true, 'reason' => null];
        } catch (Throwable $e) {
            error_log('batch_spawn_run_worker exec failed for run_id=' . (string)$runId . ' error=' . $e->getMessage());
            try {
                batch_run_worker_inline($runId);
                return ['ok' => true, 'reason' => null];
            } catch (Throwable $ex) {
                return ['ok' => false, 'reason' => 'inline_fallback_failed: ' . $ex->getMessage()];
            }
        }
    }

    // Non-Windows: spawn detached background process
    if (DIRECTORY_SEPARATOR === '\\') {
        // Fallback path (shouldn't reach here because above returns on Windows)
        $cmd = 'cmd.exe /C start "" /B ' . $escapedPhp . ' ' . $escapedWorker . ' ' . $runArg . ' > NUL 2>&1';
        pclose(popen($cmd, 'r'));
        return ['ok' => true, 'reason' => null];
    }

    $cmd = $escapedPhp . ' ' . $escapedWorker . ' ' . $runArg . ' > /dev/null 2>&1 &';
    exec($cmd);
    return ['ok' => true, 'reason' => null];
}

function batch_run_tick()
{
    $startedMs = batch_now_ms();
    $summary = [
        'success' => true,
        'due_jobs' => 0,
        'dispatched' => [],
        'skipped_running' => [],
        'timed_out_marked' => 0,
        'errors' => [],
    ];

    $jobs = batch_get_due_jobs();
    $summary['due_jobs'] = count($jobs);

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        if (!batch_acquire_job_lock($jobId)) {
            continue;
        }

        try {
            $summary['timed_out_marked'] += batch_mark_timed_out_runs($job);

            $allowParallel = isset($job['allow_parallel']) && (int)$job['allow_parallel'] === 1;
            if (!$allowParallel && batch_has_running_instance($jobId)) {
                $summary['skipped_running'][] = [
                    'job_id' => $jobId,
                    'name' => $job['name'],
                ];
                continue;
            }

            $runId = batch_insert_run($job);
            batch_mark_job_dispatched($jobId);
            $spawnResult = batch_spawn_run_worker($runId);
            if (empty($spawnResult['ok'])) {
                $reason = isset($spawnResult['reason']) && $spawnResult['reason'] !== null ? (string)$spawnResult['reason'] : 'unknown';
                $errMsg = 'failed_to_spawn_worker: ' . $reason;
                if (strlen($errMsg) > 1024) $errMsg = substr($errMsg, 0, 1024);

                executeSQL(
                    "UPDATE batch_job_runs
                     SET status = 'failed', finished_at = NOW(), error_message = ?, modified_at = NOW()
                     WHERE id = ? AND status = 'running'",
                    [$errMsg, (string)$runId]
                )->close();
                executeSQL(
                    "UPDATE batch_jobs
                     SET last_finished_at = NOW(), last_status = 'failed', modified_at = NOW()
                     WHERE id = ?",
                    [(string)$jobId]
                )->close();
                $summary['errors'][] = ['job_id' => $jobId, 'error' => 'failed_to_spawn_worker', 'reason' => $reason];
                continue;
            }

            $summary['dispatched'][] = [
                'job_id' => $jobId,
                'run_id' => $runId,
                'name' => $job['name'],
            ];
        } catch (Throwable $e) {
            $summary['errors'][] = [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ];
        } finally {
            batch_release_job_lock($jobId);
        }
    }

    $summary['duration_ms'] = batch_now_ms() - $startedMs;
    if (!empty($summary['errors'])) {
        $summary['success'] = false;
    }

    return $summary;
}
