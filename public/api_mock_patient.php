<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Disable caching
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Get the patient ID from the query string
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Mock patient detail data
$patient = [
  "id" => $id ?: "68dbf20ae69980a1de028e22",
  "firstName" => "John",
  "lastName" => "Smith",
  "birthDate" => "1975-05-12T00:00:00.000Z",
  "email" => "john.smith@example.com",
  "phone" => "555-123-4567",
  "ssn" => "*****1234",
  "address" => "123 Main St, Anytown, USA",
  "diagnosis" => ["Type 2 Diabetes", "Hypertension"],
  "medications" => ["Metformin 500mg", "Lisinopril 10mg"],
  "allergies" => ["Penicillin", "Sulfa drugs"],
  "bloodType" => "A+",
  "emergencyContact" => [
    "name" => "Jane Smith",
    "relationship" => "Spouse",
    "phone" => "555-987-6543"
  ],
  "insuranceProvider" => "HealthPlus Insurance",
  "insuranceNumber" => "HP123456789",
  "lastVisit" => "2023-09-15T00:00:00.000Z",
  "notes" => "Patient is managing diabetes well with current medication regimen. Blood pressure readings have improved since last visit."
];

// Output the JSON data
echo json_encode($patient);