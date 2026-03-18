<?php

require_once __DIR__ . '/../dbconnection.php';
require_once __DIR__ . '/../process.php';

function batch_get_param(array $params, $key, $default = null)
{
    return array_key_exists($key, $params) ? $params[$key] : $default;
}

function batch_job_process_hundred(array $params = [])
{
    $conn = getConnection();
    $limit = (int)batch_get_param($params, 'limit', 500);
    if ($limit <= 0) $limit = 500;

    // Fetch rows
    $stmt = executeSQL('SELECT * FROM raw ORDER BY id DESC LIMIT ?', [(string)$limit]);
    $rows = [];
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    } else {
        $meta = $stmt->result_metadata();
        $bindFields = [];
        $rowData = [];
        while ($field = $meta->fetch_field()) {
            $rowData[$field->name] = null;
            $bindFields[] = &$rowData[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $bindFields);
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($rowData as $k => $v) $copy[$k] = $v;
            $rows[] = $copy;
        }
    }
    $stmt->close();

    $startAll = microtime(true);
    $results = [];
    $parsed_rows = [];

    // parse helper (inlined)
    $parse = function(array $raw) {
        $raw_message = $raw['data'] ?? '';
        $parsed = json_decode($raw_message, true);
        if (!is_array($parsed)) {
            parse_str($raw_message, $qs);
            if (!empty($qs)) {
                $parsed = $qs;
            } else {
                $parsed = [];
                if (!empty($raw['post'])) {
                    $try = json_decode($raw['post'], true);
                    if (is_array($try)) $parsed = $try;
                    else { parse_str($raw['post'], $tmp); if (!empty($tmp)) $parsed = $tmp; }
                }
                if (empty($parsed) && !empty($raw['get'])) {
                    $try = json_decode($raw['get'], true);
                    if (is_array($try)) $parsed = $try;
                    else { parse_str($raw['get'], $tmp2); if (!empty($tmp2)) $parsed = $tmp2; }
                }
            }
        }

        $imei = isset($parsed['imei']) ? substr((string)$parsed['imei'], 0, 64) : (isset($parsed['id']) ? (string)$parsed['id'] : null);
        $serial = isset($parsed['serial']) ? substr((string)$parsed['serial'], 0, 64) : null;
        $object_name = $parsed['object_name'] ?? $parsed['objectName'] ?? $parsed['object'] ?? $parsed['device'] ?? 'unknown';
        $object_desc = $parsed['object_desc'] ?? $parsed['objectDesc'] ?? null;
        $object_groups = null;
        if (isset($parsed['object_groups'])) $object_groups = is_array($parsed['object_groups']) ? json_encode($parsed['object_groups']) : (string)$parsed['object_groups'];

        $event_at = $parsed['event_at'] ?? $parsed['timestamp'] ?? $parsed['time'] ?? $parsed['dateEvent'] ?? $parsed['dateevent'] ?? $parsed['date_event'] ?? null;
        if ($event_at !== null) {
            if (is_numeric($event_at)) {
                $ts = (int)$event_at;
                $event_at = date('Y-m-d H:i:s', $ts > 1000000000000 ? (int)($ts/1000) : $ts);
            } else {
                $t = strtotime($event_at);
                $event_at = $t !== false ? date('Y-m-d H:i:s', $t) : date('Y-m-d H:i:s');
            }
        } else {
            $event_at = date('Y-m-d H:i:s');
        }

        $latitude = isset($parsed['latitude']) ? (float)$parsed['latitude'] : (float)($parsed['lat'] ?? 0.0);
        $longitude = isset($parsed['longitude']) ? (float)$parsed['longitude'] : (float)($parsed['lon'] ?? 0.0);
        $speed = isset($parsed['speed']) ? (float)$parsed['speed'] : 0.0;
        $altitude = isset($parsed['altitude']) ? (int)$parsed['altitude'] : (isset($parsed['alt']) ? (int)$parsed['alt'] : null);
        $direction = isset($parsed['direction']) ? (int)$parsed['direction'] : null;
        $started = isset($parsed['started']) ? (int)(bool)$parsed['started'] : 0;
        $hardware = $parsed['hardware'] ?? null;
        $hw_signal_level = isset($parsed['hw_signal_level']) ? (int)$parsed['hw_signal_level'] : (isset($parsed['signal']) ? (int)$parsed['signal'] : null);
        $hw_message_type = isset($parsed['hw_message_type']) ? (int)$parsed['hw_message_type'] : null;
        $hw_tower = $parsed['hw_tower'] ?? null;
        $hw_altitude = isset($parsed['hw_altitude']) ? (int)$parsed['hw_altitude'] : null;

        return [
            'serial' => $serial,
            'imei' => $imei,
            'object_name' => $object_name,
            'object_desc' => $object_desc,
            'object_groups' => $object_groups,
            'event_at' => $event_at,
            'latitude' => (string)$latitude,
            'longitude' => (string)$longitude,
            'speed' => (string)$speed,
            'altitude' => $altitude === null ? null : (string)$altitude,
            'direction' => $direction === null ? null : (string)$direction,
            'started' => (string)$started,
            'hardware' => $hardware,
            'hw_signal_level' => $hw_signal_level === null ? null : (string)$hw_signal_level,
            'hw_message_type' => $hw_message_type === null ? null : (string)$hw_message_type,
            'hw_tower' => $hw_tower,
            'hw_altitude' => $hw_altitude === null ? null : (string)$hw_altitude,
            'raw_message' => $raw_message,
        ];
    };

    foreach ($rows as $raw) {
        $p = $parse($raw);
        if ($p === null) {
            $results[$raw['id'] ?? null] = ['success' => false, 'error' => 'parse_failed'];
            continue;
        }
        $results[$raw['id'] ?? null] = ['success' => true];
        $parsed_rows[] = ['raw' => $raw, 'parsed' => $p];
    }

    // If none parsed, return early
    if (empty($parsed_rows)) {
        return ['success' => true, 'fetched' => count($rows), 'processed' => 0, 'errors' => count($rows), 'duration_ms' => round((microtime(true)-$startAll)*1000,3)];
    }

    // Pre-fetch device_latest for devices in this batch
    $devicePairs = [];
    foreach ($parsed_rows as $e) {
        $p = $e['parsed'];
        if (!empty($p['serial']) && !empty($p['imei'])) $devicePairs[$p['serial'].'|'.$p['imei']] = true;
    }
    $existing_latest = [];
    if (!empty($devicePairs)) {
        $whereClauses = array_fill(0, count($devicePairs), '(serial = ? AND imei = ?)');
        $whereParams = [];
        foreach (array_keys($devicePairs) as $key) {
            $parts = explode('|', $key, 2);
            $whereParams[] = $parts[0];
            $whereParams[] = $parts[1];
        }
        $checkSql = 'SELECT serial, imei, event_at FROM device_latest WHERE '.implode(' OR ', $whereClauses);
        $checkStmt = executeSQL($checkSql, $whereParams);
        if (method_exists($checkStmt, 'get_result')) {
            $res = $checkStmt->get_result();
            while ($r = $res->fetch_assoc()) $existing_latest[$r['serial'].'|'.$r['imei']] = $r['event_at'];
        }
        $checkStmt->close();
    }

    // Choose best candidate per device
    $bestCandidate = [];
    foreach ($parsed_rows as $e) {
        $p = $e['parsed'];
        if (empty($p['serial']) || empty($p['imei'])) continue;
        $key = $p['serial'].'|'.$p['imei'];
        if (!isset($bestCandidate[$key]) || strtotime($p['event_at']) > strtotime($bestCandidate[$key]['event_at'])) {
            $bestCandidate[$key] = $p;
        }
    }

    $dlInsertRows = [];
    $dlUpdateRows = [];
    foreach ($bestCandidate as $key => $p) {
        $existingEA = $existing_latest[$key] ?? null;
        if ($existingEA === null) $dlInsertRows[] = $p;
        elseif (strtotime($p['event_at']) > strtotime($existingEA)) $dlUpdateRows[] = $p;
    }

    // Write everything in one transaction
    $processedCount = 0;
    $errorCount = 0;
    try {
        $conn->begin_transaction();

        // bulk INSERT device_events
        $evPlaceholder = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $evPlaceholders = implode(', ', array_fill(0, count($parsed_rows), $evPlaceholder));
        $evSql = "INSERT INTO device_events (serial, imei, object_name, object_desc, object_groups, event_at, latitude, longitude, speed, altitude, direction, started, hardware, hw_signal_level, hw_message_type, hw_tower, hw_altitude, raw_message) VALUES $evPlaceholders";
        $evParams = [];
        foreach ($parsed_rows as $e) {
            $p = $e['parsed'];
            $evParams[] = $p['serial']; $evParams[] = $p['imei']; $evParams[] = $p['object_name']; $evParams[] = $p['object_desc'];
            $evParams[] = $p['object_groups']; $evParams[] = $p['event_at']; $evParams[] = $p['latitude']; $evParams[] = $p['longitude'];
            $evParams[] = $p['speed']; $evParams[] = $p['altitude']; $evParams[] = $p['direction']; $evParams[] = $p['started'];
            $evParams[] = $p['hardware']; $evParams[] = $p['hw_signal_level']; $evParams[] = $p['hw_message_type']; $evParams[] = $p['hw_tower'];
            $evParams[] = $p['hw_altitude']; $evParams[] = $p['raw_message'];
        }
        $evStmt = executeSQL($evSql, $evParams);
        $evStmt->close();

        // insert new device_latest
        if (!empty($dlInsertRows)) {
            $dlInsPlaceholder = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
            $dlInsPlaceholders = implode(', ', array_fill(0, count($dlInsertRows), $dlInsPlaceholder));
            $dlInsSql = "INSERT INTO device_latest (serial, imei, object_name, object_desc, object_groups, event_at, latitude, longitude, speed, altitude, direction, started, hardware, hw_signal_level, hw_message_type, hw_tower, hw_altitude, raw_message, created_at, modified_at) VALUES $dlInsPlaceholders";
            $dlInsParams = [];
            foreach ($dlInsertRows as $p) {
                $dlInsParams[] = $p['serial']; $dlInsParams[] = $p['imei']; $dlInsParams[] = $p['object_name']; $dlInsParams[] = $p['object_desc'];
                $dlInsParams[] = $p['object_groups']; $dlInsParams[] = $p['event_at']; $dlInsParams[] = $p['latitude']; $dlInsParams[] = $p['longitude'];
                $dlInsParams[] = $p['speed']; $dlInsParams[] = $p['altitude']; $dlInsParams[] = $p['direction']; $dlInsParams[] = $p['started'];
                $dlInsParams[] = $p['hardware']; $dlInsParams[] = $p['hw_signal_level']; $dlInsParams[] = $p['hw_message_type']; $dlInsParams[] = $p['hw_tower'];
                $dlInsParams[] = $p['hw_altitude']; $dlInsParams[] = $p['raw_message'];
            }
            $dlInsStmt = executeSQL($dlInsSql, $dlInsParams);
            $dlInsStmt->close();
        }

        // conditional updates
        if (!empty($dlUpdateRows)) {
            $dlUpdSql = "UPDATE device_latest SET object_name = ?, object_desc = ?, object_groups = ?, event_at = ?, latitude = ?, longitude = ?, speed = ?, altitude = ?, direction = ?, started = ?, hardware = ?, hw_signal_level = ?, hw_message_type = ?, hw_tower = ?, hw_altitude = ?, raw_message = ?, modified_at = NOW() WHERE serial = ? AND imei = ? AND ? > event_at";
            foreach ($dlUpdateRows as $p) {
                $paramsUpd = [
                    $p['object_name'], $p['object_desc'], $p['object_groups'], $p['event_at'],
                    $p['latitude'], $p['longitude'], $p['speed'], $p['altitude'], $p['direction'],
                    $p['started'], $p['hardware'], $p['hw_signal_level'], $p['hw_message_type'],
                    $p['hw_tower'], $p['hw_altitude'], $p['raw_message'],
                    $p['serial'], $p['imei'], $p['event_at']
                ];
                $uStmt = executeSQL($dlUpdSql, $paramsUpd);
                $uStmt->close();
            }
        }

        // processed insert
        $procPlaceholder = '(?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())';
        $procPlaceholders = implode(', ', array_fill(0, count($parsed_rows), $procPlaceholder));
        $procSql = "INSERT INTO processed (raw_id, data, `get`, `post`, headers, ip_address, status, processed_at, created_at, modified_at) VALUES $procPlaceholders";
        $procParams = [];
        foreach ($parsed_rows as $e) {
            $raw = $e['raw'];
            $procParams[] = isset($raw['id']) ? (string)$raw['id'] : null;
            $procParams[] = $raw['data'] ?? '';
            $procParams[] = $raw['get'] ?? '';
            $procParams[] = $raw['post'] ?? '';
            $procParams[] = $raw['headers'] ?? '';
            $procParams[] = $raw['ip_address'] ?? '';
            $procParams[] = 'processed';
            $procParams[] = $raw['created_at'] ?? null;
        }
        $pStmt = executeSQL($procSql, $procParams);
        $pStmt->close();

        // delete raw
        $successIds = [];
        foreach ($parsed_rows as $e) $successIds[] = (string)($e['raw']['id'] ?? '');
        if (!empty($successIds)) {
            $idPlaceholders = implode(', ', array_fill(0, count($successIds), '?'));
            $dStmt = executeSQL("DELETE FROM raw WHERE id IN ($idPlaceholders)", $successIds);
            $dStmt->close();
        }

        $conn->commit();
        $processedCount = count($parsed_rows);
    } catch (Exception $ex) {
        try { $conn->rollback(); } catch (Exception $_) {}
        error_log('batch_job_process_hundred: ' . $ex->getMessage());
        $errorCount = count($parsed_rows);
        $processedCount = 0;
    }

    return [
        'success' => true,
        'fetched' => count($rows),
        'processed' => $processedCount,
        'errors' => $errorCount,
        'duration_ms' => round((microtime(true) - $startAll) * 1000.0, 3),
    ];
}

