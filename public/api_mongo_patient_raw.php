<?php
/**
 * @fileoverview MongoDB Raw Patient Data API Endpoint
 *
 * This API endpoint retrieves raw patient data from MongoDB without decryption
 * for the SecureHealth HIPAA-compliant medical records system. It returns the
 * encrypted BSON document structure for debugging and analysis purposes.
 *
 * @api
 * @endpoint GET /api_mongo_patient_raw.php?id={patientId}
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Retrieves raw encrypted patient data from MongoDB
 * - Returns BSON document structure
 * - Shows encrypted fields as Binary data
 * - Useful for debugging encryption implementation
 * - Validates ObjectId format
 *
 * @parameters
 * - id: MongoDB ObjectId of the patient (required)
 *
 * @response
 * Returns raw BSON document with encrypted fields visible as Binary data:
 * {
 *   "_id": {"$oid": "68dbf20ae69980a1de028e22"},
 *   "firstName": "encrypted_binary_data",
 *   "lastName": "encrypted_binary_data",
 *   "ssn": "encrypted_binary_data",
 *   "diagnosis": "encrypted_binary_data",
 *   // ... other encrypted fields
 * }
 *
 * @useCases
 * - Debugging encryption implementation
 * - Verifying data storage structure
 * - Analyzing encrypted field formats
 * - Development and testing purposes
 * - Security audit verification
 *
 * @security
 * - Returns raw encrypted data - should be used carefully
 * - Requires proper authentication in production
 * - Useful for debugging but not for normal operations
 * - Shows actual encrypted field structure
 *
 * @validation
 * - Validates ObjectId format
 * - Returns 400 error for invalid ID format
 * - Returns 404 error if patient not found
 * - Handles MongoDB connection errors
 *
 * @dependencies
 * - MongoDB PHP Driver
 * - MongoDB\Client
 * - MongoDB\BSON\ObjectId
 */

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


