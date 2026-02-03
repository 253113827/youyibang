<?php
// API入口文件 - 有医帮 RESTful API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理CORS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));

// 基础路由
switch ($parts[0]) {
    case 'auth':
        require_once 'auth.php';
        break;
    case 'doctors':
        require_once 'doctors.php';
        break;
    case 'patients':
        require_once 'patients.php';
        break;
    case 'consultations':
        require_once 'consultations.php';
        break;
    case 'prescriptions':
        require_once 'prescriptions.php';
        break;
    case 'food':
        require_once 'food.php';
        break;
    case 'ai':
        require_once 'ai.php';
        break;
    case 'feishu':
        require_once 'feishu.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>