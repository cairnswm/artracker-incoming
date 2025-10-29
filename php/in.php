<?php

include_once __DIR__ . "/dbconfig.php";
include_once __DIR__ . "/dbconnection.php";
include_once __DIR__ . "/utils.php";

$get = $_GET;
$post = $_POST;
$headers = getallheaders();
$input = file_get_contents("php://input");

// Determine client IP address, prefer X-Forwarded-For when present
$ip_address = null;
if (!empty($headers['X-Forwarded-For'])) {
  // X-Forwarded-For may contain a comma-separated list; take the first one
  $parts = explode(',', $headers['X-Forwarded-For']);
  $ip_address = trim($parts[0]);
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
  $ip_address = trim($parts[0]);
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
  $ip_address = $_SERVER['REMOTE_ADDR'];
}

try {
  $sql = "insert into raw (get, post, data, headers, ip_address) values (?, ?, ?, ?, ?)";
  $params = [
    json_encode($get),
    json_encode($post),
    $input,
    json_encode($headers),
    $ip_address
  ];

  $stmt = executeSQL($sql, $params);

  $out = [
    "status" => "success",
    "raw_id" => getConnection()->insert_id
  ];

  header('Content-Type: application/json');
  echo json_encode($out);

} catch (Exception $e) {
  http_response_code(500);
  $out = [
    "status" => "error",
    "message" => $e->getMessage()
  ];
  header('Content-Type: application/json');
  echo json_encode($out);
}