<?php
header('Content-Type: application/json');

// Enable CORS for local development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Log request details
file_put_contents('php://stderr', "Encrypted Search Debug - Request at " . date('Y-m-d H:i:s') . "\n");
file_put_contents('php://stderr', "Method: " . $_SERVER['REQUEST_METHOD'] . "\n");
file_put_contents('php://stderr', "URI: " . $_SERVER['REQUEST_URI'] . "\n");

// Parse request
$requestType = "";

// First check query parameter
if (isset($_GET['type'])) {
    $requestType = $_GET['type'];
}
// Then check URI patterns
else if (strpos($_SERVER['REQUEST_URI'], 'equality') !== false) {
    $requestType = "equality";
} else if (strpos($_SERVER['REQUEST_URI'], 'range') !== false) {
    $requestType = "range";
} else if (strpos($_SERVER['REQUEST_URI'], 'complex') !== false) {
    $requestType = "complex";
} else if (strpos($_SERVER['REQUEST_URI'], 'capabilities') !== false) {
    $requestType = "capabilities";
}

// For debugging
file_put_contents('php://stderr', "Request type determined: $requestType\n");

// Get request body
$rawData = file_get_contents('php://input');
$criteria = json_decode($rawData, true) ?: [];

file_put_contents('php://stderr', "Search type: $requestType\n");
file_put_contents('php://stderr', "Search criteria: " . print_r($criteria, true) . "\n");

// Define mock capabilities
if ($requestType === "capabilities") {
    $response = [
        "success" => true,
        "message" => "Encryption capabilities retrieved successfully",
        "fieldEncryptionMap" => [
            "firstName" => "deterministic",
            "lastName" => "deterministic",
            "email" => "deterministic",
            "phoneNumber" => "deterministic",
            "birthDate" => "range",
            "ssn" => "random",
            "diagnosis" => "random",
            "medications" => "random",
            "insuranceDetails" => "random"
        ]
    ];
    
    echo json_encode($response);
    exit;
}

// Mock patient data (shared across all search types)
$patients = [
    [
        "firstName" => "John",
        "lastName" => "Smith",
        "email" => "john.smith@example.com",
        "phoneNumber" => "(555) 123-4567",
        "birthDate" => "1985-06-15",
        "ssn" => "123-45-6789",
        "diagnosis" => ["Hypertension", "Type 2 Diabetes"],
        "medications" => ["Lisinopril", "Metformin"],
        "insuranceDetails" => "BlueCross ID: BC1234567",
        "createdAt" => "2025-10-03 14:23:10"
    ],
    [
        "firstName" => "Sarah",
        "lastName" => "Johnson",
        "email" => "sarah.johnson@example.com",
        "phoneNumber" => "(555) 987-6543",
        "birthDate" => "1990-03-24",
        "ssn" => "987-65-4321",
        "diagnosis" => ["Asthma"],
        "medications" => ["Albuterol"],
        "insuranceDetails" => "Aetna ID: AE7654321",
        "createdAt" => "2025-10-03 16:45:22"
    ],
    [
        "firstName" => "Robert",
        "lastName" => "Williams",
        "email" => "robert.williams@example.com",
        "phoneNumber" => "(555) 456-7890",
        "birthDate" => "1978-11-08",
        "ssn" => "456-78-9012",
        "diagnosis" => ["Arthritis", "Hypercholesterolemia"],
        "medications" => ["Ibuprofen", "Atorvastatin"],
        "insuranceDetails" => "UnitedHealth ID: UH8901234",
        "createdAt" => "2025-10-04 09:12:45"
    ],
    [
        "firstName" => "Jennifer",
        "lastName" => "Brown",
        "email" => "jennifer.brown@example.com",
        "phoneNumber" => "(555) 789-0123",
        "birthDate" => "1992-07-30",
        "ssn" => "789-01-2345",
        "diagnosis" => ["Migraine", "Anxiety"],
        "medications" => ["Sumatriptan", "Sertraline"],
        "insuranceDetails" => "Cigna ID: CG2345678",
        "createdAt" => "2025-10-04 10:30:15"
    ],
    [
        "firstName" => "Michael",
        "lastName" => "Davis",
        "email" => "michael.davis@example.com",
        "phoneNumber" => "(555) 321-6547",
        "birthDate" => "1965-02-17",
        "ssn" => "321-65-4789",
        "diagnosis" => ["Coronary Artery Disease", "GERD"],
        "medications" => ["Aspirin", "Omeprazole"],
        "insuranceDetails" => "Medicare ID: M9876543",
        "createdAt" => "2025-10-04 11:05:33"
    ],
    [
        "firstName" => "David",
        "lastName" => "Garcia",
        "email" => "david.garcia@example.com",
        "phoneNumber" => "(555) 234-5678",
        "birthDate" => "1980-09-25",
        "ssn" => "234-56-7890",
        "diagnosis" => ["Depression"],
        "medications" => ["Fluoxetine"],
        "insuranceDetails" => "Humana ID: HU3456789",
        "createdAt" => "2025-10-04 11:27:50"
    ]
];

