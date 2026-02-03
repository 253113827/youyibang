<?php
// 飞书消息处理API
require_once '../config/feishu-config.json';

class FeishuAPI {
    private $config;
    
    public function __construct() {
        $this->config = json_decode(file_get_contents('../config/feishu-config.json'), true);
    }
    
    public function handleRequest($method, $parts) {
        switch ($method) {
            case 'POST':
                if (count($parts) === 2 && $parts[1] === 'webhook') {
                    return $this->handleWebhook();
                } elseif (count($parts) === 2 && $parts[1] === 'message') {
                    return $this->sendMessage();
                }
                break;
            case 'GET':
                if (count($parts) === 2 && $parts[1] === 'verify') {
                    return $this->verifySignature();
                }
                break;
        }
        
        http_response_code(404);
        return ['error' => 'Endpoint not found'];
    }
    
    private function verifySignature() {
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce = $_GET['nonce'] ?? '';
        $signature = $_GET['signature'] ?? '';
        
        // 验证签名逻辑
        $signStr = $this->config['verification_token'] . $timestamp . $nonce;
        $expectedSign = sha1($signStr);
        
        if ($signature === $expectedSign) {
            return ['success' => true, 'echostr' => $_GET['echostr'] ?? ''];
        }
        
        http_response_code(401);
        return ['error' => 'Invalid signature'];
    }
    
    private function handleWebhook() {
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        
        // 验证消息签名
        if (!$this->validateMessage($data)) {
            http_response_code(401);
            return ['error' => 'Invalid message signature'];
        }
        
        // 处理不同类型的消息
        $eventType = $data['type'] ?? '';
        
        switch ($eventType) {
            case 'message':
                return $this->handleMessage($data);
            case 'event':
                return $this->handleEvent($data);
            default:
                return ['success' => true, 'message' => 'Unknown event type'];
        }
    }
    
    private function validateMessage($data) {
        // 实际应用中需要实现完整的签名验证
        // 这里简化处理
        return true;
    }
    
    private function handleMessage($data) {
        $message = $data['event']['message'] ?? [];
        $text = $message['content'] ?? '';
        
        // 解析飞书消息内容
        $content = trim(str_replace('"', '', $text));
        
        // 处理关键词
        if (strpos($content, '健康') !== false || strpos($content, '医生') !== false) {
            return $this->respondHealthInfo($content);
        } elseif (strpos($content, '食疗') !== false || strpos($content, '食物') !== false) {
            return $this->respondFoodInfo($content);
        } elseif (strpos($content, '帮助') !== false || strpos($content, '菜单') !== false) {
            return $this->respondHelpMenu();
        }
        
        return $this->respondDefault($content);
    }
    
    private function respondHealthInfo($query) {
        $responses = [
            "有医帮提供专业的中医医疗服务，包括在线问诊、电子处方、健康档案管理等功能。",
            "您可以注册成为医生或患者，享受便捷的互联网医疗服务。",
            "我们的AI辅助诊疗系统可以帮助您进行初步症状分析和中医辨证。",
            "需要了解更多详情，请访问我们的网站或联系客服。"
        ];
        
        return [
            'success' => true,
            'reply' => [
                'msg_type' => 'text',
                'content' => [
                    'text' => $responses[array_rand($responses)]
                ]
            ]
        ];
    }
    
    private function respondFoodInfo($query) {
        $foods = [
            '菠菜' => '养血止血，春季时令蔬菜，适合肝血不足者',
            '韭菜' => '温中行气，助阳散寒，适合脾胃虚寒者',
            '枸杞' => '滋补肝肾，明目益精，适合肝肾不足者',
            '山药' => '健脾益肺，补肾固精，适合脾胃虚弱者'
        ];
        
        $foodNames = array_keys($foods);
        $randomFood = $foodNames[array_rand($foodNames)];
        
        return [
            'success' => true,
            'reply' => [
                'msg_type' => 'text',
                'content' => [
                    'text' => "推荐食物：{$randomFood}\n{$foods[$randomFood]}\n\n有医帮AI食物卡路里追踪器可为您提供个性化食疗建议。"
                ]
            ]
        ];
    }
    
    private function respondHelpMenu() {
        return [
            'success' => true,
            'reply' => [
                'msg_type' => 'interactive',
                'card' => [
                    'config' => ['wide_screen_mode' => true],
                    'elements' => [
                        [
                            'tag' => 'div',
                            'text' => [
                                'content' => '📚 有医帮飞书机器人功能',
                                'tag' => 'lark_md'
                            ]
                        ],
                        [
                            'tag' => 'hr'
                        ],
                        [
                            'tag' => 'div',
                            'text' => [
                                'content' => '👨‍⚕️ 医生服务：在线问诊、处方管理',
                                'tag' => 'lark_md'
                            ]
                        ],
                        [
                            'tag' => 'div',
                            'text' => [
                                'content' => '👩‍🦰 患者服务：健康档案、AI辅助诊疗',
                                'tag' => 'lark_md'
                            ]
                        ],
                        [
                            'tag' => 'div',
                            'text' => [
                                'content' => '🥗 食疗助手：AI食物卡路里追踪、中医食疗推荐',
                                'tag' => 'lark_md'
                            ]
                        ],
                        [
                            'tag' => 'hr'
                        ],
                        [
                            'tag' => 'action',
                            'actions' => [
                                [
                                    'tag' => 'button',
                                    'text' => ['content' => '访问网站', 'tag' => 'plain_text'],
                                    'type' => 'default',
                                    'url' => 'https://youyibang.com'
                                ],
                                [
                                    'tag' => 'button',
                                    'text' => ['content' => '注册医生', 'tag' => 'plain_text'],
                                    'type' => 'primary',
                                    'url' => 'https://youyibang.com/register-doctor'
                                ]
                            ]
                        ]
                    ],
                    'header' => [
                        'title' => ['content' => '有医帮健康助手', 'tag' => 'plain_text'],
                        'template' => 'green'
                    ]
                ]
            ]
        ];
    }
    
    private function respondDefault($content) {
        return [
            'success' => true,
            'reply' => [
                'msg_type' => 'text',
                'content' => [
                    'text' => "您好！我是有医帮健康助手。\n\n您可以询问：\n• 健康咨询\n• 食疗建议\n• 医生服务\n• 或发送“帮助”查看菜单"
                ]
            ]
        ];
    }
    
    private function sendMessage() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $message = [
            'msg_type' => $data['msg_type'] ?? 'text',
            'content' => $data['content'] ?? ['text' => '测试消息']
        ];
        
        // 实际发送到飞书机器人
        $response = $this->sendToFeishuBot($message);
        
        return [
            'success' => true,
            'sent' => $response
        ];
    }
    
    private function sendToFeishuBot($message) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['bot_webhook']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
}

$feishuAPI = new FeishuAPI();
echo json_encode($feishuAPI->handleRequest($_SERVER['REQUEST_METHOD'], $parts));
?>