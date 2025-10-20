<?php
/**
 * @fileoverview Mock Patient Detail API Endpoint
 *
 * This API endpoint provides mock patient detail data for the SecureHealth HIPAA-compliant
 * medical records system. It returns a single patient record based on the provided ID
 * parameter, used for demonstration and testing purposes.
 *
 * @api
 * @endpoint GET /api_mock_patient.php?id={patientId}
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Returns mock patient detail data
 * - Supports patient ID parameter
 * - JSON response format
 * - Cache control headers
 * - Development/testing purposes only
 *
 * @parameters
 * - id: Patient ID (optional, defaults to sample ID if not provided)
 *
 * @response
 * Returns complete patient record including:
 * - Personal information (name, birth date, contact details)
 * - Medical information (diagnosis, medications, allergies)
 * - Insurance details
 * - Emergency contact information
 * - Visit history and notes
 *
 * @dataStructure
 * {
 *   "id": "patient_id",
 *   "firstName": "string",
 *   "lastName": "string",
 *   "birthDate": "ISO_date_string",
 *   "email": "email_address",
 *   "phone": "phone_number",
 *   "ssn": "masked_ssn",
 *   "address": "full_address",
 *   "diagnosis": ["array_of_diagnoses"],
 *   "medications": ["array_of_medications"],
 *   "allergies": ["array_of_allergies"],
 *   "bloodType": "blood_type",
 *   "emergencyContact": {...},
 *   "insuranceProvider": "provider_name",
 *   "insuranceNumber": "policy_number",
 *   "lastVisit": "ISO_date_string",
 *   "notes": "clinical_notes"
 * }
 *
 * @security
 * This endpoint returns mock data for development/testing only.
 * In production, this would be replaced with encrypted MongoDB queries
 * and proper authentication/authorization.
 *
 * @cache
 * Disables caching to ensure fresh data for testing purposes.
 */

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