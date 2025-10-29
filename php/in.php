<?php

include_once("./dbconfig.php");
include_once("./dbconnection.php");
include_once("./utils.php");

$get = $_GET;
$post = $_POST;
$headers = getallheaders();
$input = file_get_contents("php://input");

try {
  $sql = "insert into raw (get, post, data, headers) values (?, ?, ?, ?)";
  $params = [
    json_encode($get),
    json_encode($post),
    $input,
    json_encode($headers)
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