<?php

require_once __DIR__ . '/../dbconnection.php';

function batch_worker_stmt_rows($stmt)
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

function batch_worker_query_one($sql, $params = [])
{
    $stmt = executeSQL($sql, $params);
    $rows = batch_worker_stmt_rows($stmt);
    $stmt->close();
    return empty($rows) ? null : $rows[0];
}

function batch_worker_json($value)
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? null : $json;
}

function batch_worker_finish_run($runId, $status, $startedAtMs, $output, $result, $errorMessage)
{
    $durationMs = max(0, (int)round((microtime(true) * 1000) - $startedAtMs));

    $stmt = executeSQL(
        'UPDATE batch_job_runs
         SET status = ?,
             finished_at = NOW(),
             duration_ms = ?,
             output = ?,
             result_json = ?,
             error_message = ?,
             modified_at = NOW()
         WHERE id = ? AND status = ?',
        [
            $status,
            (string)$durationMs,
            $output,
            batch_worker_json($result),
            $errorMessage,
            (string)$runId,
            'running',
        ]
    );

    $updated = getConnection()->affected_rows;
    $stmt->close();

    if ($updated > 0) {
        executeSQL(
            'UPDATE batch_jobs j
             JOIN batch_job_runs r ON r.batch_job_id = j.id
             SET j.last_finished_at = NOW(), j.last_status = ?, j.modified_at = NOW()
             WHERE r.id = ?',
            [$status, (string)$runId]
        )->close();
    }
}

function batch_worker_main($runId)
{
    $startedAtMs = (int)round(microtime(true) * 1000);
    try {
        $run = batch_worker_query_one(
            'SELECT r.id AS run_id, r.status AS run_status, j.id AS job_id, j.file_path, j.function_name, j.params_json, j.max_execution_seconds
             FROM batch_job_runs r
             JOIN batch_jobs j ON j.id = r.batch_job_id
             WHERE r.id = ? LIMIT 1',
            [(string)$runId]
        );

        if (!$run || $run['run_status'] !== 'running') {
            return;
        }

        $maxSeconds = (int)$run['max_execution_seconds'];
        if ($maxSeconds > 0) {
            @set_time_limit($maxSeconds + 1);
        }

        executeSQL(
            'UPDATE batch_job_runs SET worker_pid = ? WHERE id = ? AND status = ?',
            [(string)getmypid(), (string)$runId, 'running']
        )->close();

        // Ensure runs that exceed `max_execution_seconds` are marked as timed out
        // even if PHP terminates the process (e.g. due to max execution time).
        register_shutdown_function(function() use ($runId, $startedAtMs, $maxSeconds) {
            try {
                $elapsedMs = (int) round((microtime(true) * 1000) - $startedAtMs);
                if ($maxSeconds > 0 && $elapsedMs < ($maxSeconds * 1000)) {
                    return;
                }

                $row = batch_worker_query_one('SELECT status FROM batch_job_runs WHERE id = ? LIMIT 1', [(string)$runId]);
                if (!$row || $row['status'] !== 'running') {
                    return;
                }

                batch_worker_finish_run($runId, 'timeout', $startedAtMs, '', null, 'max_execution_time_exceeded');
            } catch (Throwable $_) {
                // suppress any errors in shutdown handler
            }
        });

        $paramsRaw = $run['params_json'];
        $params = [];
        if ($paramsRaw !== null && $paramsRaw !== '') {
            $decoded = json_decode($paramsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                batch_worker_finish_run($runId, 'failed', $startedAtMs, '', null, 'invalid_params_json');
                return;
            }
            $params = $decoded;
        }

        $filePath = (string)$run['file_path'];
        if ($filePath === '') {
            batch_worker_finish_run($runId, 'failed', $startedAtMs, '', null, 'empty_file_path');
            return;
        }

        if (preg_match('/^[A-Za-z]:\\\\|^\\\\\\\\/', $filePath) === 1) {
            $resolved = realpath($filePath);
        } else {
            $resolved = realpath(__DIR__ . '/../' . ltrim($filePath, '/\\'));
        }

        if ($resolved === false || !is_file($resolved)) {
            batch_worker_finish_run($runId, 'failed', $startedAtMs, '', null, 'file_not_found');
            return;
        }

        require_once $resolved;

        $functionName = (string)$run['function_name'];
        if ($functionName === '' || !function_exists($functionName)) {
            batch_worker_finish_run($runId, 'failed', $startedAtMs, '', null, 'function_not_found');
            return;
        }

        $args = [];
        if (is_array($params)) {
            $isAssoc = array_keys($params) !== range(0, count($params) - 1);
            if ($isAssoc) {
                $args[] = $params;
            } else {
                $args = $params;
            }
        } elseif ($params !== null) {
            $args[] = $params;
        }

        ob_start();
        try {
            $result = call_user_func_array($functionName, $args);
            $output = (string)ob_get_clean();
            batch_worker_finish_run($runId, 'success', $startedAtMs, $output, $result, null);
        } catch (Throwable $e) {
            $output = (string)ob_get_clean();
            batch_worker_finish_run($runId, 'failed', $startedAtMs, $output, null, $e->getMessage());
        }
    } catch (Throwable $e) {
        batch_worker_finish_run($runId, 'failed', $startedAtMs, '', null, 'worker_bootstrap_failed: ' . $e->getMessage());
    }
}

$runId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($runId > 0) {
    batch_worker_main($runId);
}
