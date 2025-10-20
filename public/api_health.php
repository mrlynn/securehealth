<?php
/**
 * @fileoverview Health Check API Endpoint
 *
 * This API endpoint provides a simple health check for the SecureHealth HIPAA-compliant
 * medical records system. It returns basic system status information without requiring
 * authentication, making it suitable for load balancers and monitoring systems.
 *
 * @api
 * @endpoint GET /api_health.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Simple health status check
 * - No authentication required
 * - Returns current timestamp
 * - API version information
 * - Lightweight and fast response
 *
 * @response
 * {
 *   "status": "ok",
 *   "timestamp": "2024-01-15 14:30:25",
 *   "api_version": "1.0.0"
 * }
 *
 * @useCases
 * - Load balancer health checks
 * - Monitoring system probes
 * - Service availability verification
 * - API endpoint testing
 *
 * @security
 * This endpoint does not expose sensitive data and is safe to use
 * without authentication. It only returns basic system status.
 *
 * @performance
 * Designed to be lightweight and fast, suitable for frequent
 * health check requests from monitoring systems.
 */

header('Content-Type: application/json');

// Simple health check endpoint that doesn't require authentication
echo json_encode([
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'api_version' => '1.0.0'
]);