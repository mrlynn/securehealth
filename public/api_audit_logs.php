<?php
/**
 * @fileoverview Audit Logs API Endpoint
 *
 * This API endpoint provides access to audit logs for the SecureHealth HIPAA-compliant
 * medical records system. It retrieves audit trail information from MongoDB and supports
 * filtering by action type, entity type, and time range.
 *
 * @api
 * @endpoint GET /api_audit_logs.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Retrieve audit logs with pagination (limit: 1-500, default: 50)
 * - Filter by action type (create|read|update|delete)
 * - Filter by entity type (patient|user)
 * - Filter by timestamp (since parameter in milliseconds)
 * - Basic metrics for dashboard (total count, last 24h count)
 * - MongoDB integration with proper error handling
 *
 * @parameters
 * - limit: Maximum number of logs to return (1-500, default: 50)
 * - action: Filter by action type (optional)
 * - entityType: Filter by entity type (optional)
 * - since: Filter by timestamp in milliseconds since epoch (optional)
 *
 * @response
 * {
 *   "logs": [...], // Array of audit log entries
 *   "metrics": {
 *     "total": 1234, // Total number of audit logs
 *     "last24h": 56  // Number of logs in last 24 hours
 *   }
 * }
 *
 * @security
 * This endpoint handles sensitive audit information and should be protected
 * with proper authentication and authorization in production environments.
 *
 * @dependencies
 * - MongoDB PHP Driver
 * - MongoDB\Client
 * - MongoDB\BSON\UTCDateTime
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;

header('Content-Type: application/json');

try {
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new RuntimeException('MongoDB connection string missing. Set MONGODB_URI in the environment.');
    }
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

