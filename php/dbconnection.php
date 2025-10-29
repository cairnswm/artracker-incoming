<?php


include_once "./dbconfig.php";
global $conn;

function getConnection() {
    global $conn;
    
    if (!isset($conn)) {
        global $dbconfig;
        $conn = new mysqli($dbconfig["server"], $dbconfig["username"], $dbconfig["password"], $dbconfig["database"]);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
    }
    
    return $conn;
}

function executeSQL($sql, $params = []) {
    $conn = getConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    return $stmt;
}

function logError($runId, $nodeId, $message, $code = null) {
    $sql = "INSERT INTO workflow_errors (run_id, node_id, error_message, error_code, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = executeSQL($sql, [$runId, $nodeId, $message, $code]);
    $insertId = getConnection()->insert_id;
    $stmt->close();
    return $insertId;
}
