<?php

require_once __DIR__ . '/../corsheaders.php';
require_once __DIR__ . '/../dbconnection.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function batch_api_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function batch_api_stmt_rows($stmt)
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

function batch_api_query_rows($sql, $params = [])
{
    $stmt = executeSQL($sql, $params);
    $rows = batch_api_stmt_rows($stmt);
    $stmt->close();
    return $rows;
}

function batch_api_query_one($sql, $params = [])
{
    $rows = batch_api_query_rows($sql, $params);
    return empty($rows) ? null : $rows[0];
}

function batch_api_decode_json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        batch_api_error('invalid_json_body', 400);
    }

    return $data;
}

function batch_api_action_upsert_job()
{
    $body = batch_api_decode_json_body();

    $name = isset($body['name']) ? trim((string)$body['name']) : '';
    $filePath = isset($body['file_path']) ? trim((string)$body['file_path']) : '';
    $functionName = isset($body['function_name']) ? trim((string)$body['function_name']) : '';
    $runEverySeconds = isset($body['run_every_seconds']) ? (int)$body['run_every_seconds'] : 60;
    $maxExecutionSeconds = isset($body['max_execution_seconds']) ? (int)$body['max_execution_seconds'] : 60;
    $allowParallel = !empty($body['allow_parallel']) ? 1 : 0;
    $isActive = array_key_exists('is_active', $body) ? (!empty($body['is_active']) ? 1 : 0) : 1;
    $params = isset($body['params']) ? $body['params'] : [];

    if ($name === '' || $filePath === '' || $functionName === '') {
        batch_api_error('name_file_path_function_name_required', 422);
    }
    if ($runEverySeconds <= 0) {
        batch_api_error('run_every_seconds_must_be_gt_0', 422);
    }
    if ($maxExecutionSeconds <= 0) {
        batch_api_error('max_execution_seconds_must_be_gt_0', 422);
    }

    $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($paramsJson === false) {
        batch_api_error('invalid_params_json', 422);
    }

    $existing = batch_api_query_one('SELECT id FROM batch_jobs WHERE name = ? LIMIT 1', [$name]);
    if ($existing) {
        executeSQL(
            'UPDATE batch_jobs
             SET is_active = ?,
                 run_every_seconds = ?,
                 max_execution_seconds = ?,
                 allow_parallel = ?,
                 file_path = ?,
                 function_name = ?,
                 params_json = ?,
                 modified_at = NOW()
             WHERE id = ?',
            [
                (string)$isActive,
                (string)$runEverySeconds,
                (string)$maxExecutionSeconds,
                (string)$allowParallel,
                $filePath,
                $functionName,
                $paramsJson,
                (string)$existing['id'],
            ]
        )->close();

        echo json_encode(['success' => true, 'action' => 'updated', 'job_id' => (int)$existing['id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    executeSQL(
        'INSERT INTO batch_jobs
         (name, is_active, run_every_seconds, max_execution_seconds, allow_parallel, file_path, function_name, params_json, next_run_at, created_at, modified_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
        [
            $name,
            (string)$isActive,
            (string)$runEverySeconds,
            (string)$maxExecutionSeconds,
            (string)$allowParallel,
            $filePath,
            $functionName,
            $paramsJson,
        ]
    )->close();

    echo json_encode(
        ['success' => true, 'action' => 'created', 'job_id' => (int)getConnection()->insert_id],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function batch_api_action_list_jobs()
{
    $rows = batch_api_query_rows(
        'SELECT id, name, is_active, run_every_seconds, max_execution_seconds, allow_parallel, file_path, function_name, params_json, next_run_at, last_started_at, last_finished_at, last_status, created_at, modified_at
         FROM batch_jobs
         ORDER BY id ASC'
    );

    foreach ($rows as &$row) {
        if (isset($row['params_json']) && $row['params_json'] !== null && $row['params_json'] !== '') {
            $decoded = json_decode($row['params_json'], true);
            $row['params'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['params_json'];
        } else {
            $row['params'] = [];
        }
        unset($row['params_json']);
    }

    echo json_encode(['success' => true, 'jobs' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function batch_api_action_list_runs()
{
    $jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    if ($jobId > 0) {
        $rows = batch_api_query_rows(
            'SELECT r.id, r.batch_job_id, j.name AS job_name, r.status, r.scheduled_at, r.started_at, r.finished_at, r.duration_ms, r.worker_pid, r.output, r.result_json, r.error_message, r.created_at
             FROM batch_job_runs r
             JOIN batch_jobs j ON j.id = r.batch_job_id
             WHERE r.batch_job_id = ?
             ORDER BY r.id DESC
             LIMIT ?',
            [(string)$jobId, (string)$limit]
        );
    } else {
        $rows = batch_api_query_rows(
            'SELECT r.id, r.batch_job_id, j.name AS job_name, r.status, r.scheduled_at, r.started_at, r.finished_at, r.duration_ms, r.worker_pid, r.output, r.result_json, r.error_message, r.created_at
             FROM batch_job_runs r
             JOIN batch_jobs j ON j.id = r.batch_job_id
             ORDER BY r.id DESC
             LIMIT ?',
            [(string)$limit]
        );
    }

    foreach ($rows as &$row) {
        if (isset($row['result_json']) && $row['result_json'] !== null && $row['result_json'] !== '') {
            $decoded = json_decode($row['result_json'], true);
            $row['result'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['result_json'];
        } else {
            $row['result'] = null;
        }
        unset($row['result_json']);
    }

    echo json_encode(['success' => true, 'runs' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
    if ($action === '') {
        batch_api_error('missing_action', 422);
    }

    if ($action === 'upsert_job') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            batch_api_error('method_not_allowed', 405);
        }
        batch_api_action_upsert_job();
    }

    if ($action === 'list_jobs') {
        batch_api_action_list_jobs();
    }

    if ($action === 'list_runs') {
        batch_api_action_list_runs();
    }

    batch_api_error('unknown_action', 404);
} catch (Throwable $e) {
    batch_api_error($e->getMessage(), 500);
}
