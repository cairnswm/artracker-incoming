<?php
/**
 * php/process_interval_backfill.php
 *
 * Backfills `device_interval` table from `device_events` over a historical range.
 * Usage: php process_interval_backfill.php [start] [end]
 * start/end: optional ISO datetime (e.g. '2026-01-01 00:00:00'). If omitted, scans full range in day-sized chunks.
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
$startArg = $argv[1] ?? null;
$endArg = $argv[2] ?? null;

// Determine min/max range if not provided
if ($startArg === null || $endArg === null) {
    $r = $conn->query('SELECT MIN(event_at) AS mn, MAX(event_at) AS mx FROM device_events');
    if ($r) {
        $row = $r->fetch_assoc();
        $mn = $row['mn'];
        $mx = $row['mx'];
    } else {
        echo json_encode(['success' => false, 'error' => 'failed_select_range']);
        exit(1);
    }
    if ($mn === null || $mx === null) {
        echo json_encode(['success' => true, 'message' => 'no_events']);
        exit(0);
    }
    if ($startArg === null) $startArg = $mn;
    if ($endArg === null) $endArg = $mx;
}

$chunkSeconds = 24 * 3600; // 1 day chunks
$cursor = strtotime($startArg);
$endTs = strtotime($endArg);

$totalInserted = 0;
$totalAffected = 0;
$totalMsStart = nowMs();

while ($cursor <= $endTs) {
    $chunkStart = date('Y-m-d H:i:s', $cursor);
    $cursor += $chunkSeconds;
    $chunkEnd = date('Y-m-d H:i:s', min($cursor, $endTs));

    // Aggregate and upsert intervals for this chunk
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
        WHERE e.event_at >= ? AND e.event_at < ?
        GROUP BY e.serial, e.imei, FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(e.event_at) / 150) * 150)
        ON DUPLICATE KEY UPDATE
            -- Replace points_count with the aggregated value for this interval (avoid double-counting on re-run)
            points_count = VALUES(points_count),
            min_speed = LEAST(COALESCE(min_speed, VALUES(min_speed)), VALUES(min_speed)),
            max_speed = GREATEST(COALESCE(max_speed, VALUES(max_speed)), VALUES(max_speed)),
            last_event_at = GREATEST(COALESCE(last_event_at, '1970-01-01'), VALUES(last_event_at)),
            last_latitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_latitude), last_latitude),
            last_longitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_longitude), last_longitude),
            raw_message = VALUES(raw_message),
            modified_at = NOW();";

    $startMs = nowMs();
    try {
        $stmt = executeSQL($sql, [$chunkStart, $chunkEnd]);
        $affected = getConnection()->affected_rows;
        $stmt->close();
        $dur = nowMs() - $startMs;
        $totalAffected += $affected;
        $totalInserted += $affected;
        echo json_encode(['success' => true, 'chunk_start' => $chunkStart, 'chunk_end' => $chunkEnd, 'affected' => $affected, 'duration_ms' => round($dur,3)]) . PHP_EOL;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'chunk_start' => $chunkStart, 'chunk_end' => $chunkEnd, 'error' => $e->getMessage()]) . PHP_EOL;
    }
}

$totalMs = nowMs() - $totalMsStart;
echo json_encode(['success' => true, 'total_affected' => $totalAffected, 'total_duration_ms' => round($totalMs,3)]) . PHP_EOL;
