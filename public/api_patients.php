<?php
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