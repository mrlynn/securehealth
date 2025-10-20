<?php
/**
 * @fileoverview Simple Patients API Endpoint
 *
 * This API endpoint provides a simple list of patient records for the SecureHealth
 * HIPAA-compliant medical records system. It returns basic patient information
 * without encryption for demonstration and testing purposes.
 *
 * @api
 * @endpoint GET /api_patients.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Returns basic patient information
 * - JSON response format
 * - Simple data structure
 * - Development/testing purposes only
 * - No encryption (for demo purposes)
 *
 * @response
 * Returns array of patient objects with basic information:
 * [
 *   {
 *     "id": "1",
 *     "firstName": "John",
 *     "lastName": "Doe",
 *     "birthDate": "1980-05-15",
 *     "email": "john.doe@example.com",
 *     "phone": "555-123-4567"
 *   }
 * ]
 *
 * @dataStructure
 * - id: Simple string identifier
 * - firstName: Patient's first name
 * - lastName: Patient's last name
 * - birthDate: Date of birth (YYYY-MM-DD format)
 * - email: Email address
 * - phone: Phone number
 *
 * @security
 * This endpoint returns unencrypted data for demonstration purposes only.
 * In production, this would be replaced with encrypted MongoDB queries
 * and proper authentication/authorization.
 *
 * @note
 * This is a simplified version for basic testing. For production use,
 * use the MongoDB-based endpoints with proper encryption.
 */

header('Content-Type: application/json');

// Example patients data - in a real app this would come from MongoDB
$patients = [
    [
        'id' => '1',
        'firstName' => 'John',
        'lastName' => 'Doe',
        'birthDate' => '1980-05-15',
        'email' => 'john.doe@example.com',
        'phone' => '555-123-4567'
    ],
    [
        'id' => '2',
        'firstName' => 'Jane',
        'lastName' => 'Smith',
        'birthDate' => '1985-10-22',
        'email' => 'jane.smith@example.com',
        'phone' => '555-987-6543'
    ],
    [
        'id' => '3',
        'firstName' => 'Robert',
        'lastName' => 'Johnson',
        'birthDate' => '1975-03-08',
        'email' => 'robert.j@example.com',
        'phone' => '555-567-8901'
    ]
];

// Return the patients data
echo json_encode($patients);