function batch_job_process_interval_worker(array $params = [])
{
    $minutes = (int)batch_get_param($params, 'minutes_window', 10);
    $safety = (int)batch_get_param($params, 'safety_minutes', 1);
    if ($minutes <= 0) {
        $minutes = 10;
    }
    if ($safety < 0) {
        $safety = 1;
    }

    $windowEnd = date('Y-m-d H:i:s');
    $windowStart = date('Y-m-d H:i:s', time() - (($minutes + $safety) * 60));

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

    $start = microtime(true);
    $stmt = executeSQL($sql, [$windowStart, $windowEnd]);
    $affected = getConnection()->affected_rows;
    $stmt->close();

    return [
        'success' => true,
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
        'affected' => $affected,
        'duration_ms' => round((microtime(true) - $start) * 1000.0, 3),
    ];
}

function batch_job_process_interval_backfill(array $params = [])
{
    $conn = getConnection();
    $startArg = batch_get_param($params, 'start', null);
    $endArg = batch_get_param($params, 'end', null);
    $chunkSeconds = (int)batch_get_param($params, 'chunk_seconds', 86400);
    $maxChunks = (int)batch_get_param($params, 'max_chunks', 0);

    if ($chunkSeconds <= 0) {
        $chunkSeconds = 86400;
    }

    if ($startArg === null || $endArg === null) {
        $rangeRes = $conn->query('SELECT MIN(event_at) AS mn, MAX(event_at) AS mx FROM device_events');
        if (!$rangeRes) {
            throw new Exception('failed_select_range');
        }
        $range = $rangeRes->fetch_assoc();
        if (!$range || $range['mn'] === null || $range['mx'] === null) {
            return ['success' => true, 'message' => 'no_events'];
        }
        if ($startArg === null) {
            $startArg = $range['mn'];
        }
        if ($endArg === null) {
            $endArg = $range['mx'];
        }
    }

    $cursor = strtotime((string)$startArg);
    $endTs = strtotime((string)$endArg);
    if ($cursor === false || $endTs === false) {
        throw new Exception('invalid_backfill_range');
    }

    $totalAffected = 0;
    $chunks = 0;
    $runStart = microtime(true);

    while ($cursor <= $endTs) {
        if ($maxChunks > 0 && $chunks >= $maxChunks) {
            break;
        }

        $chunkStart = date('Y-m-d H:i:s', $cursor);
        $cursor += $chunkSeconds;
        $chunkEnd = date('Y-m-d H:i:s', min($cursor, $endTs));

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
            points_count = VALUES(points_count),
            min_speed = LEAST(COALESCE(min_speed, VALUES(min_speed)), VALUES(min_speed)),
            max_speed = GREATEST(COALESCE(max_speed, VALUES(max_speed)), VALUES(max_speed)),
            last_event_at = GREATEST(COALESCE(last_event_at, '1970-01-01'), VALUES(last_event_at)),
            last_latitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_latitude), last_latitude),
            last_longitude = IF(VALUES(last_event_at) > last_event_at, VALUES(last_longitude), last_longitude),
            raw_message = VALUES(raw_message),
            modified_at = NOW();";

        $stmt = executeSQL($sql, [$chunkStart, $chunkEnd]);
        $totalAffected += getConnection()->affected_rows;
        $stmt->close();
        $chunks++;
    }

    return [
        'success' => true,
        'start' => (string)$startArg,
        'end' => (string)$endArg,
        'chunks_processed' => $chunks,
        'total_affected' => $totalAffected,
        'duration_ms' => round((microtime(true) - $runStart) * 1000.0, 3),
    ];
}

