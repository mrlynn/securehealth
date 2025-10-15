<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Read the demo patients JSON file
$jsonFile = __DIR__ . '/demo_patients.json';
if (file_exists($jsonFile)) {
    echo file_get_contents($jsonFile);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Demo data not found']);
}
?>
