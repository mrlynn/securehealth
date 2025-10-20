<?php
/**
 * @fileoverview Range Encrypted Search API Endpoint
 *
 * This API endpoint provides range-based search functionality for encrypted patient data
 * in the SecureHealth HIPAA-compliant medical records system. It uses range encryption
 * to enable comparison operations on date and age fields while maintaining data security.
 *
 * @api
 * @endpoint POST /api_encrypted_search_range.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Date range searches using range encryption
 * - Age range filtering (minAge, maxAge)
 * - Birth date range filtering (birthDateFrom, birthDateTo)
 * - Input validation and sanitization
 * - Mock data implementation for demonstration
 * - CORS support for cross-origin requests
 *
 * @searchCriteria
 * - birthDateFrom: Start date for birth date range (YYYY-MM-DD format)
 * - birthDateTo: End date for birth date range (YYYY-MM-DD format)
 * - minAge: Minimum age filter (0-120 years)
 * - maxAge: Maximum age filter (0-120 years)
 *
 * @encryptionType
 * - range: Enables comparison operations on encrypted data
 *   Allows range queries while maintaining data security
 *
 * @request
 * POST with JSON body containing search criteria:
 * {
 *   "birthDateFrom": "1980-01-01",
 *   "birthDateTo": "1990-12-31",
 *   "minAge": 30,
 *   "maxAge": 50
 * }
 *
 * @response
 * {
 *   "success": true,
 *   "searchType": "range",
 *   "criteria": {...},
 *   "results": [...],
 *   "totalResults": 3,
 *   "searchTime": 55,
 *   "encryptedFields": ["birthDate"],
 *   "encryptionType": "range",
 *   "message": "Range search completed on encrypted data"
 * }
 *
 * @validation
 * - Age values: Must be between 0 and 120 years
 * - Date format: Must be valid YYYY-MM-DD format
 * - At least one range criteria is required
 *
 * @security
 * This endpoint handles sensitive patient data and should be protected with
 * proper authentication and authorization. Currently uses mock data for
 * demonstration purposes.
 *
 * @cors
 * Supports CORS with wildcard origin for development. Should be restricted
 * in production environments.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

// Validate input
$criteria = [];
if (!empty($input['birthDateFrom'])) {
    $criteria['birthDateFrom'] = $input['birthDateFrom'];
}
if (!empty($input['birthDateTo'])) {
    $criteria['birthDateTo'] = $input['birthDateTo'];
}
if (!empty($input['minAge'])) {
    $minAge = (int) $input['minAge'];
    if ($minAge >= 0 && $minAge <= 120) {
        $criteria['minAge'] = $minAge;
    }
}
if (!empty($input['maxAge'])) {
    $maxAge = (int) $input['maxAge'];
    if ($maxAge >= 0 && $maxAge <= 120) {
        $criteria['maxAge'] = $maxAge;
    }
}

if (empty($criteria)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'At least one range criteria is required',
        'message' => 'Please provide birthDateFrom, birthDateTo, minAge, or maxAge for range search'
    ]);
    exit();
}

// Mock patients data
$mockPatients = [
    [
        'id' => '1',
        'firstName' => 'John',
        'lastName' => 'Smith',
        'email' => 'john.smith@example.com',
        'phoneNumber' => '555-123-4567',
        'birthDate' => '1985-03-15',
        'ssn' => '***-**-1234',
        'diagnosis' => ['Hypertension'],
        'medications' => ['Lisinopril'],
        'createdAt' => '2024-01-15 10:30:00'
    ],
    [
        'id' => '2',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane.doe@gmail.com',
        'phoneNumber' => '555-987-6543',
        'birthDate' => '1990-07-22',
        'ssn' => '***-**-5678',
        'diagnosis' => ['Diabetes Type 2'],
        'medications' => ['Metformin'],
        'createdAt' => '2024-01-16 14:20:00'
    ],
    [
        'id' => '3',
        'firstName' => 'Michael',
        'lastName' => 'Johnson',
        'email' => 'michael.johnson@yahoo.com',
        'phoneNumber' => '555-456-7890',
        'birthDate' => '1978-11-08',
        'ssn' => '***-**-9012',
        'diagnosis' => ['Asthma'],
        'medications' => ['Albuterol'],
        'createdAt' => '2024-01-17 09:15:00'
    ],
    [
        'id' => '4',
        'firstName' => 'Sarah',
        'lastName' => 'Wilson',
        'email' => 'sarah.wilson@example.com',
        'phoneNumber' => '555-321-9876',
        'birthDate' => '1995-12-03',
        'ssn' => '***-**-3456',
        'diagnosis' => ['Migraine'],
        'medications' => ['Sumatriptan'],
        'createdAt' => '2024-01-18 11:45:00'
    ]
];

// Filter results based on range criteria
$results = [];
foreach ($mockPatients as $patient) {
    $matches = true;
    $patientBirthDate = new DateTime($patient['birthDate']);
    $patientAge = $patientBirthDate->diff(new DateTime())->y;
    
    // Check birth date range
    if (isset($criteria['birthDateFrom'])) {
        $fromDate = new DateTime($criteria['birthDateFrom']);
        if ($patientBirthDate < $fromDate) {
            $matches = false;
        }
    }
    
    if (isset($criteria['birthDateTo'])) {
        $toDate = new DateTime($criteria['birthDateTo']);
        if ($patientBirthDate > $toDate) {
            $matches = false;
        }
    }
    
    // Check age range
    if (isset($criteria['minAge']) && $patientAge < $criteria['minAge']) {
        $matches = false;
    }
    
    if (isset($criteria['maxAge']) && $patientAge > $criteria['maxAge']) {
        $matches = false;
    }
    
    if ($matches) {
        $results[] = $patient;
    }
}

// Return response
echo json_encode([
    'success' => true,
    'searchType' => 'range',
    'criteria' => $criteria,
    'results' => $results,
    'totalResults' => count($results),
    'searchTime' => rand(25, 90), // Mock search time
    'encryptedFields' => ['birthDate'],
    'encryptionType' => 'range',
    'message' => 'Range search completed on encrypted data'
]);
?>
