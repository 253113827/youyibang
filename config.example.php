<?php
/* 有医帮 - 全局配置 */
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Shanghai');
mb_internal_encoding('UTF-8');

// MySQL（PDO 预处理防注入）
define('DB_HOST', 'localhost');
define('DB_NAME', 'youyibang');
define('DB_USER', 'youyibang');
define('DB_PASS', '<你的数据库密码>');

// 医生登录账号
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'youyibang2026');

// EMR 哈希链初始种子（全表首条 emr_records 的 prev_hash）
define('EMR_SEED', 'youyibang-emr-genesis-2026');

// OpenAI 兼容接口（按顺序故障切换）
$LLM_ENDPOINTS = [
    ['base' => 'http://beiheng.heiyu.space:1314/v1', 'key' => '<你的API-Key>', 'model' => 'glm-5.3-flash'],
    ['base' => 'http://10.0.0.128:19170/v1',        'key' => '<你的API-Key>', 'model' => 'glm-5.3-flash'],
];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function need_login(): void {
    if (empty($_SESSION['login'])) {
        if (is_api()) { http_response_code(401); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => '未登录']); exit; }
        header('Location: login.php'); exit;
    }
}
function is_api(): bool { return str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'api.php'); }

/* 调用大模型（非流式），自动故障切换；失败返回 null */
function llm_chat(array $messages, float $timeout = 120): ?string {
    global $LLM_ENDPOINTS;
    foreach ($LLM_ENDPOINTS as $ep) {
        $ch = curl_init(rtrim($ep['base'], '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)$timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $ep['key'],
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $ep['model'],
                'messages' => $messages,
                'stream' => false,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) continue;
        $j = json_decode($res, true);
        if (isset($j['choices'][0]['message']['content']) && $j['choices'][0]['message']['content'] !== '') {
            return $j['choices'][0]['message']['content'];
        }
    }
    error_log("LLM fail: $err");
    return null;
}

/* 简易 Markdown 渲染（标题/加粗/列表/段落，安全转义） */
function md_render(string $md): string {
    $md = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
    $lines = preg_split('/\r\n|\r|\n/', $md);
    $html = ''; $inList = false;
    foreach ($lines as $line) {
        $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
        if (preg_match('/^#{1,4}\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h3>' . $m[1] . '</h3>';
        } elseif (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . $m[1] . '</li>';
        } elseif (trim($line) === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
        } else {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . $line . '</p>';
        }
    }
    if ($inList) $html .= '</ul>';
    return $html;
}

// ASR 语音转写端点（OpenAI 兼容 /v1/audio/transcriptions，按顺序故障切换）
// 用于「云端分片转写」：浏览器录音每 4s 分片上传，实时出字；
// 解决 Chrome 内置语音识别（依赖 Google 服务）在国内网络静默失败的问题
$ASR_ENDPOINTS = [
    ['base' => 'http://127.0.0.1:8765/v1', 'key' => 'local', 'model' => 'whisper-1'], // 本机 faster-whisper（可选）
    // ['base' => 'http://your-asr-server/v1', 'key' => 'your-key', 'model' => 'whisper-1'],
];
