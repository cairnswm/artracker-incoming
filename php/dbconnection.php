<?php


global $conn;

// Load dbconfig from same directory as this file. If not present, fall back to
// environment variables so CLI invocations work regardless of working dir.
$cfgPath = __DIR__ . '/dbconfig.php';
if (file_exists($cfgPath)) {
    require_once $cfgPath;
}

if (!isset($dbconfig) || !is_array($dbconfig)) {
    $dbconfig = [
        'db_host' => getenv('DB_HOST') ?: getenv('DB_SERVER') ?: '127.0.0.1',
        'db_user' => getenv('DB_USER') ?: 'root',
        'db_pass' => getenv('DB_PASS') ?: '',
        'db_name' => getenv('DB_NAME') ?: '',
    ];
}

function getConnection() {
    global $conn;
    
    if (!isset($conn)) {
        global $dbconfig;
        $conn = new mysqli($dbconfig["db_host"], $dbconfig["db_user"], $dbconfig["db_pass"], $dbconfig["db_name"]);
        
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
