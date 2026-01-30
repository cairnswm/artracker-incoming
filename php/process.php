<?php
/**
 * php/process.php
 *
 * Provides process_point(array $raw_row) which accepts one row from `raw` table
 * and inserts a parsed record into `device_events`.
 */

require_once __DIR__ . '/dbconnection.php';
if (file_exists(__DIR__ . '/utils.php')) {
    require_once __DIR__ . '/utils.php';
}

function process_point(array $raw_row)
{
    try {
        $conn = getConnection();
    } catch (Throwable $e) {
        error_log('process_point: cannot get DB connection: ' . $e->getMessage());
        return ['success' => false, 'error' => 'db_connection_failed'];
    }

    $raw_message = $raw_row['data'] ?? '';

    $parsed = json_decode($raw_message, true);
    if (!is_array($parsed)) {
        parse_str($raw_message, $qs);
        if (!empty($qs)) {
            $parsed = $qs;
        } else {
            $parsed = [];
            if (!empty($raw_row['post'])) {
                $try = json_decode($raw_row['post'], true);
                if (is_array($try)) {
                    $parsed = $try;
                } else {
                    parse_str($raw_row['post'], $tmp);
                    if (!empty($tmp)) $parsed = $tmp;
                }
            }
            if (empty($parsed) && !empty($raw_row['get'])) {
                $try = json_decode($raw_row['get'], true);
                if (is_array($try)) {
                    $parsed = $try;
                } else {
                    parse_str($raw_row['get'], $tmp2);
                    if (!empty($tmp2)) $parsed = $tmp2;
                }
            }
        }
    }

    $imei = isset($parsed['imei']) ? substr((string)$parsed['imei'], 0, 64) : (isset($parsed['id']) ? (string)$parsed['id'] : null);
    $serial = isset($parsed['serial']) ? substr((string)$parsed['serial'], 0, 64) : null;
    $object_name = $parsed['object_name'] ?? $parsed['objectName'] ?? $parsed['object'] ?? $parsed['device'] ?? 'unknown';
    $object_desc = $parsed['object_desc'] ?? $parsed['objectDesc'] ?? null;
    $object_groups = null;
    if (isset($parsed['object_groups'])) {
        $object_groups = is_array($parsed['object_groups']) ? json_encode($parsed['object_groups']) : (string)$parsed['object_groups'];
    }

    $event_at = $parsed['event_at'] ?? $parsed['timestamp'] ?? $parsed['time'] ?? $parsed['dateEvent'] ?? $parsed['dateevent'] ?? $parsed['date_event'] ?? null;
    if ($event_at !== null) {
        if (is_numeric($event_at)) {
            $ts = (int)$event_at;
            if ($ts > 1000000000000) {
                $event_at = date('Y-m-d H:i:s', (int)($ts / 1000));
            } else {
                $event_at = date('Y-m-d H:i:s', $ts);
            }
        } else {
            $t = strtotime($event_at);
            $event_at = $t !== false ? date('Y-m-d H:i:s', $t) : date('Y-m-d H:i:s');
        }
    } else {
        $event_at = date('Y-m-d H:i:s');
    }

    $latitude = isset($parsed['latitude']) ? (float)$parsed['latitude'] : (isset($parsed['lat']) ? (float)$parsed['lat'] : 0.0);
    $longitude = isset($parsed['longitude']) ? (float)$parsed['longitude'] : (isset($parsed['lon']) ? (float)$parsed['lon'] : 0.0);
    $speed = isset($parsed['speed']) ? (float)$parsed['speed'] : 0.0;
    $altitude = isset($parsed['altitude']) ? (int)$parsed['altitude'] : (isset($parsed['alt']) ? (int)$parsed['alt'] : null);
    $direction = isset($parsed['direction']) ? (int)$parsed['direction'] : null;
    $started = isset($parsed['started']) ? (int)(bool)$parsed['started'] : 0;
    $hardware = $parsed['hardware'] ?? null;
    $hw_signal_level = isset($parsed['hw_signal_level']) ? (int)$parsed['hw_signal_level'] : (isset($parsed['signal']) ? (int)$parsed['signal'] : null);
    $hw_message_type = isset($parsed['hw_message_type']) ? (int)$parsed['hw_message_type'] : null;
    $hw_tower = $parsed['hw_tower'] ?? null;
    $hw_altitude = isset($parsed['hw_altitude']) ? (int)$parsed['hw_altitude'] : null;

    $sql = "INSERT INTO device_events
        (serial, imei, object_name, object_desc, object_groups, event_at, latitude, longitude, speed, altitude, direction, started, hardware, hw_signal_level, hw_message_type, hw_tower, hw_altitude, raw_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $serial,
        $imei,
        $object_name,
        $object_desc,
        $object_groups,
        $event_at,
        (string)$latitude,
        (string)$longitude,
        (string)$speed,
        $altitude === null ? null : (string)$altitude,
        $direction === null ? null : (string)$direction,
        (string)$started,
        $hardware,
        $hw_signal_level === null ? null : (string)$hw_signal_level,
        $hw_message_type === null ? null : (string)$hw_message_type,
        $hw_tower,
        $hw_altitude === null ? null : (string)$hw_altitude,
        $raw_message
    ];

    try {
        $stmt = executeSQL($sql, $params);
        $insertId = getConnection()->insert_id;

        // Update device_latest: only insert/update when serial+imei present
        // and the event_at for this point is newer than any existing event_at.
        try {
            if (!empty($serial) && !empty($imei)) {
                // Fetch existing event_at for this device
                $checkSql = 'SELECT event_at FROM device_latest WHERE serial = ? AND imei = ? LIMIT 1';
                $checkStmt = executeSQL($checkSql, [$serial, $imei]);
                $existingEventAt = null;
                if (method_exists($checkStmt, 'get_result')) {
                    $res = $checkStmt->get_result();
                    $r = $res->fetch_assoc();
                    $existingEventAt = $r['event_at'] ?? null;
                } else {
                    $checkStmt->bind_result($existingEventAt);
                    $checkStmt->fetch();
                }
                $checkStmt->close();

                $shouldUpdate = false;
                if ($existingEventAt === null) {
                    $shouldUpdate = true;
                } else {
                    $tNew = strtotime($event_at);
                    $tExisting = strtotime($existingEventAt);
                    if ($tNew !== false && $tExisting !== false && $tNew > $tExisting) {
                        $shouldUpdate = true;
                    }
                }

                if ($shouldUpdate) {
                    // If an existing row exists, update it; otherwise insert a new row
                    if ($existingEventAt === null) {
                        $insSql = "INSERT INTO device_latest
                            (serial, imei, object_name, object_desc, object_groups, event_at, latitude, longitude, speed, altitude, direction, started, hardware, hw_signal_level, hw_message_type, hw_tower, hw_altitude, raw_message, created_at, modified_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        executeSQL($insSql, [$serial, $imei, $object_name, $object_desc, $object_groups, $event_at, (string)$latitude, (string)$longitude, (string)$speed, $altitude === null ? null : (string)$altitude, $direction === null ? null : (string)$direction, (string)$started, $hardware, $hw_signal_level === null ? null : (string)$hw_signal_level, $hw_message_type === null ? null : (string)$hw_message_type, $hw_tower, $hw_altitude === null ? null : (string)$hw_altitude, $raw_message]);
                    } else {
                        $updSql = "UPDATE device_latest SET
                            object_name = ?, object_desc = ?, object_groups = ?, event_at = ?, latitude = ?, longitude = ?, speed = ?, altitude = ?, direction = ?, started = ?, hardware = ?, hw_signal_level = ?, hw_message_type = ?, hw_tower = ?, hw_altitude = ?, raw_message = ?, modified_at = NOW()
                            WHERE serial = ? AND imei = ?";
                        executeSQL($updSql, [$object_name, $object_desc, $object_groups, $event_at, (string)$latitude, (string)$longitude, (string)$speed, $altitude === null ? null : (string)$altitude, $direction === null ? null : (string)$direction, (string)$started, $hardware, $hw_signal_level === null ? null : (string)$hw_signal_level, $hw_message_type === null ? null : (string)$hw_message_type, $hw_tower, $hw_altitude === null ? null : (string)$hw_altitude, $raw_message, $serial, $imei]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('process_point: device_latest update failed: ' . $e->getMessage());
            // Non-fatal; continue
        }

        $stmt->close();
        return ['success' => true, 'inserted_id' => $insertId];
    } catch (Exception $e) {
        error_log('process_point: failed insert: ' . $e->getMessage() . ' raw_id=' . ($raw_row['id'] ?? ''));
        return ['success' => false, 'error' => 'insert_failed'];
    }
}
