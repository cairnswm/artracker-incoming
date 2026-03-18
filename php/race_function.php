<?php
$legacyDbPath = __DIR__ . '/../gapiv2/dbconn.php';
if (is_file($legacyDbPath)) {
    require_once $legacyDbPath;
} else {
    require_once __DIR__ . '/dbconnection.php';
}

// These are functions for managing manual batch runs

function race_stmt_rows($stmt)
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

function race_query_rows($sql, $params = [])
{
    $result = executeSQL($sql, $params);
    if (is_array($result)) {
        return $result;
    }
    if ($result instanceof mysqli_stmt) {
        $rows = race_stmt_rows($result);
        $result->close();
        return $rows;
    }
    return [];
}

function race_exec($sql, $params = [])
{
    $result = executeSQL($sql, $params);
    if ($result instanceof mysqli_stmt) {
        $result->close();
    }
}

/**
 * Create or update the `race` row for a given event id.
 * Returns an associative array with result details (no direct output).
 *
 * @param int $eventId
 * @return array
 */
function create_or_update_race($eventId)
{
    $sql = <<<'SQL'
SELECT
    e.id            AS event_id,
    e.slug          AS event_slug,
    e.name          AS event_name,
    e.description,
    e.start_time,
    e.end_time,

    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', l.id,
                'slug', l.slug,
                'name', l.name,
                'short_name', l.short_name,
                'type', l.type,
                'lat', l.lat,
                'lng', l.lng,
                'images', l.images
            )
        )
        FROM locations l
        WHERE l.id IN (

            SELECT e2.race_start_location_id
            FROM events e2
            WHERE e2.id = e.id
              AND e2.race_start_location_id IS NOT NULL

            UNION

            SELECT lg.start_location_id
            FROM legs lg
            JOIN courses c ON c.id = lg.course_id
            WHERE c.event_id = e.id
              AND lg.start_location_id IS NOT NULL

            UNION

            SELECT lg.end_location_id
            FROM legs lg
            JOIN courses c ON c.id = lg.course_id
            WHERE c.event_id = e.id
              AND lg.end_location_id IS NOT NULL

            UNION

            SELECT lc.location_id
            FROM leg_checkpoints lc
            JOIN legs lg ON lg.id = lc.leg_id
            JOIN courses c ON c.id = lg.course_id
            WHERE c.event_id = e.id

            UNION

            SELECT tt.location_id
            FROM transition_times tt
            JOIN teams t ON t.id = tt.team_id
            WHERE t.event_id = e.id
        )
    ) AS locations,

    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', c.id,
                'slug', c.slug,
                'name', c.name,
                'border_color', c.border_color
            )
        )
        FROM categories c
    ) AS categories,

    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', c.id,
                'slug', c.slug,
                'name', c.name,
                'distance', c.distance,

                'legs', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', l.id,
                            'slug', l.slug,
                            'name', l.name,
                            'type', l.type,
                            'order_no', l.order_no,
                            'total_checkpoints', l.total_checkpoints,
                            'ends_at', l.ends_at,
                            'color', l.color,
                            'distance_text', l.distance_text,
                            'altitude_gain', l.altitude_gain,
                            'route_gpx', l.route_gpx,
                            'start_location_id', l.start_location_id,
                            'end_location_id', l.end_location_id,

                            'checkpoints', (
                                SELECT JSON_ARRAYAGG(
                                    JSON_OBJECT(
                                        'id', lc.id,
                                        'order_no', lc.order_no,
                                        'location_id', lc.location_id
                                    )
                                    ORDER BY lc.order_no
                                )
                                FROM leg_checkpoints lc
                                WHERE lc.leg_id = l.id
                            ),

                            'special_checkpoints', (
                                SELECT JSON_ARRAYAGG(
                                    JSON_OBJECT(
                                        'id', sc.id,
                                        'name', sc.name,
                                        'description', sc.description
                                    )
                                )
                                FROM leg_special_checkpoints sc
                                WHERE sc.leg_id = l.id
                            )
                        )
                        ORDER BY l.order_no
                    )
                    FROM legs l
                    WHERE l.course_id = c.id
                )
            )
        )
        FROM courses c
        WHERE c.event_id = e.id
    ) AS courses,

    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', t.id,
                'slug', t.slug,
                'name', t.name,
                'bib_number', t.bib_number,
                'withdrawn', t.withdrawn,
                'number', t.number,
                'biography', t.biography,
                'category_id', t.category_id,

                'members', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', tm.id,
                            'name', tm.name,
                            'dropped_out', tm.dropped_out
                        )
                    )
                    FROM team_members tm
                    WHERE tm.team_id = t.id
                ),

                'progress', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', tp.id,
                            'leg_id', tp.leg_id,
                            'checkpoints_collected', tp.checkpoints_collected,
                            'finish_time', tp.finish_time,
                            'transition_in', tp.transition_in,
                            'transition_out', tp.transition_out,
                            'meta', tp.meta,

                            'special_events', (
                                SELECT JSON_ARRAYAGG(
                                    JSON_OBJECT(
                                        'id', sce.id,
                                        'name', sce.name,
                                        'occurred_at', sce.occurred_at,
                                        'checkpoints_before', sce.checkpoints_before
                                    )
                                )
                                FROM special_checkpoint_events sce
                                WHERE sce.team_progress_id = tp.id
                            )
                        )
                    )
                    FROM team_progress tp
                    WHERE tp.team_id = t.id
                ),

                'transition_times', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', tt.id,
                            'name', tt.name,
                            'time', tt.time,
                            'location_id', tt.location_id
                        )
                    )
                    FROM transition_times tt
                    WHERE tt.team_id = t.id
                )
                ,
                'devices', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', d.id,
                            'serial', d.serial,
                            'imei', d.imei
                        )
                    )
                    FROM team_device td
                    JOIN device d ON d.id = td.device_id
                    WHERE td.team_id = t.id
                ),

                'latest_location', (
                    SELECT JSON_OBJECT(
                        'id', dl.id,
                        'serial', dl.serial,
                        'imei', dl.imei,
                        'object_name', dl.object_name,
                        'object_desc', dl.object_desc,
                        'event_at', dl.event_at,
                        'latitude', dl.latitude,
                        'longitude', dl.longitude,
                        'speed', dl.speed,
                        'altitude', dl.altitude,
                        'direction', dl.direction
                    )
                    FROM device_latest dl
                    JOIN device d2 ON d2.serial = dl.serial AND d2.imei = dl.imei
                    JOIN team_device td2 ON td2.device_id = d2.id
                    WHERE td2.team_id = t.id
                    ORDER BY dl.event_at DESC
                    LIMIT 1
                )
            )
        )
        FROM teams t
        WHERE t.event_id = e.id
    ) AS teams

