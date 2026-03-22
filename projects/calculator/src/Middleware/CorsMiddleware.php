<?php

declare(strict_types=1);

namespace CalculatorApi\Middleware;

class CorsMiddleware
{
    /**
     * Handle CORS headers
     */
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (in_array('*', ALLOWED_ORIGINS) || in_array($origin, ALLOWED_ORIGINS)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', ALLOWED_METHODS));
        header('Access-Control-Allow-Headers: ' . implode(', ', ALLOWED_HEADERS));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}