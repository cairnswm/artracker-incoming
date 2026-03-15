<?php
/**
 * php/process_one.php
 *
 * Fetches the newest row from `raw` and passes it to process_point().
 */

require_once __DIR__ . '/process.php';
require_once __DIR__ . '/dbconnection.php';

try {
    $conn = getConnection();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit(1);
}

$res = $conn->query('SELECT * FROM raw ORDER BY id DESC LIMIT 1');
if (!$res) {
    echo json_encode(['success' => false, 'error' => 'query_failed']);
    exit(1);
}

$row = $res->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'no_raw_rows']);
    exit(0);
}

$start = microtime(true);
$result = process_point($row);
$durationMs = (microtime(true) - $start) * 1000.0;
$result['duration_ms'] = round($durationMs, 3);

if (!empty($result['success'])) {
    // successful processing — insert into `processed` then delete the `raw` row
    try {
        $dataVal = isset($row['data']) ? $row['data'] : '';
        $getVal = isset($row['get']) ? $row['get'] : '';
        $postVal = isset($row['post']) ? $row['post'] : '';
        $headersVal = isset($row['headers']) ? $row['headers'] : '';
        $ipVal = isset($row['ip_address']) ? $row['ip_address'] : '';
        $createdAt = isset($row['created_at']) ? $row['created_at'] : null;

        $procSql = "INSERT INTO processed
            (raw_id, data, `get`, `post`, headers, ip_address, status, processed_at, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";

        executeSQL($procSql, [isset($row['id']) ? (string)$row['id'] : null, $dataVal, $getVal, $postVal, $headersVal, $ipVal, 'processed', $createdAt]);
    } catch (Exception $e) {
        error_log('process_one: failed to insert into processed for raw id=' . ($row['id'] ?? '') . ' error=' . $e->getMessage());
    }

    try {
        $deleteSql = 'DELETE FROM raw WHERE id = ?';
        executeSQL($deleteSql, [ (string)$row['id'] ]);
    } catch (Exception $e) {
        // deletion failed — log but still return success for processing
        error_log('process_one: failed to delete raw id=' . ($row['id'] ?? '') . ' error=' . $e->getMessage());
    }
    echo json_encode($result);
    exit(0);
} else {
    // processing failed — insert into process_error
    try {
        $errSql = "INSERT INTO process_error
            (raw_id, data, `get`, `post`, headers, ip_address, `error`, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $dataVal = isset($row['data']) ? $row['data'] : '';
        $getVal = isset($row['get']) ? $row['get'] : '';
        $postVal = isset($row['post']) ? $row['post'] : '';
        $headersVal = isset($row['headers']) ? $row['headers'] : '';
        $ipVal = isset($row['ip_address']) ? $row['ip_address'] : '';
        $errMsg = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'processing_failed');

        executeSQL($errSql, [isset($row['id']) ? (string)$row['id'] : null, $dataVal, $getVal, $postVal, $headersVal, $ipVal, $errMsg]);
    } catch (Exception $e) {
        error_log('process_one: failed to insert process_error for raw id=' . ($row['id'] ?? '') . ' error=' . $e->getMessage());
    }

    echo json_encode($result);
    exit(1);
}
