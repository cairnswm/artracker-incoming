<?php
/**
 * php/process_hundred.php
 *
 * Fetches newest 100 rows from `raw` and calls process_point on each row.
 */

require_once __DIR__ . '/process.php';
require_once __DIR__ . '/dbconnection.php';

try {
    $conn = getConnection();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit(1);
}

$res = $conn->query('SELECT * FROM raw ORDER BY id DESC LIMIT 100');
if (!$res) {
    echo json_encode(['success' => false, 'error' => 'query_failed']);
    exit(1);
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

$results = [];
$totalStart = microtime(true);
foreach ($rows as $raw) {
    $id = $raw['id'] ?? null;
    $start = microtime(true);
    $res = process_point($raw);
    $durationMs = (microtime(true) - $start) * 1000.0;
    $res['duration_ms'] = round($durationMs, 3);
    $results[$id] = $res;
    // If processing succeeded, insert into `processed` and remove from `raw`
    if (!empty($res['success'])) {
        try {
            $dataVal = isset($raw['data']) ? $raw['data'] : '';
            $getVal = isset($raw['get']) ? $raw['get'] : '';
            $postVal = isset($raw['post']) ? $raw['post'] : '';
            $headersVal = isset($raw['headers']) ? $raw['headers'] : '';
            $ipVal = isset($raw['ip_address']) ? $raw['ip_address'] : '';
            $createdAt = isset($raw['created_at']) ? $raw['created_at'] : null;

            $procSql = "INSERT INTO processed
                (data, `get`, `post`, headers, ip_address, status, processed_at, created_at, modified_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";

            executeSQL($procSql, [$dataVal, $getVal, $postVal, $headersVal, $ipVal, 'processed', $createdAt]);
        } catch (Exception $e) {
            error_log('process_hundred: failed to insert into processed for raw id=' . ($id ?? '') . ' error=' . $e->getMessage());
        }

        try {
            $deleteSql = 'DELETE FROM raw WHERE id = ?';
            executeSQL($deleteSql, [ (string)$id ]);
        } catch (Exception $e) {
            error_log('process_hundred: failed to delete raw id=' . ($id ?? '') . ' error=' . $e->getMessage());
        }
    }
}
$totalMs = (microtime(true) - $totalStart) * 1000.0;

echo json_encode(['success' => true, 'results' => $results, 'total_duration_ms' => round($totalMs, 3)]);
