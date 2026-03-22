<?php

declare(strict_types=1);

namespace CalculatorApi\Controllers;

use CalculatorApi\Services\CalculatorService;
use CalculatorApi\Validators\InputValidator;
use CalculatorApi\Exceptions\ValidationException;
use CalculatorApi\Exceptions\CalculationException;

class CalculatorController
{
    private CalculatorService $calculatorService;
    private InputValidator $validator;

    public function __construct(CalculatorService $calculatorService, InputValidator $validator)
    {
        $this->calculatorService = $calculatorService;
        $this->validator = $validator;
    }

    /**
     * Handle addition operation
     */
    public function add(): void
    {
        $this->handleCalculation('add', 'addition');
    }

    /**
     * Handle subtraction operation
     */
    public function subtract(): void
    {
        $this->handleCalculation('subtract', 'subtraction');
    }

    /**
     * Handle multiplication operation
     */
    public function multiply(): void
    {
        $this->handleCalculation('multiply', 'multiplication');
    }

    /**
     * Handle division operation
     */
    public function divide(): void
    {
        $this->handleCalculation('divide', 'division');
    }

    /**
     * Generic calculation handler
     */
    private function handleCalculation(string $operation, string $operationName): void
    {
        $startTime = microtime(true);
        
        try {
            // Get and parse input
            $input = $this->getInput();
            
            // Validate input
            $validationResult = $this->validator->validateCalculationInput($input);
            
            if (!$validationResult['valid']) {
                $this->sendErrorResponse(400, $validationResult['error']);
                return;
            }

            // Additional validation for division
            if ($operation === 'divide') {
                $divisionValidation = $this->validator->validateDivisionInput($input);
                if (!$divisionValidation['valid']) {
                    $this->sendErrorResponse(400, $divisionValidation['error']);
                    return;
                }
            }

            $a = (float) $input['a'];
            $b = (float) $input['b'];

            // Perform calculation
            $result = $this->calculatorService->$operation($a, $b);

            // Send success response
            $this->sendSuccessResponse($result, $operationName, $a, $b, $startTime);

        } catch (ValidationException $e) {
            $this->sendErrorResponse(400, [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'details' => $e->getDetails()
            ]);
        } catch (CalculationException $e) {
            $this->sendErrorResponse(400, [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'details' => $e->getDetails()
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, [
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'An unexpected error occurred',
                'details' => null
            ]);
        }
    }

    /**
     * Get and parse JSON input
     */
    private function getInput(): array
    {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                ERROR_CODES['INVALID_JSON'],
                'Invalid JSON format: ' . json_last_error_msg(),
                ['json_error' => json_last_error_msg()]
            );
        }

        return $input ?? [];
    }

    /**
     * Send success response
     */
    private function sendSuccessResponse(float $result, string $operation, float $a, float $b, float $startTime): void
    {
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        http_response_code(200);
        header('X-Response-Time: ' . $responseTime . 'ms');
        
        $response = [
            'success' => true,
            'result' => $result,
            'operation' => $operation,
            'operands' => [
                'a' => $a,
                'b' => $b
            ],
            'timestamp' => date('c')
        ];

        echo json_encode($response);
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(int $statusCode, array $error): void
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => $error,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
    }
}