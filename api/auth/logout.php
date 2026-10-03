<?php
/**
 * Campus Connect - Logout API Endpoint
 */

require_once __DIR__ . '/../common.php';

clearUserSession();

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);
