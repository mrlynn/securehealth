<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

try {
    $mongoUri = getenv('MONGODB_URI') ?: 'mongodb+srv://mike:Password456%21@performance.zbcul.mongodb.net/?retryWrites=true&w=majority&appName=performance';
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';

    $client = new Client($mongoUri);
    $db = $client->selectDatabase($dbName);
    $collection = $db->selectCollection('audit_log');

    // Filters
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 50;
    $action = $_GET['action'] ?? null;           // e.g., create|read|update|delete
    $entityType = $_GET['entityType'] ?? null;   // e.g., patient|user
    $since = $_GET['since'] ?? null;             // milliseconds since epoch

    $filter = [];
    if ($action) {
        $filter['action'] = $action;
    }
    if ($entityType) {
        $filter['entityType'] = $entityType;
    }
    if ($since) {
        $ms = (int)$since;
        if ($ms > 0) {
            $filter['timestamp'] = ['$gte' => new UTCDateTime($ms)];
        }
    }

    $cursor = $collection->find($filter, [
        'sort' => ['timestamp' => -1],
        'limit' => $limit,
    ]);

    $logs = [];
    foreach ($cursor as $doc) {
        // Convert BSONDocument to PHP array (Extended JSON like structure)
        $logs[] = iterator_to_array($doc);
    }

    // Basic metrics for dashboard
    $now = new UTCDateTime();
    $oneDayAgo = new UTCDateTime((int)(microtime(true) * 1000) - 24 * 60 * 60 * 1000);
    $last24hCount = $collection->countDocuments(['timestamp' => ['$gte' => $oneDayAgo]]);

    echo json_encode([
        'logs' => $logs,
        'metrics' => [
            'total' => $collection->countDocuments([]),
            'last24h' => $last24hCount,
        ],
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}


