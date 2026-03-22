<?php

declare(strict_types=1);

namespace CalculatorApi\Router;

class Router
{
    private array $routes = [];

    /**
     * Add a route to the router
     */
    public function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Dispatch the request to the appropriate handler
     */
    public function dispatch(string $method, string $path): void
    {
        // Handle OPTIONS requests for CORS
        if ($method === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        // Check if route exists
        if (!isset($this->routes[$method][$path])) {
            $this->handleNotFound($method, $path);
            return;
        }

        $handler = $this->routes[$method][$path];
        
        // Execute the handler
        if (is_callable($handler)) {
            call_user_func($handler);
        }
    }

    /**
     * Handle 404 and 405 errors
     */
    private function handleNotFound(string $method, string $path): void
    {
        // Check if path exists but method is wrong
        $pathExists = false;
        $allowedMethods = [];
        
        foreach ($this->routes as $routeMethod => $routes) {
            if (isset($routes[$path])) {
                $pathExists = true;
                $allowedMethods[] = $routeMethod;
            }
        }

        if ($pathExists) {
            // Method not allowed
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            
            $response = [
                'success' => false,
                'error' => [
                    'code' => ERROR_CODES['METHOD_NOT_ALLOWED'],
                    'message' => 'Only ' . implode(', ', $allowedMethods) . ' method(s) allowed for this endpoint',
                    'details' => [
                        'allowed_methods' => $allowedMethods
                    ]
                ],
                'timestamp' => date('c')
            ];
        } else {
            // Endpoint not found
            http_response_code(404);
            
            $response = [
                'success' => false,
                'error' => [
                    'code' => ERROR_CODES['ENDPOINT_NOT_FOUND'],
                    'message' => 'Endpoint not found',
                    'details' => [
                        'path' => $path,
                        'method' => $method
                    ]
                ],
                'timestamp' => date('c')
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }
}