<?php
require_once __DIR__ . '/dbconnection.php';
$res = getConnection()->query("SELECT id,batch_job_id,status,scheduled_at,started_at,finished_at,duration_ms,worker_pid,error_message,LEFT(output,1000) AS output FROM batch_job_runs ORDER BY id DESC LIMIT 20");
if (!$res) { echo json_encode(['error' => 'query_failed', 'sql_error' => getConnection()->error]); exit(1); }
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
