<?php
// Debug script to check existing users and patients
header('Content-Type: application/json');

try {
    // Get the email from query parameter
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode([
            'error' => 'Please provide an email parameter: ?email=test@example.com'
        ]);
        exit;
    }
    
    // This would need to be integrated with your Symfony app
    // For now, just return the email being checked
    echo json_encode([
        'email' => $email,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Check console logs or database for existing users/patients with this email',
        'suggestion' => 'Try with a different email address or check if this email is already registered'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>