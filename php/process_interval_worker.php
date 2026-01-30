<?php
/**
 * php/process_interval_worker.php
 *
 * Incremental worker to aggregate recent `device_events` into `device_interval`.
 * By default it processes the last 10 minutes with a small safety window to catch late arrivals.
 * Usage: php process_interval_worker.php [minutes_window]
 */

require_once __DIR__ . '/dbconnection.php';

function nowMs() { return round(microtime(true) * 1000, 3); }

try {
    $conn = getConnection();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit(1);
}

$argv = $_SERVER['argv'];
$minutes = isset($argv[1]) ? (int)$argv[1] : 10;
$safety = 1; // extra minutes to include late arrivals

$windowEnd = date('Y-m-d H:i:s');
$windowStart = date('Y-m-d H:i:s', time() - ($minutes + $safety) * 60);

$sql = "INSERT INTO device_interval
  (serial, imei, interval_start, interval_end, points_count, min_speed, max_speed, last_event_at, last_latitude, last_longitude, raw_message, created_at, modified_at)
SELECT
  e.serial,
  e.imei,
  FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(e.event_at) / 150) * 150),
  FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(e.event_at) / 150) * 150) + INTERVAL 150 SECOND,
  COUNT(*) AS points_count,
  MIN(CAST(e.speed AS DECIMAL(8,3))) AS min_speed,
  MAX(CAST(e.speed AS DECIMAL(8,3))) AS max_speed,
  MAX(e.event_at) AS last_event_at,
  SUBSTRING_INDEX(GROUP_CONCAT(e.latitude ORDER BY e.event_at DESC SEPARATOR ','), ',', 1) + 0 AS last_latitude,
  SUBSTRING_INDEX(GROUP_CONCAT(e.longitude ORDER BY e.event_at DESC SEPARATOR ','), ',', 1) + 0 AS last_longitude,
  '' as raw_message,
  NOW(), NOW()
FROM device_events e
WHERE e.event_at >= ? AND e.event_at <= ?
GROUP BY e.serial, e.imei, FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(e.event_at) / 150) * 150)
ON DUPLICATE KEY UPDATE
  points_count = points_count + VALUES(points_count),
  min_speed = LEAST(COALESCE(min_speed, VALUES(min_speed)), VALUES(min_speed)),
  max_speed = GREATEST(COALESCE(max_speed, VALUES(max_speed)), VALUES(max_speed)),
  last_event_at = GREATEST(COALESCE(last_event_at, '1970-01-01'), VALUES(last_event_at)),
  last_latitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_latitude), last_latitude),
  last_longitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_longitude), last_longitude),
  raw_message = VALUES(raw_message),
  modified_at = NOW();";

$startMs = nowMs();
try {
    $stmt = executeSQL($sql, [$windowStart, $windowEnd]);
    $affected = getConnection()->affected_rows;
    $stmt->close();
    $dur = nowMs() - $startMs;
    echo json_encode(['success' => true, 'window_start' => $windowStart, 'window_end' => $windowEnd, 'affected' => $affected, 'duration_ms' => round($dur,3)]) . PHP_EOL;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]) . PHP_EOL;
    exit(1);
}
