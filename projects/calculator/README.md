# PHP Calculator API

A RESTful calculator API built in PHP that provides basic arithmetic operations (addition, subtraction, multiplication, division) with comprehensive input validation and JSON responses.

## Features

- **Four arithmetic operations**: Add, subtract, multiply, divide
- **Input validation**: Comprehensive validation for all inputs including type checking, range validation, and special case handling
- **JSON API**: All requests and responses use JSON format
- **Error handling**: Detailed error responses with specific error codes
- **CORS support**: Cross-origin resource sharing for web applications
- **Type safety**: Strict type declarations throughout the codebase

## Requirements

- PHP 8.0 or higher
- Web server (Apache/Nginx) with URL rewriting support
- JSON extension (usually included with PHP)

## Installation

1. **Clone or download the project**:
   ```bash
   git clone <repository-url>
   cd calculator-api
   ```

2. **Set up web server**:
   - For Apache: Ensure `.htaccess` file is in place and mod_rewrite is enabled
   - For Nginx: Configure URL rewriting to point all requests to `public/index.php`

3. **Configure file permissions**:
   ```bash
   chmod -R 755 calculator-api/
   ```

4. **Apache .htaccess configuration** (create in project root):
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ public/index.php [QSA,L]
   ```

5. **Nginx configuration example**:
   ```nginx
   location / {
       try_files $uri $uri/ /public/index.php?$query_string;
   }
   ```

## Usage Examples

### Basic Addition
```bash
curl -X POST http://your-domain/api/v1/calculator/add \
  -H "Content-Type: application/json" \
  -d '{"a": 10.5, "b": 5.2}'
```

**Response:**
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

### JavaScript Example
```javascript
fetch('http://your-domain/api/v1/calculator/multiply', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    a: 4.5,
    b: 2.0
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Python Example
```python
import requests
import json

url = "http://your-domain/api/v1/calculator/divide"
payload = {"a": 15.0, "b": 3.0}
headers = {"Content-Type": "application/json"}

response = requests.post(url, data=json.dumps(payload), headers=headers)
result = response.json()
print(f"Result: {result['result']}")
```

## Testing

Run the test suite using PHPUnit:

```bash
# Install PHPUnit (if not already installed)
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit tests/
```

## Project Structure

```
calculator-api/
├── public/
│   └── index.php              # Application entry point
├── src/
│   ├── Controllers/
│   │   └── CalculatorController.php  # Request handling
│   ├── Services/
│   │   └── CalculatorService.php     # Business logic
│   ├── Validators/
│   │   └── InputValidator.php        # Input validation
│   ├── Exceptions/
│   │   ├── ValidationException.php   # Custom exceptions
│   │   └── CalculationException.php
│   ├── Middleware/
│   │   ├── CorsMiddleware.php        # CORS handling
│   │   └── JsonMiddleware.php        # JSON processing
│   └── Router/
│       └── Router.php               # Request routing
├── config/
│   └── config.php            # Application configuration
├── tests/                    # Test files
├── .htaccess                # Apache URL rewriting
└── README.md                # This file
```

## Configuration

Edit `config/config.php` to customize:

- **CORS settings**: Allowed origins, methods, headers
- **Validation limits**: Maximum values, decimal places
- **Error codes**: Custom error code definitions

---