<?php
// 认证API - 登录、注册、JWT令牌管理
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthAPI {
    private $secretKey = 'youyibang_secret_key_2026';
    
    public function handleRequest($method, $parts) {
        switch ($method) {
            case 'POST':
                if (count($parts) === 2 && $parts[1] === 'login') {
                    return $this->login();
                } elseif (count($parts) === 2 && $parts[1] === 'register') {
                    return $this->register();
                } elseif (count($parts) === 2 && $parts[1] === 'refresh') {
                    return $this->refreshToken();
                }
                break;
            case 'GET':
                if (count($parts) === 2 && $parts[1] === 'profile') {
                    return $this->getProfile();
                }
                break;
        }
        
        http_response_code(404);
        return ['error' => 'Endpoint not found'];
    }
    
    private function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 模拟用户验证（实际应连接数据库）
        $users = [
            ['id' => 1, 'username' => 'doctor1', 'role' => 'doctor', 'name' => '王医生'],
            ['id' => 2, 'username' => 'patient1', 'role' => 'patient', 'name' => '张女士']
        ];
        
        foreach ($users as $user) {
            if ($user['username'] === $data['username'] && $data['password'] === '123456') {
                $token = $this->generateToken($user);
                return [
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'name' => $user['name']
                    ]
                ];
            }
        }
        
        http_response_code(401);
        return ['error' => 'Invalid credentials'];
    }
    
    private function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 实际注册逻辑会保存到数据库
        $userId = mt_rand(1000, 9999);
        
        $token = $this->generateToken([
            'id' => $userId,
            'username' => $data['username'],
            'role' => $data['role'] ?? 'patient',
            'name' => $data['name']
        ]);
        
        return [
            'success' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'username' => $data['username'],
                'role' => $data['role'] ?? 'patient',
                'name' => $data['name']
            ]
        ];
    }
    
    private function generateToken($payload) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // 1小时
        
        $token = JWT::encode([
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'sub' => $payload['id'],
            'username' => $payload['username'],
            'role' => $payload['role'],
            'name' => $payload['name']
        ], $this->secretKey, 'HS256');
        
        return $token;
    }
    
    private function getProfile() {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return [
                'success' => true,
                'user' => [
                    'id' => $decoded->sub,
                    'username' => $decoded->username,
                    'role' => $decoded->role,
                    'name' => $decoded->name
                ]
            ];
        } catch (Exception $e) {
            http_response_code(401);
            return ['error' => 'Invalid token'];
        }
    }
}

$auth = new AuthAPI();
echo json_encode($auth->handleRequest($_SERVER['REQUEST_METHOD'], $parts));
?>