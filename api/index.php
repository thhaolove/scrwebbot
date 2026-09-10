<?php
/**
 * SHOPKEY API - Entry Point
 * 
 * @package SHOPKEY API
 * @version 1.0.0
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

echo json_encode([
    'success' => true,
    'message' => 'SHOPKEY API v1.0',
    'version' => '1.0.0',
    'documentation' => 'Contact administrator for API documentation',
    'endpoints' => [
        'POST /api/v1/orders/create' => 'Create new order',
        'GET /api/v1/orders/status' => 'Check order status',
        'GET /api/v1/orders/list' => 'List orders',
        'GET /api/v1/products/list' => 'List products',
        'GET /api/v1/account/balance' => 'Check account balance',
        'GET /api/v1/account/info' => 'Get account info'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

