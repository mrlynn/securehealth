<?php
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
if (!empty($input['email'])) {
    $criteria['email'] = trim($input['email']);
}
if (!empty($input['minAge'])) {
    $minAge = (int) $input['minAge'];
    if ($minAge >= 0 && $minAge <= 120) {
        $criteria['minAge'] = $minAge;
    }
}
if (!empty($input['phonePrefix'])) {
    $phonePrefix = trim($input['phonePrefix']);
    if (preg_match('/^\d{3}$/', $phonePrefix)) {
        $criteria['phonePrefix'] = $phonePrefix;
    }
}
if (!empty($input['birthYear'])) {
    $birthYear = (int) $input['birthYear'];
    if ($birthYear >= 1900 && $birthYear <= date('Y')) {
        $criteria['birthYear'] = $birthYear;
    }
}

if (empty($criteria)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'At least one search criteria is required',
        'message' => 'Please provide lastName, email, minAge, phonePrefix, or birthYear for complex search'
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
    ],
    [
        'id' => '5',
        'firstName' => 'David',
        'lastName' => 'Brown',
        'email' => 'david.brown@gmail.com',
        'phoneNumber' => '555-789-0123',
        'birthDate' => '1982-05-18',
        'ssn' => '***-**-7890',
        'diagnosis' => ['Arthritis'],
        'medications' => ['Ibuprofen'],
        'createdAt' => '2024-01-19 08:30:00'
    ]
];

// Filter results based on complex criteria
$results = [];
foreach ($mockPatients as $patient) {
    $matches = true;
    $patientBirthDate = new DateTime($patient['birthDate']);
    $patientAge = $patientBirthDate->diff(new DateTime())->y;
    $patientBirthYear = $patientBirthDate->format('Y');
    
    // Check lastName (partial match for complex search)
    if (isset($criteria['lastName']) && stripos($patient['lastName'], $criteria['lastName']) === false) {
        $matches = false;
    }
    
    // Check email (partial match for complex search)
    if (isset($criteria['email']) && stripos($patient['email'], $criteria['email']) === false) {
        $matches = false;
    }
    
    // Check minimum age
    if (isset($criteria['minAge']) && $patientAge < $criteria['minAge']) {
        $matches = false;
    }
    
    // Check phone prefix
    if (isset($criteria['phonePrefix']) && strpos($patient['phoneNumber'], $criteria['phonePrefix']) !== 0) {
        $matches = false;
    }
    
    // Check birth year
    if (isset($criteria['birthYear']) && $patientBirthYear != $criteria['birthYear']) {
        $matches = false;
    }
    
    if ($matches) {
        $results[] = $patient;
    }
}

// Return response
echo json_encode([
    'success' => true,
    'searchType' => 'complex',
    'criteria' => $criteria,
    'results' => $results,
    'totalResults' => count($results),
    'searchTime' => rand(30, 100), // Mock search time
    'encryptedFields' => ['lastName', 'email', 'birthDate', 'phoneNumber'],
    'encryptionTypes' => ['deterministic', 'range'],
    'message' => 'Complex search completed on encrypted data'
]);
?>
