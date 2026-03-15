<?php
require_once __DIR__ . '/../corsheaders.php';
require_once __DIR__ . '/race_function.php';

header('Content-Type: application/json; charset=utf-8');

// Accept event id via GET or default to 1
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 1;

try {
    $result = create_or_update_race($eventId);
    if (isset($result['ok']) && $result['ok']) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        // Not found or other handled error
        $code = isset($result['error']) ? 500 : 404;
        http_response_code($code);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

?>
