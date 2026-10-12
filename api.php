<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$dataFile = __DIR__ . '/match_live_data.json';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $decoded = json_decode($rawInput, true);
        if ($decoded !== null) {
            file_put_contents($dataFile, $rawInput);
            echo json_encode(["status" => "success", "timestamp" => time()]);
            exit;
        }
    }
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

// GET Request: Return latest match data
if (file_exists($dataFile)) {
    echo file_get_contents($dataFile);
} else {
    echo json_encode(["status" => "empty", "message" => "No active match data"]);
}
?>