// Filter patients based on search criteria
$results = [];

if ($requestType === "equality") {
    $results = array_filter($patients, function($patient) use ($criteria) {
        if (!empty($criteria["firstName"]) && $patient["firstName"] !== $criteria["firstName"]) {
            return false;
        }
        if (!empty($criteria["lastName"]) && $patient["lastName"] !== $criteria["lastName"]) {
            return false;
        }
        if (!empty($criteria["email"]) && $patient["email"] !== $criteria["email"]) {
            return false;
        }
        if (!empty($criteria["phone"]) && strpos($patient["phoneNumber"], $criteria["phone"]) === false) {
            return false;
        }
        return true;
    });
} elseif ($requestType === "range") {
    $results = array_filter($patients, function($patient) use ($criteria) {
        $patientBirthDate = strtotime($patient["birthDate"]);
        
        // Birth date range filter
        if (!empty($criteria["birthDateFrom"])) {
            $fromDate = strtotime($criteria["birthDateFrom"]);
            if ($patientBirthDate < $fromDate) {
                return false;
            }
        }
        
        if (!empty($criteria["birthDateTo"])) {
            $toDate = strtotime($criteria["birthDateTo"]);
            if ($patientBirthDate > $toDate) {
                return false;
            }
        }
        
        // Age range filter
        $age = date('Y') - date('Y', $patientBirthDate);
        
        if (!empty($criteria["minAge"]) && $age < $criteria["minAge"]) {
            return false;
        }
        
        if (!empty($criteria["maxAge"]) && $age > $criteria["maxAge"]) {
            return false;
        }
        
        return true;
    });
} elseif ($requestType === "complex") {
    $results = array_filter($patients, function($patient) use ($criteria) {
        // Last name filter
        if (!empty($criteria["lastName"]) && stripos($patient["lastName"], $criteria["lastName"]) === false) {
            return false;
        }
        
        // Email domain filter
        if (!empty($criteria["email"])) {
            $parts = explode('@', $patient["email"]);
            $domain = end($parts);
            if (stripos($domain, $criteria["email"]) === false) {
                return false;
            }
        }
        
        // Age filter
        $age = date('Y') - date('Y', strtotime($patient["birthDate"]));
        if (!empty($criteria["minAge"]) && $age < $criteria["minAge"]) {
            return false;
        }
        
        // Phone prefix filter
        if (!empty($criteria["phonePrefix"]) && strpos($patient["phoneNumber"], $criteria["phonePrefix"]) === false) {
            return false;
        }
        
        // Birth year filter
        if (!empty($criteria["birthYear"]) && date('Y', strtotime($patient["birthDate"])) != $criteria["birthYear"]) {
            return false;
        }
        
        return true;
    });
}

// Convert to indexed array
$results = array_values($results);

// Simulate search delay for realism
usleep(rand(100000, 500000)); // 100-500ms delay

// Return search results
echo json_encode([
    "success" => true,
    "message" => "Search completed successfully",
    "totalResults" => count($results),
    "results" => $results,
    "searchType" => $requestType,
    "criteria" => $criteria,
    "debug" => true
]);