<?php
require_once __DIR__ . '/dbconnection.php';
$id = isset($argv[1]) ? (int)$argv[1] : 0;
if ($id <= 0) { echo json_encode(['error'=>'missing_id']) . "\n"; exit(1); }
$res = getConnection()->query("SELECT * FROM batch_job_runs WHERE id = $id");
if (!$res) { echo json_encode(['error'=>'query_failed','sql_error'=>getConnection()->error]) . "\n"; exit(1); }
echo json_encode($res->fetch_assoc()) . "\n";
