# Calculator API Reference

## Base URL
```
http://your-domain/api/v1/calculator/
```

## Authentication
No authentication required.

## Content Type
All requests must include:
```
Content-Type: application/json
```

## Endpoints

### 1. Addition
**Endpoint:** `POST /api/v1/calculator/add`

**Description:** Adds two numbers together.

**Request Body:**
```json
{
  "a": number,  // First operand (required)
  "b": number   // Second operand (required)
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "result": 15.7,
  "operation": "addition",
  "operands": {
    "a": 10.5,
    "b": 5.2
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### 2. Subtraction
**Endpoint:** `POST /api/v1/calculator/subtract`

**Description:** Subtracts the second number from the first number.

**Request Body:**
```json
{
  "a": number,  // Minuend (required)
  "b": number   // Subtrahend (required)
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "result": 7.3,
  "operation": "subtraction",
  "operands": {
    "a": 10.5,
    "b": 3.2
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### 3. Multiplication
**Endpoint:** `POST /api/v1/calculator/multiply`

**Description:** Multiplies two numbers together.

**Request Body:**
```json
{
  "a": number,  // First factor (required)
  "b": number   // Second factor (required)
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "result": 9.0,
  "operation": "multiplication",
  "operands": {
    "a": 4.5,
    "b": 2.0
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### 4. Division
**Endpoint:** `POST /api/v1/calculator/divide`

**Description:** Divides the first number by the second number.

**Request Body:**
```json
{
  "a": number,  // Dividend (required)
  "b": number   // Divisor (required, cannot be zero)
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "result": 5.0,
  "operation": "division",
  "operands": {
    "a": 15.0,
    "b": 3.0
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

## Error Responses

All error responses follow this structure:
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {} // Additional error context (optional)
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200  | Success - Operation completed successfully |
| 400  | Bad Request - Invalid input or validation error |
| 404  | Not Found - Endpoint does not exist |
| 405  | Method Not Allowed - HTTP method not supported |
| 415  | Unsupported Media Type - Content-Type not application/json |
| 500  | Internal Server Error - Unexpected server error |

## Error Codes

| Error Code | Description | HTTP Status |
|------------|-------------|-------------|
| `MISSING_PARAMETER` | Required parameter not provided | 400 |
| `INVALID_TYPE` | Parameter is not a valid number | 400 |
| `DIVISION_BY_ZERO` | Attempt to divide by zero | 400 |
| `NUMBER_OVERFLOW` | Result exceeds maximum value | 400 |
| `INVALID_JSON` | Malformed JSON in request | 400 |
| `VALIDATION_ERROR` | General validation error | 400 |
| `ENDPOINT_NOT_FOUND` | Invalid API endpoint | 404 |
| `METHOD_NOT_ALLOWED` | HTTP method not allowed | 405 |
| `CONTENT_TYPE_REQUIRED` | Missing Content-Type header | 415 |
| `INTERNAL_SERVER_ERROR` | Unexpected server error | 500 |

## Error Examples

### Missing Parameter
```json
{
  "success": false,
  "error": {
    "code": "MISSING_PARAMETER",
    "message": "Parameter \"a\" is required",
    "details": {
      "field": "a",
      "issue": "Field \"a\" is required and must be a number"
    }
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Division by Zero
```json
{
  "success": false,
  "error": {
    "code": "DIVISION_BY_ZERO",
    "message": "Division by zero is not allowed",
    "details": {
      "operands": {
        "a": 10,
        "b": 0
      }
    }
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Invalid Type
```json
{
  "success": false,
  "error": {
    "code": "INVALID_TYPE",
    "message": "Parameter \"a\" must be a number",
    "details": {
      "field": "a",
      "value": "abc",
      "type": "string"
    }
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Method Not Allowed
```json
{
  "success": false,
  "error": {
    "code": "METHOD_NOT_ALLOWED",
    "message": "Only POST method(s) allowed for this endpoint",
    "details": {
      "allowed_methods": ["POST"]
    }
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

## Input Validation Rules

### Required Fields
- Both `a` and `b` parameters must be present in all requests
- Request must include `Content-Type: application/json` header

### Data Types
- Parameters must be numeric (integer, float, or scientific notation)
- Negative numbers are accepted
- Infinite or NaN values are rejected

### Special Cases
- **Division**: Parameter `b` cannot be zero
- **All operations**: Results are validated for overflow/underflow
- **Range**: Numbers must be within PHP's float range (`±1.7976931348623157E+308`)

## CORS Support

The API includes CORS headers for cross-origin requests:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Access-Control-Allow-Credentials: true
```

## Response Headers

All responses include these headers:
```
Content-Type: application/json
X-API-Version: 1.0
X-Response-Time: {milliseconds}ms (for successful operations)
```

## Rate Limiting

Currently no rate limiting is implemented. Consider implementing rate limiting in production environments.

---