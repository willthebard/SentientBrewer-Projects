<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Router/Router.php';
require_once __DIR__ . '/../src/Controllers/CalculatorController.php';
require_once __DIR__ . '/../src/Services/CalculatorService.php';
require_once __DIR__ . '/../src/Validators/InputValidator.php';
require_once __DIR__ . '/../src/Exceptions/ValidationException.php';
require_once __DIR__ . '/../src/Exceptions/CalculationException.php';
require_once __DIR__ . '/../src/Middleware/CorsMiddleware.php';
require_once __DIR__ . '/../src/Middleware/JsonMiddleware.php';

use CalculatorApi\Router\Router;
use CalculatorApi\Controllers\CalculatorController;
use CalculatorApi\Services\CalculatorService;
use CalculatorApi\Validators\InputValidator;
use CalculatorApi\Middleware\CorsMiddleware;
use CalculatorApi\Middleware\JsonMiddleware;

// Enable error reporting for development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Start output buffering
ob_start();

try {
    // Initialize components
    $calculator = new CalculatorService();
    $validator = new InputValidator();
    $controller = new CalculatorController($calculator, $validator);
    $router = new Router();

    // Apply CORS middleware
    CorsMiddleware::handle();

    // Apply JSON middleware
    JsonMiddleware::handle();

    // Register routes
    $router->addRoute('POST', '/api/v1/calculator/add', [$controller, 'add']);
    $router->addRoute('POST', '/api/v1/calculator/subtract', [$controller, 'subtract']);
    $router->addRoute('POST', '/api/v1/calculator/multiply', [$controller, 'multiply']);
    $router->addRoute('POST', '/api/v1/calculator/divide', [$controller, 'divide']);

    // Get request method and URI
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Dispatch request
    $router->dispatch($method, $uri);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'An unexpected error occurred',
            'details' => null
        ],
        'timestamp' => date('c')
    ];
    
    echo json_encode($response);
}

ob_end_flush();