<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repository\UserRepository;
use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use MongoDB\Client;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// MongoDB connection
$mongoUrl = $_ENV['MONGODB_URI'] ?? 'mongodb://localhost:27017';
$mongoDb = $_ENV['MONGODB_DB'] ?? 'securehealth';

try {
    $client = new Client($mongoUrl);
    $database = $client->selectDatabase($mongoDb);
    $usersCollection = $database->selectCollection('users');
    
    echo "=== User Debug Information ===\n\n";
    
    // Check if users collection exists
    $collections = $database->listCollections(['filter' => ['name' => 'users']]);
    $usersExists = false;
    foreach ($collections as $collection) {
        if ($collection->getName() === 'users') {
            $usersExists = true;
            break;
        }
    }
    
    if (!$usersExists) {
        echo "❌ Users collection does not exist!\n";
        echo "Creating users collection...\n";
        $database->createCollection('users');
        echo "✅ Users collection created.\n\n";
    } else {
        echo "✅ Users collection exists.\n\n";
    }
    
    // Count users
    $userCount = $usersCollection->countDocuments();
    echo "📊 Total users in database: $userCount\n\n";
    
    if ($userCount > 0) {
        echo "👥 Users in database:\n";
        echo str_repeat("-", 80) . "\n";
        
        $users = $usersCollection->find();
        foreach ($users as $user) {
            echo "Email: " . ($user['email'] ?? 'N/A') . "\n";
            echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
            echo "Password: " . (isset($user['password']) ? substr($user['password'], 0, 20) . '...' : 'N/A') . "\n";
            $roles = isset($user['roles']) ? (is_array($user['roles']) ? $user['roles'] : iterator_to_array($user['roles'])) : [];
            echo "Roles: " . implode(', ', $roles) . "\n";
            echo "Is Admin: " . (isset($user['isAdmin']) ? ($user['isAdmin'] ? 'Yes' : 'No') : 'N/A') . "\n";
            echo str_repeat("-", 80) . "\n";
        }
    } else {
        echo "❌ No users found in database!\n\n";
        echo "Creating test users...\n";
        
        $testUsers = [
            [
                'email' => 'doctor@example.com',
                'username' => 'Dr. Smith',
                'password' => 'doctor', // Plain password for demo
                'roles' => ['ROLE_DOCTOR'],
                'isAdmin' => false
            ],
            [
                'email' => 'nurse@example.com',
                'username' => 'Nurse Johnson',
                'password' => 'nurse', // Plain password for demo
                'roles' => ['ROLE_NURSE'],
                'isAdmin' => false
            ],
            [
                'email' => 'receptionist@example.com',
                'username' => 'Receptionist Davis',
                'password' => 'receptionist', // Plain password for demo
                'roles' => ['ROLE_RECEPTIONIST'],
                'isAdmin' => false
            ],
            [
                'email' => 'admin@example.com',
                'username' => 'Admin User',
                'password' => 'admin', // Plain password for demo
                'roles' => ['ROLE_ADMIN'],
                'isAdmin' => true
            ]
        ];
        
        $result = $usersCollection->insertMany($testUsers);
        echo "✅ Created " . $result->getInsertedCount() . " test users.\n\n";
        
        // Show the created users
        echo "👥 Created users:\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($testUsers as $user) {
            echo "Email: {$user['email']}\n";
            echo "Username: {$user['username']}\n";
            echo "Password: {$user['password']}\n";
            echo "Roles: " . implode(', ', $user['roles']) . "\n";
            echo "Is Admin: " . ($user['isAdmin'] ? 'Yes' : 'No') . "\n";
            echo str_repeat("-", 80) . "\n";
        }
    }
    
    echo "\n=== Test Login Credentials ===\n";
    echo "Doctor: doctor@example.com / doctor\n";
    echo "Nurse: nurse@example.com / nurse\n";
    echo "Receptionist: receptionist@example.com / receptionist\n";
    echo "Admin: admin@example.com / admin\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
