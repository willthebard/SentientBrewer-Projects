<?php

class Auth {
    private static array $config;

    private static function config(): array {
        if (!isset(self::$config)) {
            self::$config = require __DIR__ . '/../config.php';
        }
        return self::$config['jwt'];
    }

    public static function register(string $email, string $password, string $name = ''): array {
        $pdo = DB::getInstance();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
        $stmt->execute([$email, $hash, $name]);

        $userId = (int) $pdo->lastInsertId();
        return [
            'success' => true,
            'token' => self::createToken($userId, $email),
            'user' => ['id' => $userId, 'email' => $email, 'name' => $name],
        ];
    }

    public static function login(string $email, string $password): array {
        $pdo = DB::getInstance();

        $stmt = $pdo->prepare('SELECT id, email, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        return [
            'success' => true,
            'token' => self::createToken($user['id'], $user['email']),
            'user' => ['id' => $user['id'], 'email' => $user['email'], 'name' => $user['name']],
        ];
    }

    public static function validateToken(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        $signature = $parts[2];

        if (!$header || !$payload) return null;

        $validSig = self::base64UrlEncode(
            hash_hmac('sha256', $parts[0] . '.' . $parts[1], self::config()['secret'], true)
        );

        if (!hash_equals($validSig, $signature)) return null;
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    public static function requireAuth(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }

        $payload = self::validateToken($matches[1]);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        return $payload;
    }

    private static function createToken(int $userId, string $email): string {
        $cfg = self::config();

        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'sub' => $userId,
            'email' => $email,
            'iss' => $cfg['issuer'],
            'iat' => time(),
            'exp' => time() + $cfg['expiry'],
        ]));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, $cfg['secret'], true)
        );

        return $header . '.' . $payload . '.' . $signature;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
