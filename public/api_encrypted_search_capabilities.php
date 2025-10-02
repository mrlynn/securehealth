<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Return search capabilities
echo json_encode([
    'searchTypes' => [
        'equality' => [
            'description' => 'Exact match searches using deterministic encryption',
            'supportedFields' => ['lastName', 'firstName', 'email', 'phoneNumber'],
            'encryptionType' => 'deterministic',
            'example' => 'Find all patients with lastName "Smith"'
        ],
        'range' => [
            'description' => 'Range queries using range encryption',
            'supportedFields' => ['birthDate'],
            'encryptionType' => 'range',
            'example' => 'Find all patients born between 1980 and 1990'
        ],
        'complex' => [
            'description' => 'Multi-field searches combining different encryption types',
            'supportedFields' => ['lastName', 'email', 'birthDate', 'phoneNumber'],
            'encryptionTypes' => ['deterministic', 'range'],
            'example' => 'Find patients with lastName containing "John" and age > 30'
        ]
    ],
    'encryptionTypes' => [
        'deterministic' => [
            'description' => 'Same input always produces same encrypted output',
            'useCase' => 'Exact match searches',
            'security' => 'Medium - allows pattern analysis'
        ],
        'range' => [
            'description' => 'Enables comparison operations on encrypted data',
            'useCase' => 'Range queries, sorting',
            'security' => 'Medium - allows ordering analysis'
        ],
        'random' => [
            'description' => 'Maximum security encryption',
            'useCase' => 'Highly sensitive data (SSN, diagnosis)',
            'security' => 'High - no search capabilities'
        ]
    ],
    'fieldEncryptionMap' => [
        'lastName' => 'deterministic',
        'firstName' => 'deterministic',
        'email' => 'deterministic',
        'phoneNumber' => 'deterministic',
        'birthDate' => 'deterministic', // Using deterministic for demo
        'ssn' => 'random',
        'diagnosis' => 'random',
        'medications' => 'random',
        'insuranceDetails' => 'random',
        'notes' => 'random'
    ]
]);
?>
