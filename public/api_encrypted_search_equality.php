<?php
/**
 * @fileoverview Equality Encrypted Search API Endpoint
 *
 * This API endpoint provides exact match search functionality for encrypted patient data
 * in the SecureHealth HIPAA-compliant medical records system. It uses deterministic
 * encryption to enable precise searches on specific patient fields.
 *
 * @api
 * @endpoint POST /api_encrypted_search_equality.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Exact match searches using deterministic encryption
 * - Support for firstName, lastName, email, and phone fields
 * - Input validation and sanitization
 * - Mock data implementation for demonstration
 * - CORS support for cross-origin requests
 *
 * @searchCriteria
 * - lastName: Exact match search (case-insensitive)
 * - firstName: Exact match search (case-insensitive)
 * - email: Exact match search with email validation
 * - phone: Exact match search with phone format validation (XXX-XXX-XXXX)
 *
 * @encryptionType
 * - deterministic: Same input always produces same encrypted output
 *   Enables exact match searches while maintaining data security
 *
 * @request
 * POST with JSON body containing search criteria:
 * {
 *   "lastName": "Smith",
 *   "firstName": "John",
 *   "email": "john.smith@example.com",
 *   "phone": "555-123-4567"
 * }
 *
 * @response
 * {
 *   "success": true,
 *   "searchType": "equality",
 *   "criteria": {...},
 *   "results": [...],
 *   "totalResults": 1,
 *   "searchTime": 35,
 *   "encryptedFields": ["lastName", "firstName", "email", "phoneNumber"],
 *   "encryptionType": "deterministic",
 *   "message": "Equality search completed on encrypted data"
 * }
 *
 * @validation
 * - Email: Must be valid email format
 * - Phone: Must match XXX-XXX-XXXX pattern
 * - At least one search criteria is required
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
if (!empty($input['lastName'])) {
    $criteria['lastName'] = trim($input['lastName']);
}
if (!empty($input['firstName'])) {
    $criteria['firstName'] = trim($input['firstName']);
}
if (!empty($input['email'])) {
    $email = trim($input['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $criteria['email'] = $email;
    }
}
if (!empty($input['phone'])) {
    $phone = trim($input['phone']);
    if (preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) {
        $criteria['phone'] = $phone;
    }
}

if (empty($criteria)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'At least one search criteria is required',
        'message' => 'Please provide lastName, firstName, email, or phone for equality search'
    ]);
    exit();
}

// For demo purposes, return mock data
// In a real implementation, this would connect to MongoDB with encryption
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
    ]
];

// Filter results based on criteria
$results = [];
foreach ($mockPatients as $patient) {
    $matches = true;
    
    if (isset($criteria['lastName']) && strtolower($patient['lastName']) !== strtolower($criteria['lastName'])) {
        $matches = false;
    }
    if (isset($criteria['firstName']) && strtolower($patient['firstName']) !== strtolower($criteria['firstName'])) {
        $matches = false;
    }
    if (isset($criteria['email']) && strtolower($patient['email']) !== strtolower($criteria['email'])) {
        $matches = false;
    }
    if (isset($criteria['phone']) && $patient['phoneNumber'] !== $criteria['phone']) {
        $matches = false;
    }
    
    if ($matches) {
        $results[] = $patient;
    }
}

// Return response
echo json_encode([
    'success' => true,
    'searchType' => 'equality',
    'criteria' => $criteria,
    'results' => $results,
    'totalResults' => count($results),
    'searchTime' => rand(20, 80), // Mock search time
    'encryptedFields' => ['lastName', 'firstName', 'email', 'phoneNumber'],
    'encryptionType' => 'deterministic',
    'message' => 'Equality search completed on encrypted data'
]);
?>
