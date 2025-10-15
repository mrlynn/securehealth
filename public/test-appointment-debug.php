<?php
// Debug script to test appointment update step by step
header('Content-Type: application/json');

$appointmentId = '68e26430d3d98d51d4089b4e';
$patientId = '68e1b6b1499ced6b89078765';

echo json_encode([
    'message' => 'Appointment update debug test',
    'appointmentId' => $appointmentId,
    'patientId' => $patientId,
    'testData' => [
        'patientId' => $patientId,
        'scheduledAt' => '2025-01-15T10:00:00',
        'notes' => 'Test update'
    ],
    'curlTest' => "curl -X PUT 'http://localhost:8081/api/appointments/{$appointmentId}' -H 'Content-Type: application/json' -H 'Cookie: PHPSESSID=0b6d410006b8a42fb6b6116c77733f0f' -d '{\"patientId\": \"{$patientId}\", \"scheduledAt\": \"2025-01-15T10:00:00\", \"notes\": \"Test update\"}'"
]);
?>