function batch_job_process_race(array $params = [])
{
    $eventId = (int)batch_get_param($params, 'event_id', 1);
    if ($eventId <= 0) {
        $eventId = 1;
    }

    $raceFile = __DIR__ . '/../race_function.php';
    if (is_file($raceFile)) {
        require_once $raceFile;
    }
    if (!function_exists('create_or_update_race')) {
        throw new Exception('create_or_update_race_not_found');
    }

    return create_or_update_race($eventId);
}

function batch_job_update_team_track(array $params = [])
{
    $requestedTeamId = (int)batch_get_param($params, 'team_id', 0);
    $start = batch_get_param($params, 'start', null);
    $end = batch_get_param($params, 'end', null);

    // helper to read stmt rows (compatible with mysqlnd or not)
    $stmt_rows = function($stmt) {
        $rows = [];
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            return $rows;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return $rows;
        $row = [];
        $binds = [];
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $binds[] = &$row[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $binds);
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($row as $k => $v) $copy[$k] = $v;
            $rows[] = $copy;
        }
        return $rows;
    };

    // determine team ids to process
    $teamIds = [];
    if ($requestedTeamId > 0) {
        $teamIds[] = $requestedTeamId;
    } else {
        $tstmt = executeSQL('SELECT id FROM teams');
        $trows = $stmt_rows($tstmt);
        $tstmt->close();
        foreach ($trows as $tr) $teamIds[] = (int)$tr['id'];
    }

    $results = [];
    foreach ($teamIds as $teamId) {
        // Get devices for team
        $devStmt = executeSQL('SELECT d.serial, d.imei FROM team_device td JOIN device d ON d.id = td.device_id WHERE td.team_id = ?', [(string)$teamId]);
        $devices = $stmt_rows($devStmt);
        $devStmt->close();

        $trackPoints = [];
        foreach ($devices as $dev) {
            $serial = $dev['serial'];
            $imei = $dev['imei'];

            if ($start === null && $end === null) {
                $sql = 'SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? ORDER BY interval_start ASC';
                $paramsQ = [$serial, $imei];
            } elseif ($start !== null && $end === null) {
                $sql = 'SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start >= ? ORDER BY interval_start ASC';
                $paramsQ = [$serial, $imei, $start];
            } elseif ($start === null && $end !== null) {
                $sql = 'SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start <= ? ORDER BY interval_start ASC';
                $paramsQ = [$serial, $imei, $end];
            } else {
                $sql = 'SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start >= ? AND interval_start <= ? ORDER BY interval_start ASC';
                $paramsQ = [$serial, $imei, $start, $end];
            }

            $stmt = executeSQL($sql, $paramsQ);
            $intervals = $stmt_rows($stmt);
            $stmt->close();

            foreach ($intervals as $int) {
                if ($int['last_latitude'] === null || $int['last_longitude'] === null || $int['last_event_at'] === null) continue;
                $trackPoints[] = [
                    'lat' => (float)$int['last_latitude'],
                    'lng' => (float)$int['last_longitude'],
                    'timestamp' => $int['last_event_at']
                ];
            }
        }

        usort($trackPoints, function ($a, $b) {
            return strcmp($a['timestamp'], $b['timestamp']);
        });

        $trackJson = json_encode($trackPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Upsert into team_track
        $existsStmt = executeSQL('SELECT id FROM team_track WHERE team_id = ? LIMIT 1', [(string)$teamId]);
        $exists = $stmt_rows($existsStmt);
        $existsStmt->close();

        if (!empty($exists)) {
            executeSQL('UPDATE team_track SET track = ?, modified_at = CURRENT_TIMESTAMP() WHERE team_id = ?', [$trackJson, (string)$teamId])->close();
            $results[] = ['team_id' => $teamId, 'action' => 'updated', 'points' => count($trackPoints)];
        } else {
            executeSQL('INSERT INTO team_track (team_id, track, created_at, modified_at) VALUES (?, ?, NOW(), NOW())', [(string)$teamId, $trackJson])->close();
            $results[] = ['team_id' => $teamId, 'action' => 'inserted', 'points' => count($trackPoints)];
        }
    }

    return ['success' => true, 'processed' => count($teamIds), 'details' => $results];
}
