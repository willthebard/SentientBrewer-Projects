<?php

declare(strict_types=1);

namespace CalculatorApi\Services;

use CalculatorApi\Exceptions\CalculationException;

/**
 * CalculatorService handles all arithmetic operations for the calculator API.
 * 
 * This service provides basic mathematical operations with validation
 * to ensure results are within acceptable ranges and handle edge cases
 * such as division by zero and numeric overflow.
 */
class CalculatorService
{
    /**
     * Add two numbers together.
     * 
     * @param float $a The first operand
     * @param float $b The second operand
     * @return float The sum of a and b
     * @throws CalculationException If the result overflows or is invalid
     */
    public function add(float $a, float $b): float
    {
        $result = $a + $b;
        $this->validateResult($result, 'addition');
        return $result;
    }

    /**
     * Subtract the second number from the first number.
     * 
     * @param float $a The minuend (number to subtract from)
     * @param float $b The subtrahend (number to subtract)
     * @return float The difference of a and b
     * @throws CalculationException If the result overflows or is invalid
     */
    public function subtract(float $a, float $b): float
    {
        $result = $a - $b;
        $this->validateResult($result, 'subtraction');
        return $result;
    }

    /**
     * Multiply two numbers together.
     * 
     * @param float $a The first factor
     * @param float $b The second factor
     * @return float The product of a and b
     * @throws CalculationException If the result overflows or is invalid
     */
    public function multiply(float $a, float $b): float
    {
        $result = $a * $b;
        $this->validateResult($result, 'multiplication');
        return $result;
    }

    /**
     * Divide the first number by the second number.
     * 
     * @param float $a The dividend (number to be divided)
     * @param float $b The divisor (number to divide by)
     * @return float The quotient of a divided by b
     * @throws CalculationException If b is zero or result is invalid
     */
    public function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new CalculationException(
                ERROR_CODES['DIVISION_BY_ZERO'],
                'Division by zero is not allowed',
                [
                    'operands' => [
                        'a' => $a,
                        'b' => $b
                    ]
                ]
            );
        }

        $result = $a / $b;
        $this->validateResult($result, 'division');
        return $result;
    }

    /**
     * Validate calculation result for overflow/underflow and special values.
     * 
     * Ensures that the result is a finite number and within acceptable
     * range limits to prevent issues with infinite or NaN values.
     * 
     * @param float $result The calculation result to validate
     * @param string $operation The operation name for error context
     * @throws CalculationException If the result is invalid
     */
    private function validateResult(float $result, string $operation): void
    {
        if (!is_finite($result)) {
            throw new CalculationException(
                ERROR_CODES['NUMBER_OVERFLOW'],
                'Calculation result is not a finite number',
                [
                    'operation' => $operation,
                    'result' => $result
                ]
            );
        }

        if (abs($result) > MAX_FLOAT_VALUE) {
            throw new CalculationException(
                ERROR_CODES['NUMBER_OVERFLOW'],
                'Calculation result exceeds maximum allowed value',