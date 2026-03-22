<?php

declare(strict_types=1);

// API Configuration
define('API_VERSION', '1.0');
define('API_BASE_PATH', '/api/v1/calculator');

// CORS Configuration
define('ALLOWED_ORIGINS', ['*']); // Configure specific domains in production
define('ALLOWED_METHODS', ['GET', 'POST', 'OPTIONS']);
define('ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Requested-With']);

// Validation Configuration
define('MAX_DECIMAL_PLACES', 14);
define('MAX_FLOAT_VALUE', PHP_FLOAT_MAX);
define('MIN_FLOAT_VALUE', -PHP_FLOAT_MAX);

// Error Codes
define('ERROR_CODES', [
    'VALIDATION_ERROR' => 'VALIDATION_ERROR',
    'MISSING_PARAMETER' => 'MISSING_PARAMETER',
    'INVALID_TYPE' => 'INVALID_TYPE',
    'DIVISION_BY_ZERO' => 'DIVISION_BY_ZERO',
    'NUMBER_OVERFLOW' => 'NUMBER_OVERFLOW',
    'INVALID_JSON' => 'INVALID_JSON',
    'METHOD_NOT_ALLOWED' => 'METHOD_NOT_ALLOWED',
    'ENDPOINT_NOT_FOUND' => 'ENDPOINT_NOT_FOUND',
    'CONTENT_TYPE_REQUIRED' => 'CONTENT_TYPE_REQUIRED'
]);