<?php
// Debug script to test SessionAuthenticator

require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Security\SessionAuthenticator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Create a mock URL generator
$urlGenerator = new class implements UrlGeneratorInterface {
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string {
        return '/';
    }
    public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}
    public function getContext(): \Symfony\Component\Routing\RequestContext {
        return new \Symfony\Component\Routing\RequestContext();
    }
};

// Create SessionAuthenticator
$authenticator = new SessionAuthenticator($urlGenerator);

// Create a request with session data
$request = Request::create('/api/patients', 'GET');
$session = new Session();
$session->start();
$session->set('user', [
    'email' => 'doctor@example.com',
    'username' => 'Dr. Smith',
    'roles' => ['ROLE_DOCTOR', 'ROLE_USER']
]);
$request->setSession($session);

echo "=== SessionAuthenticator Debug ===\n\n";

echo "Request path: " . $request->getPathInfo() . "\n";
echo "Session ID: " . $session->getId() . "\n";
echo "Session data: " . json_encode($session->get('user')) . "\n\n";

echo "Testing supports() method:\n";
$supports = $authenticator->supports($request);
echo "Supports: " . ($supports ? 'YES' : 'NO') . "\n\n";

if ($supports) {
    echo "Testing authenticate() method:\n";
    try {
        $passport = $authenticator->authenticate($request);
        echo "Authentication successful!\n";
        echo "Passport user: " . $passport->getUser()->getUserIdentifier() . "\n";
        echo "Passport user roles: " . json_encode($passport->getUser()->getRoles()) . "\n";
    } catch (Exception $e) {
        echo "Authentication failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Authenticator does not support this request\n";
}
?>
