<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;

header('Content-Type: application/json');

// Read id
$patientId = $_GET['id'] ?? null;
if (!$patientId) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Patient ID is required']);
    exit;
}

try {
    // Connection params
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new RuntimeException('MongoDB connection string missing. Set MONGODB_URI in the environment.');
    }
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';

    $client = new Client($mongoUri);
    $collection = $client->selectDatabase($dbName)->selectCollection('patients');

    // Validate ObjectId
    try {
        $objectId = new ObjectId($patientId);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid patient ID format']);
        exit;
    }

    // Fetch raw BSON document
    $doc = $collection->findOne(['_id' => $objectId]);
    if (!$doc) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => 'Patient not found']);
        exit;
    }

    // Convert BSON document to array and return as JSON
    // This will show the raw structure including encrypted fields as Binary data
    $array = iterator_to_array($doc);
    echo json_encode($array, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}