FROM events e
WHERE e.id = ?
SQL;

    try {
        $rows = race_query_rows($sql, [$eventId]);
        if (empty($rows)) {
            return ['ok' => false, 'message' => 'Event not found', 'event_id' => $eventId];
        }

        $row = $rows[0];

        // Decode top-level JSON array fields so nested objects are proper in stored JSON
        $jsonFields = ['locations', 'categories', 'courses', 'teams'];
        foreach ($jsonFields as $f) {
            if (isset($row[$f]) && $row[$f] !== null) {
                $decoded = json_decode($row[$f], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$f] = $decoded;
                }
            }
        }

        $dataJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Upsert into race table: ensure one row per event
        $existing = race_query_rows("SELECT id FROM race WHERE event_id = ?", [$eventId]);
        if (!empty($existing)) {
            race_exec("UPDATE race SET data = ?, modified_at = CURRENT_TIMESTAMP() WHERE event_id = ?", [$dataJson, $eventId]);
            return ['ok' => true, 'action' => 'updated', 'event_id' => $eventId, 'end_time' => isset($row['end_time']) ? $row['end_time'] : null];
        } else {
            race_exec("INSERT INTO race (event_id, data) VALUES (?, ?)", [$eventId, $dataJson]);
            $insertInfo = race_query_rows("SELECT id FROM race WHERE event_id = ?", [$eventId]);
            $insertId = !empty($insertInfo) ? $insertInfo[0]['id'] : null;
            return ['ok' => true, 'action' => 'inserted', 'event_id' => $eventId, 'id' => $insertId, 'end_time' => isset($row['end_time']) ? $row['end_time'] : null];
        }
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Build and store tracks for every team in a race.
 * Accepts a `race` id (from `race` table). The function will look up the
 * associated event, fetch teams for the event, collect device intervals for
 * each team's devices, build a merged time-ordered track array and upsert
 * into the `team_track` table.
 *
 * Returns an associative summary array with processing details.
 *
 * @param int $raceId
 * @return array
 */
