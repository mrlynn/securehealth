<?php
// Test script to debug appointment update issues
header('Content-Type: application/json');

// Get the appointment ID from query parameter
$appointmentId = $_GET['id'] ?? '68e26430d3d98d51d4089b4e';

// Test data
$testData = [
    'patientId' => '68e1b6b1499ced6b89078765',
    'scheduledAt' => '2025-01-15T10:00:00',
    'notes' => 'Test update from PHP script'
];

echo json_encode([
    'appointmentId' => $appointmentId,
    'testData' => $testData,
    'message' => 'Use this data to test the appointment update API',
    'curl_command' => "curl -X PUT 'http://localhost:8081/api/appointments/{$appointmentId}' -H 'Content-Type: application/json' -d '" . json_encode($testData) . "'"
]);
?>
