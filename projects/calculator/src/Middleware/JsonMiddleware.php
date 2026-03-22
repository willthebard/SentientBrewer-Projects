<?php

declare(strict_types=1);

namespace CalculatorApi\Middleware;

class JsonMiddleware
{
    /**
     * Handle JSON middleware - validate content type and parse input
     */
    public static function handle(): void
    {
        // Skip for OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return;
        }

        // Check content type for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') === false) {
                http_response_code(415);
                header('Content-Type: application/json');
                
                $response = [
                    'success' => false,
                    'error' => [
                        'code' => ERROR_CODES['CONTENT_TYPE_REQUIRED'],
                        'message' => 'Content-Type must be application/json',
                        'details' => [
                            'received' => $contentType,
                            'expected' => 'application/json'
                        ]
                    ],
                    'timestamp' => date('c')
                ];
                
                echo json_encode($response);
                exit();
            }
        }

        // Set response headers
        header('Content-Type: application/json');
        header('X-API-Version: ' . API_VERSION);
    }
}