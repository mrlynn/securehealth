<?php
/**
 * @fileoverview Mock Patients List API Endpoint
 *
 * This API endpoint provides mock patient list data for the SecureHealth HIPAA-compliant
 * medical records system. It returns an array of patient records with basic information
 * and medical data, used for demonstration and testing purposes.
 *
 * @api
 * @endpoint GET /api_mock_patients.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Returns array of mock patient records
 * - JSON response format
 * - Cache control headers
 * - Development/testing purposes only
 * - Includes medical information and roles
 *
 * @response
 * Returns array of patient objects containing:
 * - Basic information (id, name, birth date, contact)
 * - Medical information (diagnosis, medications)
 * - User role information
 * - Masked SSN for security
 *
 * @dataStructure
 * [
 *   {
 *     "id": "patient_id",
 *     "firstName": "string",
 *     "lastName": "string",
 *     "birthDate": "ISO_date_string",
 *     "email": "email_address",
 *     "phone": "phone_number",
 *     "ssn": "masked_ssn",
 *     "diagnosis": ["array_of_diagnoses"],
 *     "medications": ["array_of_medications"],
 *     "role": "user_role"
 *   }
 * ]
 *
 * @security
 * This endpoint returns mock data for development/testing only.
 * In production, this would be replaced with encrypted MongoDB queries
 * and proper authentication/authorization. SSNs are masked for security.
 *
 * @cache
 * Disables caching to ensure fresh data for testing purposes.
 */

// Set the content type to JSON
header('Content-Type: application/json');

// Disable caching
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Mock patient data
$patients = [
  [
    "id" => "60f1a5f3c53e7e24e8d13a70",
    "firstName" => "John",
    "lastName" => "Smith",
    "birthDate" => "1975-05-12T00:00:00.000Z",
    "email" => "john.smith@example.com",
    "phone" => "555-123-4567",
    "ssn" => "*****1234",
    "diagnosis" => ["Type 2 Diabetes", "Hypertension"],
    "medications" => ["Metformin 500mg", "Lisinopril 10mg"],
    "role" => "ROLE_DOCTOR"
  ],
  [
    "id" => "60f1a5f3c53e7e24e8d13a71",
    "firstName" => "Jane",
    "lastName" => "Doe",
    "birthDate" => "1982-08-24T00:00:00.000Z",
    "email" => "jane.doe@example.com",
    "phone" => "555-987-6543",
    "ssn" => "*****5678",
    "diagnosis" => ["Asthma", "Allergic Rhinitis"],
    "medications" => ["Albuterol Inhaler", "Loratadine 10mg"],
    "role" => "ROLE_DOCTOR"
  ],
  [
    "id" => "60f1a5f3c53e7e24e8d13a72",
    "firstName" => "Robert",
    "lastName" => "Johnson",
    "birthDate" => "1968-11-03T00:00:00.000Z",
    "email" => "robert.j@example.com",
    "phone" => "555-345-6789",
    "ssn" => "*****9012",
    "diagnosis" => ["Coronary Artery Disease", "Hyperlipidemia"],
    "medications" => ["Atorvastatin 20mg", "Aspirin 81mg"],
    "role" => "ROLE_DOCTOR"
  ],
  [
    "id" => "60f1a5f3c53e7e24e8d13a73",
    "firstName" => "Maria",
    "lastName" => "Garcia",
    "birthDate" => "1990-04-15T00:00:00.000Z",
    "email" => "maria.g@example.com",
    "phone" => "555-234-5678",
    "ssn" => "*****3456",
    "diagnosis" => ["Migraine", "Anxiety Disorder"],
    "medications" => ["Sumatriptan 50mg", "Sertraline 50mg"],
    "role" => "ROLE_NURSE"
  ],
  [
    "id" => "60f1a5f3c53e7e24e8d13a74",
    "firstName" => "David",
    "lastName" => "Wilson",
    "birthDate" => "1955-09-28T00:00:00.000Z",
    "email" => "d.wilson@example.com",
    "phone" => "555-876-5432",
    "ssn" => "*****7890",
    "diagnosis" => ["Osteoarthritis", "Hypothyroidism"],
    "medications" => ["Acetaminophen 500mg", "Levothyroxine 50mcg"],
    "role" => "ROLE_NURSE"
  ]
];

// Output the JSON data
echo json_encode($patients);