function get_race_tracks($raceId)
{
    try {
        // Find the race row to get the event_id
        $raceRow = race_query_rows("SELECT event_id FROM race WHERE id = ?", [$raceId]);
        if (empty($raceRow)) {
            return ['ok' => false, 'message' => 'Race not found', 'race_id' => $raceId];
        }

        $eventId = $raceRow[0]['event_id'];

        // Fetch event start/end times
        $eventRow = race_query_rows("SELECT start_time, end_time FROM events WHERE id = ?", [$eventId]);
        if (empty($eventRow)) {
            return ['ok' => false, 'message' => 'Event not found', 'event_id' => $eventId];
        }
        $eventStart = $eventRow[0]['start_time'];
        $eventEnd = isset($eventRow[0]['end_time']) ? $eventRow[0]['end_time'] : null;

        // Get all teams for the event
        $teams = race_query_rows("SELECT id FROM teams WHERE event_id = ?", [$eventId]);
        if (empty($teams)) {
            return ['ok' => true, 'message' => 'No teams for event', 'event_id' => $eventId, 'processed' => 0];
        }

        $results = [];
        foreach ($teams as $teamRow) {
            $teamId = $teamRow['id'];

            // Get devices for the team
            $devices = race_query_rows("SELECT d.serial, d.imei FROM team_device td JOIN device d ON d.id = td.device_id WHERE td.team_id = ?", [$teamId]);

            $trackPoints = [];
            foreach ($devices as $dev) {
                $serial = $dev['serial'];
                $imei = $dev['imei'];

                // Fetch device intervals (ordered by interval_start) constrained by event times
                if ($eventStart === null && $eventEnd === null) {
                    $intervals = race_query_rows("SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? ORDER BY interval_start ASC", [$serial, $imei]);
                } elseif ($eventStart !== null && $eventEnd === null) {
                    $intervals = race_query_rows("SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start >= ? ORDER BY interval_start ASC", [$serial, $imei, $eventStart]);
                } elseif ($eventStart === null && $eventEnd !== null) {
                    $intervals = race_query_rows("SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start <= ? ORDER BY interval_start ASC", [$serial, $imei, $eventEnd]);
                } else {
                    $intervals = race_query_rows("SELECT last_latitude, last_longitude, last_event_at FROM device_interval WHERE serial = ? AND imei = ? AND interval_start >= ? AND interval_start <= ? ORDER BY interval_start ASC", [$serial, $imei, $eventStart, $eventEnd]);
                }
                foreach ($intervals as $int) {
                    // Skip empty coords
                    if ($int['last_latitude'] === null || $int['last_longitude'] === null || $int['last_event_at'] === null) {
                        continue;
                    }
                    $trackPoints[] = [
                        'lat' => (float)$int['last_latitude'],
                        'lng' => (float)$int['last_longitude'],
                        'timestamp' => $int['last_event_at']
                    ];
                }
            }

            // Merge / order by timestamp
            usort($trackPoints, function ($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });

            // Persist into team_track table as JSON array
            $trackJson = json_encode($trackPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existing = race_query_rows("SELECT id FROM team_track WHERE team_id = ?", [$teamId]);
            if (!empty($existing)) {
                race_exec("UPDATE team_track SET track = ?, modified_at = CURRENT_TIMESTAMP() WHERE team_id = ?", [$trackJson, $teamId]);
                $results[] = ['team_id' => $teamId, 'action' => 'updated', 'points' => count($trackPoints)];
            } else {
                race_exec("INSERT INTO team_track (team_id, track) VALUES (?, ?)", [$teamId, $trackJson]);
                $results[] = ['team_id' => $teamId, 'action' => 'inserted', 'points' => count($trackPoints)];
            }
        }

        return ['ok' => true, 'race_id' => $raceId, 'event_id' => $eventId, 'processed' => count($teams), 'details' => $results];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

?>
