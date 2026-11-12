<?php
/* 有医帮 - JSON API */
require __DIR__ . '/config.php';
require __DIR__ . '/presets.php';
require __DIR__ . '/rules.php';
need_login();
header('Content-Type: application/json; charset=utf-8');

$act = $_GET['act'] ?? $_POST['act'] ?? '';
$pdo = db();

function fail(string $msg, int $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function ok(array $data = []) { echo json_encode($data + ['ok' => true], JSON_UNESCAPED_UNICODE); exit; }

/* 越权防护：校验 case 归属当前登录用户，否则 403 */
function check_owner(int $cid): void {
    static $uid = null;
    if ($uid === null) $uid = (int)($_SESSION['uid'] ?? 1);
    $st = db()->prepare('SELECT user_id FROM cases WHERE id=?');
    $st->execute([$cid]);
    $row = $st->fetch();
    if (!$row) fail('医案不存在', 404);
    if ((int)$row['user_id'] !== $uid) fail('无权访问该医案', 403);
}

function q_text(array $q): string { return $q[1]; }
function q_is_female(array $q): bool { return !empty($q[2]); }

/* 导出打印版式（A4）
 * 注：OFD-H 版式为 2027 国家标准预留，当前为打印/PDF 过渡方案（window.print 出 PDF）。 */
function export_html(array $c, array $rec, array $emr, array $parsed, array $res): string {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $drugs = $parsed['drugs'] ?? [];
    $rxRows = '';
    foreach ($drugs as $i => $d) {
        $rxRows .= '<tr><td>' . ($i + 1) . '</td><td>' . $e($d['name']) . '</td><td>' .
            ($d['dose'] === null ? '<b style="color:#c00">缺剂量</b>' : $e($d['dose'] . 'g')) . '</td><td></td></tr>';
    }
    $w = '';
    foreach ($res['warnings'] ?? [] as $x) $w .= '<li class="w">' . $e($x) . '</li>';
    $er = '';
    foreach ($res['errors'] ?? [] as $x) $er .= '<li class="e">' . $e($x) . '</li>';
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>有医帮 · 病历导出 #' . $e($c['id']) . '</title>
<style>
@page { size: A4; margin: 18mm 16mm; }
body { font: 12pt/1.7 "Songti SC","SimSun",serif; color:#111; max-width: 178mm; margin: 0 auto; }
h1 { text-align:center; letter-spacing: 8px; font-size: 20pt; margin: 0 0 4px; }
.sub { text-align:center; color:#555; font-size:9pt; border-bottom: 2px solid #111; padding-bottom:8px; margin-bottom: 12px; }
h2 { font-size: 12pt; border-left: 4px solid #111; padding-left: 8px; margin: 14px 0 6px; }
table { width:100%; border-collapse: collapse; margin: 6px 0; }
th, td { border: 1px solid #444; padding: 4px 8px; font-size: 10.5pt; text-align: left; }
pre { white-space: pre-wrap; font: inherit; }
.hash { font-family: monospace; font-size: 9pt; word-break: break-all; background: #f4f4f4; padding: 6px; }
.w { color: #8a6d00; } .e { color: #c00; font-weight: bold; }
.foot { margin-top: 18px; border-top: 1px solid #999; padding-top: 6px; font-size: 8.5pt; color: #666; }
.noprint { text-align:center; margin: 10px 0; }
@media print { .noprint { display: none; } }
</style></head><body>
<div class="noprint"><button onclick="window.print()">🖨 打印 / 另存为 PDF</button></div>
<h1>有医帮 · 中医门诊病历</h1>
<div class="sub">有医帮医生 AI 助理站 · youyibang.com ｜ 电子病历编号 EMR-' . $e($c['id']) . '</div>
<h2>患者信息</h2>
<table><tr><th>姓名</th><td>' . $e($c['patient_name']) . '</td><th>性别</th><td>' . $e($c['gender']) . '</td><th>年龄</th><td>' . $e($c['age']) . '岁</td><th>就诊日期</th><td>' . $e($c['created_at']) . '</td></tr></table>
<h2>病历全文</h2>
<pre>' . $e($rec['content_md'] ?? '（无病历）') . '</pre>
<h2>处方一览</h2>
<table><tr><th>#</th><th>药名</th><th>剂量</th><th>备注</th></tr>' . ($rxRows ?: '<tr><td colspan="4">无处方</td></tr>') . '</table>
<h2>归档信息（防篡改哈希链）</h2>' . (!empty($emr)
        ? '<div class="hash">归档时间：' . $e($emr['created_at']) . ' ｜ 内容哈希：' . $e($emr['content_hash']) . '<br>前序哈希：' . $e($emr['prev_hash']) . '</div>'
        : '<div style="color:#8a6d00">尚未归档（EMR 落库后显示哈希指纹）</div>') . '
' . (($er || $w) ? '<h2>校验提示</h2><ul>' . $er . $w . '</ul>' : '') . '
<div class="foot">免责声明：本病历由 AI 辅助整理并经执业医师审核确认，仅供参考，不构成对任何人的诊疗指令。打印版式说明：OFD-H 版式为 2027 国家标准预留，当前为打印/PDF 过渡方案。有医帮 youyibang.com</div>
</body></html>';
}

/* 按患者性别过滤后的预设问题文本列表 */
function preset_list(int $cid): array {
    $gender = '';
    $st = db()->prepare('SELECT gender FROM cases WHERE id=?'); $st->execute([$cid]);
    $gender = (string)$st->fetchColumn();
    $out = [];
    foreach ($GLOBALS['PRESET_QUESTIONS'] as $q) {
        if (q_is_female($q) && $gender !== '女') continue;
        $out[] = $q;
    }
    return $out;
}

/* 越权防护：校验 visit 归属当前登录医生 */
function check_visit(int $vid): array {
    static $uid = null;
    if ($uid === null) $uid = (int)($_SESSION['uid'] ?? 1);
    $st = db()->prepare('SELECT * FROM visits WHERE id=?');
    $st->execute([$vid]);
    $v = $st->fetch();
    if (!$v) fail('就诊不存在', 404);
    if ((int)$v['doctor_id'] !== $uid) fail('无权访问该就诊', 403);
    return $v;
}

/* 患者历史：该患者既往就诊（含诊断摘要） */
function patient_history(int $patient_id, int $exclude_vid = 0): array {
    $st = db()->prepare('SELECT v.id, v.status, v.registered_at, v.finished_at, v.reg_fee, r.diagnosis
        FROM visits v LEFT JOIN opd_records r ON r.visit_id = v.id
        WHERE v.patient_id = ? AND v.id <> ? ORDER BY v.id DESC LIMIT 20');
    $st->execute([$patient_id, $exclude_vid]);
    return $st->fetchAll();
}

switch ($act) {

/* ---------- 拉取增量（轮询同步） ---------- */
case 'poll': {
    $cid = (int)($_GET['case_id'] ?? 0);
    check_owner($cid);
    $lastMsg = (int)($_GET['last_msg'] ?? 0);
    $lastImg = (int)($_GET['last_img'] ?? 0);
    $recVer  = (int)($_GET['rec_ver'] ?? 0);

    $msgs = $pdo->prepare('SELECT id, role, content, created_at FROM messages WHERE case_id=? AND id>? AND role<>"system" ORDER BY id');
    $msgs->execute([$cid, $lastMsg]);

    $imgs = $pdo->prepare('SELECT id, path, type, ai_result, created_at FROM images WHERE case_id=? AND id>? ORDER BY id');
    $imgs->execute([$cid, $lastImg]);

    $rec = $pdo->prepare('SELECT id, content_md, archived, UNIX_TIMESTAMP(updated_at) AS ver FROM records WHERE case_id=?');
    $rec->execute([$cid]);
    $recRow = $rec->fetch();

    ok([
        'messages' => $msgs->fetchAll(),
        'images'   => $imgs->fetchAll(),
        'record'   => ($recRow && (int)$recRow['ver'] > $recVer) ? ['content_md' => $recRow['content_md'], 'ver' => (int)$recRow['ver'], 'archived' => (int)$recRow['archived']] : null,
        'preset'   => array_map('q_text', preset_list($cid)),
    ]);
}

/* ---------- 发送消息 + AI 回复 ---------- */
case 'send': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $text = trim($_POST['text'] ?? '');
    if ($text === '' && empty($_POST['preset_index'])) fail('内容为空');
    $body = $text;
    if (isset($_POST['preset_index']) && $_POST['preset_index'] !== '') {
        $i = (int)$_POST['preset_index'];
        $list = preset_list($cid);
        if (isset($list[$i])) $body = q_text($list[$i]) . ($text !== '' ? "\n（医生补充：" . $text . "）" : '');
    }
    if ($body === '') fail('内容为空');
    $pdo->prepare('INSERT INTO messages (case_id, role, content) VALUES (?,?,?)')->execute([$cid, 'user', $body]);
    $mid = (int)$pdo->lastInsertId();

    // 组装上下文（最近 30 条 + 图片摘要）
    $hist = $pdo->prepare('SELECT role, content FROM messages WHERE case_id=? ORDER BY id DESC LIMIT 30');
    $hist->execute([$cid]);
    $ctx = array_reverse($hist->fetchAll());
    $imgRes = $pdo->prepare('SELECT type, ai_result FROM images WHERE case_id=? AND ai_result IS NOT NULL AND ai_result<>"" ');
    $imgRes->execute([$cid]);
    foreach ($imgRes->fetchAll() as $im) {
        $ctx[] = ['role' => 'system', 'content' => "（患者图片资料[{$im['type']}]解析：{$im['ai_result']}）"];
    }
    $reply = llm_chat($ctx, 120);
    if ($reply === null) $reply = '（AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）。已记录本次对话。）';
    $pdo->prepare('INSERT INTO messages (case_id, role, content) VALUES (?,?,?)')->execute([$cid, 'assistant', $reply]);
    ok(['user_id' => $mid, 'reply' => $reply]);
}

/* ---------- 上传图片 ---------- */
case 'upload': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $type = $_POST['type'] ?? '其他';
    if (!in_array($type, ['面色', '舌苔', '化验单', '其他'])) $type = '其他';
    if (empty($_FILES['files'])) fail('没有文件');
    // 归一化：字段名为 files（非 files[]）时各键是标量，转成单元素数组，避免 foreach 产生 PHP Warning
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $k) {
        if (isset($_FILES['files'][$k]) && !is_array($_FILES['files'][$k])) {
            $_FILES['files'][$k] = [$_FILES['files'][$k]];
        }
    }
    $dir = __DIR__ . '/uploads/case_' . $cid;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $out = [];
    foreach ($_FILES['files']['name'] as $i => $n) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
        // 真实图片类型校验（防伪装上传），保留扩展名白名单
        $check = @getimagesize($_FILES['files']['tmp_name'][$i]);
        if ($check === false) fail('非有效图片文件');
        $tmp = $_FILES['files']['tmp_name'][$i];
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'])) $ext = 'jpg';
        $name = 'c' . $cid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) continue;
        $rel = 'uploads/case_' . $cid . '/' . $name;
        $pdo->prepare('INSERT INTO images (case_id, path, type) VALUES (?,?,?)')->execute([$cid, $rel, $type]);
        $out[] = ['id' => (int)$pdo->lastInsertId(), 'path' => $rel, 'type' => $type];
    }
    if (!$out) fail('上传失败');
    ok(['images' => $out]);
}

/* ---------- AI 解析图片 ---------- */
case 'ai_image': {
    $iid = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM images WHERE id=?'); $st->execute([$iid]);
    $img = $st->fetch(); if (!$img) fail('图片不存在', 404);
    check_owner((int)$img['case_id']);
    $abs = __DIR__ . '/' . $img['path'];
    if (!is_file($abs)) fail('文件丢失', 404);
    $data = base64_encode(file_get_contents($abs));
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));

    global $IMAGE_PROMPTS;
    $type = $img['type'];
    $prompt = $IMAGE_PROMPTS[$type] ?? $IMAGE_PROMPTS['其他'];

    global $LLM_ENDPOINTS;
    $result = null; $err = '';
    foreach ($LLM_ENDPOINTS as $ep) {
        $ch = curl_init(rtrim($ep['base'], '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $ep['key']],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $ep['model'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:$mime;base64,$data"]],
                    ],
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($res !== false) {
            $j = json_decode($res, true);
            if (!empty($j['choices'][0]['message']['content'])) { $result = $j['choices'][0]['message']['content']; break; }
        }
    }
    if ($result === null) fail('AI 解析失败：' . ($err ?: '模型不可用'), 502);
    $pdo->prepare('UPDATE images SET ai_result=? WHERE id=?')->execute([$result, $iid]);
    ok(['id' => $iid, 'ai_result' => $result]);
}

/* ---------- AI 整理病案 ---------- */
case 'record': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $msgs = $pdo->prepare('SELECT role, content FROM messages WHERE case_id=? AND role<>"system" ORDER BY id');
    $msgs->execute([$cid]);
    $dialog = '';
    foreach ($msgs->fetchAll() as $m) $dialog .= ($m['role'] === 'user' ? '【医生】' : '【AI】') . $m['content'] . "\n";

    $imgs = $pdo->prepare('SELECT type, ai_result FROM images WHERE case_id=? AND ai_result IS NOT NULL AND ai_result<>""');
    $imgs->execute([$cid]);
    $imgTxt = '';
    foreach ($imgs->fetchAll() as $im) $imgTxt .= "· [{$im['type']}] {$im['ai_result']}\n";

    $case = $pdo->prepare('SELECT * FROM cases WHERE id=?'); $case->execute([$cid]);
    $c = $case->fetch();
    $prompt = medical_record_prompt($c, $dialog, $imgTxt);
    $r = llm_chat([['role' => 'user', 'content' => $prompt]], 240);
    if ($r === null) fail('AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）', 502);
    $pdo->prepare('INSERT INTO records (case_id, content_md) VALUES (?,?) ON DUPLICATE KEY UPDATE content_md=VALUES(content_md)')
        ->execute([$cid, $r]);
    ok(['content_md' => $r]);
}

/* ---------- 保存编辑病案 ---------- */
case 'record_save': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $md = $_POST['content_md'] ?? '';
    $pdo->prepare('INSERT INTO records (case_id, content_md) VALUES (?,?) ON DUPLICATE KEY UPDATE content_md=VALUES(content_md)')
        ->execute([$cid, $md]);
    ok(['ver' => time()]);
}

/* ---------- 规则引擎校验 ---------- */
case 'validate': {
    $cid = (int)($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
    check_owner($cid);
    $st = $pdo->prepare('SELECT content_md, is_signed, archived FROM records WHERE case_id=?'); $st->execute([$cid]);
    $rec = $st->fetch();
    if (!$rec || $rec['content_md'] === null || trim((string)$rec['content_md']) === '') fail('尚无病历草稿', 404);
    $parsed = parse_record((string)$rec['content_md']);
    $res = validate_record($parsed);
    ok([
        'parsed' => [
            'chief_complaint' => $parsed['chief_complaint'],
            'present_illness' => $parsed['present_illness'],
            'tongue' => $parsed['tongue'],
            'pulse' => $parsed['pulse'],
            'syndrome' => $parsed['syndrome'],
            'drugs' => $parsed['drugs'],
            'pregnant' => !empty($parsed['pregnant']),
        ],
        'errors' => $res['errors'],
        'warnings' => $res['warnings'],
        'signed' => (bool)$rec['is_signed'],
        'archived' => (bool)$rec['archived'],
    ]);
}

/* ---------- EMR 防篡改归档（追加式，仅 INSERT） ---------- */
case 'commit_emr': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $st = $pdo->prepare('SELECT content_md FROM records WHERE case_id=?'); $st->execute([$cid]);
    $rec = $st->fetch();
    if (!$rec || trim((string)$rec['content_md']) === '') fail('尚无病历草稿可归档', 404);
    $content = (string)$rec['content_md'];
    $prev = $pdo->query('SELECT content_hash FROM emr_records ORDER BY id DESC LIMIT 1')->fetchColumn();
    $prevHash = $prev ?: EMR_SEED;
    $hash = hash('sha256', $content . '|' . $prevHash);
    $uid = (int)($_SESSION['uid'] ?? 1);
    $pdo->prepare('INSERT INTO emr_records (case_id, uid, content_md, content_hash, prev_hash, created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([$cid, $uid, $content, $hash, $prevHash]);
    // lastInsertId 必须在 INSERT 后立即取（紧随其后的 UPDATE 会重置 last_insert_id）
    $emr_id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE records SET archived=1 WHERE case_id=?')->execute([$cid]);
    ok(['emr_id' => $emr_id, 'content_hash' => $hash, 'prev_hash' => $prevHash]);
}

/* ---------- EMR 哈希链完整性验证 ---------- */
case 'verify_emr': {
    $cid = (int)($_GET['case_id'] ?? 0);
    check_owner($cid);
    $rows = $pdo->query('SELECT id, case_id, content_md, content_hash, prev_hash FROM emr_records ORDER BY id')->fetchAll();
    $prev = EMR_SEED; $broken = 0;
    foreach ($rows as $i => $r) {
        if ($r['prev_hash'] !== $prev || hash('sha256', $r['content_md'] . '|' . $r['prev_hash']) !== $r['content_hash']) {
            $broken = (int)$r['id']; break;
        }
        $prev = $r['content_hash'];
    }
    ok(['valid' => $broken === 0, 'broken_at' => $broken, 'total' => count($rows)]);
}

/* ---------- 导出打印版式（A4，OFD-H 2027 国标过渡方案） ---------- */
case 'export': {
    $cid = (int)($_GET['case_id'] ?? 0);
    check_owner($cid);
    $st = $pdo->prepare('SELECT * FROM cases WHERE id=?'); $st->execute([$cid]);
    $c = $st->fetch(); if (!$c) fail('医案不存在', 404);
    $st = $pdo->prepare('SELECT * FROM records WHERE case_id=?'); $st->execute([$cid]);
    $rec = $st->fetch() ?: [];
    $emr = $pdo->prepare('SELECT * FROM emr_records WHERE case_id=? ORDER BY id DESC LIMIT 1');
    $emr->execute([$cid]); $emr = $emr->fetch() ?: [];
    $parsed = parse_record((string)($rec['content_md'] ?? ''));
    $res = validate_record($parsed);
    header('Content-Type: text/html; charset=utf-8');
    echo export_html($c, $rec, $emr, $parsed, $res);
    exit;
}

/* ---------- 追问：推荐诊断与方剂依据 ---------- */
case 'ask': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $q = trim($_POST['q'] ?? '');
    if ($q === '') fail('问题为空');
    $st = $pdo->prepare('SELECT content_md FROM records WHERE case_id=?'); $st->execute([$cid]);
    $rec = $st->fetchColumn();
    $msgs = $pdo->prepare('SELECT role, content FROM messages WHERE case_id=? AND role<>"system" ORDER BY id DESC LIMIT 40');
    $msgs->execute([$cid]);
    $ctx = array_reverse($msgs->fetchAll());
    $ctx[] = ['role' => 'system', 'content' => '当前病案整理结果：' . ($rec ?: '（尚未生成）') . "\n请引用医案上下文回答医生追问，给出推荐诊断与方剂的依据。"];
    $ctx[] = ['role' => 'user', 'content' => $q];
    $r = llm_chat($ctx, 180);
    if ($r === null) fail('AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）', 502);
    $pdo->prepare('INSERT INTO messages (case_id, role, content) VALUES (?,?,?)')->execute([$cid, 'assistant', $r]);
    ok(['reply' => $r]);
}

/* ==================== 门诊接诊工作台（OPD）==================== */

/* 快速接诊：新建患者并挂号（waiting visit） */
case 'create_patient': {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') fail('患者姓名为空');
    $gender = in_array($_POST['gender'] ?? '', ['男', '女', '未知']) ? $_POST['gender'] : '未知';
    $uid = (int)($_SESSION['uid'] ?? 1);
    $pdo->beginTransaction();
    $st = $pdo->prepare('INSERT INTO patients (name, gender, age, birthdate, phone, allergy) VALUES (?,?,?,?,?,?)');
    $st->execute([$name, $gender, trim($_POST['age'] ?? ''), ($_POST['birthdate'] ?? '') ?: null,
                  trim($_POST['phone'] ?? ''), trim($_POST['allergy'] ?? '')]);
    $pid = (int)$pdo->lastInsertId();
    $arc = 'OPD' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE patients SET archive_no=? WHERE id=?')->execute([$arc, $pid]);
    $regFee = (float)($_POST['reg_fee'] ?? 10);
    $pdo->prepare('INSERT INTO visits (patient_id, doctor_id, status, reg_fee) VALUES (?,?,?,?)')
        ->execute([$pid, $uid, 'waiting', $regFee]);
    $vid = (int)$pdo->lastInsertId();
    $pdo->commit();
    ok(['patient_id' => $pid, 'visit_id' => $vid, 'archive_no' => $arc]);
}

/* 编辑患者档案（经 visit 归属校验，仅本人接诊中的患者可改） */
case 'update_patient': {
    /* 校验患者是否属于当前医生的就诊 */
    $pid = (int)($_POST['patient_id'] ?? 0);
    $uid = (int)($_SESSION['uid'] ?? 1);
    $st = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE patient_id=? AND doctor_id=?');
    $st->execute([$pid, $uid]);
    if (!$st->fetchColumn()) fail('无权修改该患者', 403);
    $name = trim($_POST['name'] ?? '');
    if ($name === '') fail('患者姓名为空');
    $gender = in_array($_POST['gender'] ?? '', ['男', '女', '未知']) ? $_POST['gender'] : '未知';
    $pdo->prepare('UPDATE patients SET name=?, gender=?, age=?, phone=?, allergy=? WHERE id=?')
        ->execute([$name, $gender, trim($_POST['age'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['allergy'] ?? ''), $pid]);
    ok(['patient_id' => $pid]);
}

/* 今日队列 + 患者列表 + 历史就诊 */
/* 删除患者（软删除：病历/处方数据保留，仅从列表隐藏） */
case 'del_patient': {
    $pid = (int)($_POST['patient_id'] ?? 0);
    if ($pid <= 0) fail('缺少患者 ID');
    $pdo->prepare('UPDATE patients SET is_deleted = 1 WHERE id = ?')->execute([$pid]);
    ok(['deleted' => $pid]);
}

/* 医案更新（index.php 编辑） */
case 'case_update': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $pdo->prepare('UPDATE cases SET patient_name=?, gender=?, age=?, chief_complaint=? WHERE id=?')
        ->execute([trim($_POST['patient_name'] ?? ''), ($_POST['gender'] ?? '未知') ?: '未知',
                   trim($_POST['age'] ?? ''), trim($_POST['chief_complaint'] ?? ''), $cid]);
    ok(['updated' => $cid]);
}

/* 删除医案（软删除） */
case 'case_del': {
    $cid = (int)($_POST['case_id'] ?? 0);
    check_owner($cid);
    $pdo->prepare('UPDATE cases SET is_deleted = 1 WHERE id = ?')->execute([$cid]);
    ok(['deleted' => $cid]);
}

case 'queue': {
    $uid = (int)($_SESSION['uid'] ?? 1);
    $today = $pdo->prepare("SELECT v.id, v.status, v.reg_fee, v.registered_at, v.case_id, p.id AS patient_id, p.name, p.gender, p.age, p.phone
        FROM visits v JOIN patients p ON p.id = v.patient_id
        WHERE v.doctor_id = ? AND DATE(v.registered_at) = CURDATE() AND p.is_deleted = 0
        ORDER BY FIELD(v.status,'waiting','in_visit','done'), v.id DESC");
    $today->execute([$uid]);
    $hist = $pdo->prepare("SELECT v.id, v.finished_at, r.diagnosis, p.name, v.doctor_id AS uid
        FROM visits v JOIN patients p ON p.id = v.patient_id
        LEFT JOIN opd_records r ON r.visit_id = v.id
        WHERE v.doctor_id = ? AND v.status = 'done' AND DATE(v.registered_at) < CURDATE() AND p.is_deleted = 0
        ORDER BY v.finished_at DESC LIMIT 30");
    $hist->execute([$uid]);
    ok(['today' => $today->fetchAll(), 'history' => $hist->fetchAll()]);
}

/* 保存结构化病历（opd_records + 可选处方批量保存） */
case 'save_opd': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    $aux = $_POST['auxiliary_exam'] ?? '[]';
    $trt = $_POST['treatments'] ?? '[]';
    foreach ([$aux, $trt] as $raw) { json_decode($raw); if (json_last_error() !== JSON_ERROR_NONE) fail('JSON 格式错误'); }
    $total = 0.0;
    if (!empty($_POST['rx_items'])) {
        $rx = json_decode($_POST['rx_items'], true);
        if (!is_array($rx)) fail('处方 JSON 格式错误');
        foreach ($rx as $it) {
            if (!isset($it['name'])) fail('处方条目缺药名');
            $total += (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0);
        }
    }
    /* 计费口径：总额 = 药品 + 辅助检查(单价×次数) + 治疗项目(单价×次数)；字段缺失/非法按 0 计 */
    $auxArr = json_decode($aux, true);
    $trtArr = json_decode($trt, true);
    foreach (is_array($auxArr) ? $auxArr : [] as $a) {
        $total += (float)($a['price'] ?? 0) * (float)($a['times'] ?? 1);
    }
    foreach (is_array($trtArr) ? $trtArr : [] as $t) {
        $total += (float)($t['price'] ?? 0) * (float)($t['times'] ?? 1);
    }
    /* 中医四诊勾选（JSON，可选；非法 JSON 存 NULL 不阻断保存） */
    $tcmRaw = trim($_POST['tcm_checklist'] ?? '');
    $tcm = null;
    if ($tcmRaw !== '') { $tcmDec = json_decode($tcmRaw, true); if (is_array($tcmDec)) $tcm = json_encode($tcmDec, JSON_UNESCAPED_UNICODE); }
    $pdo->prepare('INSERT INTO opd_records (visit_id, chief_complaint, present_illness, allergy_history, physical_exam, diagnosis, auxiliary_exam, treatments, notes, tcm_checklist)
        VALUES (?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE chief_complaint=VALUES(chief_complaint), present_illness=VALUES(present_illness),
        allergy_history=VALUES(allergy_history), physical_exam=VALUES(physical_exam), diagnosis=VALUES(diagnosis),
        auxiliary_exam=VALUES(auxiliary_exam), treatments=VALUES(treatments), notes=VALUES(notes), tcm_checklist=VALUES(tcm_checklist)')
        ->execute([$vid, trim($_POST['chief_complaint'] ?? ''), trim($_POST['present_illness'] ?? ''),
                   trim($_POST['allergy_history'] ?? ''), trim($_POST['physical_exam'] ?? ''),
                   trim($_POST['diagnosis'] ?? ''), $aux, $trt, trim($_POST['notes'] ?? ''), $tcm]);
    if (!empty($_POST['rx_items'])) {
        $pdo->prepare('DELETE FROM prescriptions WHERE visit_id=?')->execute([$vid]);
        foreach (['western', 'chinese', 'infusion'] as $i => $t) {
            $items = array_values(array_filter($rx, fn($x) => ($x['type'] ?? 'western') === $t));
            if (!$items) continue;
            $t_total = 0.0;
            foreach ($items as $it) $t_total += (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0);
            $pdo->prepare('INSERT INTO prescriptions (visit_id, type, items, total) VALUES (?,?,?,?)')
                ->execute([$vid, $t, json_encode($items, JSON_UNESCAPED_UNICODE), $t_total]);
        }
    }
    $pdo->prepare("UPDATE visits SET status = IF(status='waiting','in_visit',status) WHERE id=?")->execute([$vid]);
    ok(['visit_id' => $vid, 'total' => round($total, 2)]);
}

/* 读取接诊数据：患者 + 病历 + 处方 + 历史就诊 */
case 'get_opd': {
    $vid = (int)($_GET['visit_id'] ?? 0);
    check_visit($vid);
    $st = $pdo->prepare('SELECT p.*, v.status, v.reg_fee, v.registered_at, v.case_id, v.doctor_id
        FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?');
    $st->execute([$vid]);
    $patient = $st->fetch();
    if (!$patient) fail('就诊不存在', 404);
    $st = $pdo->prepare('SELECT * FROM opd_records WHERE visit_id=?'); $st->execute([$vid]);
    $rec = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT id, type, items, total FROM prescriptions WHERE visit_id=? ORDER BY id'); $st->execute([$vid]);
    $rx = [];
    foreach ($st->fetchAll() as $r) { $r['items'] = json_decode($r['items'], true); $rx[$r['type']] = $r; }
    ok(['patient' => $patient, 'record' => $rec, 'prescriptions' => $rx,
        'history' => patient_history((int)$patient['id'], $vid)]);
}

/* 药品模糊搜索（名称 LIKE，支持拼音简拼可选） */
case 'search_drug': {
    $kw = trim($_GET['kw'] ?? '');
    if ($kw === '') ok(['drugs' => []]);
    $st = $pdo->prepare("SELECT id, name, spec, manufacturer, type, unit, default_usage, default_freq, price
        FROM drug_dict WHERE name LIKE ? OR spec LIKE ? ORDER BY name LIMIT 15");
    $st->execute(["%$kw%", "%$kw%"]);
    ok(['drugs' => $st->fetchAll()]);
}

/* 诊疗项目搜索 */
case 'search_proc': {
    $kw = trim($_GET['kw'] ?? '');
    if ($kw === '') ok(['procs' => []]);
    $st = $pdo->prepare('SELECT id, name, type, price FROM procedure_dict WHERE name LIKE ? ORDER BY name LIMIT 15');
    $st->execute(["%$kw%"]);
    ok(['procs' => $st->fetchAll()]);
}

/* AI 智能诊断：按 research-aidx.md 提示词（CliniQ 思路中文版）返回鉴别诊断；上游离线优雅降级 */
case 'ai_dx': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    $mode = ($_POST['mode'] ?? 'western') === 'tcm' ? 'tcm' : 'western';
    $st = $pdo->prepare('SELECT p.name, p.gender, p.age, r.chief_complaint, r.present_illness, r.physical_exam, r.allergy_history, r.diagnosis
        FROM visits v JOIN patients p ON p.id = v.patient_id LEFT JOIN opd_records r ON r.visit_id = v.id WHERE v.id = ?');
    $st->execute([$vid]);
    $c = $st->fetch();
    /* 优先采用前端传来的【实时采集病情】，未填的用数据库兜底 */
    $cc = trim($_POST['cc'] ?? '') ?: (string)($c['chief_complaint'] ?? '');
    $pi = trim($_POST['pi'] ?? '') ?: (string)($c['present_illness'] ?? '');
    $pe = trim($_POST['pe'] ?? '') ?: (string)($c['physical_exam'] ?? '');
    $al = trim($_POST['al'] ?? '') ?: (string)($c['allergy_history'] ?? '');
    $baseInfo = "【主诉】{$cc}\n【现病史】{$pi}\n【体格检查】{$pe}\n【既往史/过敏史】{$al}\n【年龄】{$c['age']} 【性别】{$c['gender']}";
    $ck = 'aidx_' . $mode . '_' . md5($baseInfo);
    $cached = ai_cache_get($ck);
    if ($cached !== null) ok($cached);
    if ($mode === 'tcm') {
        $task = <<<T
# 任务（中医辨证论治模式）
基于以上信息进行中医辨证：给出 3-5 个可能证型，按可能性从高到低排列。处方必须依据经典方剂（《方剂学》新世纪教材、《伤寒论》《金匮要略》等经典），证型判断依据《中医诊断学》《中医内科学》教材，并在字段中标注出处。严禁编造方剂、药物与出处。

# 输出要求（严格 JSON）
{"differentials":[{"diagnosis":"证型名（如：肝郁脾虚证）","supporting":"辨证依据（含舌脉要点）","against":"不符点（可空）","associated_symptoms":["伴随症状1","伴随症状2"],"physical_findings":"相关望闻切诊所见","suggested_tests":["建议检查1"],"confidence":"较可能/可能/需警惕但不能除外","red_flags":"须立即转诊的情况（可空）","formula":"推荐方剂名（如：逍遥散合四君子汤加减）","formula_source":"方剂出处（如：《方剂学》新世纪第5版／《伤寒论》）"}],"missing_info":["建议补充四诊信息"],"disclaimer":"本结果由 AI 依据教材与经典生成，仅供临床参考，不构成诊断或医疗建议，请以执业中医师面诊判断为准。"}
T;
    } else {
        $task = <<<T
# 任务（西医鉴别诊断模式）
基于以上信息，给出 3-5 个鉴别诊断，按可能性从高到低排列。诊断附 ICD-10 编码；治疗方案须依据《内科学》人卫版教材、相关临床诊疗指南、《中国药典》，并在 note 中标注出处；严禁编造药物与出处。

# 输出要求（严格 JSON）
{"differentials":[{"diagnosis":"诊断名称（规范中文病名，附 ICD-10 编码）","supporting":"诊断依据","against":"不符点（可空）","associated_symptoms":["伴随症状1","伴随症状2"],"physical_findings":"相关查体所见","suggested_tests":["建议检查1","建议检查2"],"confidence":"较可能/可能/需警惕但不能除外（禁止数字概率）","red_flags":"须立即转诊的情况（可空）"}],"missing_info":["建议补充采集的信息"],"disclaimer":"本结果由 AI 依据教材与指南生成，仅供临床参考，不构成诊断或医疗建议，请以医生面诊判断为准。"}
T;
    }
    $modeLabel = $mode === 'tcm' ? '中医' : '西医';
    $prompt = <<<P
# 角色
你是一名经验丰富的全科临床顾问（{$modeLabel}视角），为基层诊所医生提供诊疗参考。你的输出仅供参考，不构成诊断或医疗建议，最终诊疗决策由接诊医生做出。所有医学内容必须有明确依据（教材/经典/药典/指南），严禁编造。

# 输入
{$baseInfo}

{$task}

# 规则
1. 只依据输入信息推理，不得虚构；信息不足写进 missing_info。
2. 必须包含至少一条"需警惕但不能除外"的严重可治疾病（或急危重证型）。
3. 涉及急危重指征时在 red_flags 写出转诊建议。
4. associated_symptoms 为常见伴随症状词组（每条 2-6 字），供医生点击快捷写入病历。
5. 只输出 JSON 本体，不要 markdown 代码块包裹。disclaimer 字段原样保留。
P;
    $r = llm_chat([['role' => 'user', 'content' => $prompt]], 150);
    if ($r === null) ok(['differentials' => [], 'missing_info' => [], 'note' => 'AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）']);
    $s = strpos($r, '{'); $e = strrpos($r, '}');
    $j = ($s !== false && $e !== false) ? json_decode(substr($r, $s, $e - $s + 1), true) : null;
    $dx = is_array($j) && isset($j['differentials']) && is_array($j['differentials']) ? array_slice($j['differentials'], 0, 5) : [];
    if (!$dx) ok(['differentials' => [], 'missing_info' => [], 'note' => 'AI 返回格式异常，请稍后重试']);
    $out = ['differentials' => $dx, 'missing_info' => (array)($j['missing_info'] ?? []),
        'disclaimer' => $j['disclaimer'] ?? '本结果由 AI 生成，仅供临床参考，不构成诊断或医疗建议，请以医生面诊判断为准。'];
    ai_cache_set($ck, $out);
    ok($out);
}

/* 采纳诊断时按需生成治疗方案（短输出，避免长响应截断） */
case 'ai_treatment': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    $dx = trim($_POST['diagnosis'] ?? '');
    if ($dx === '') fail('缺少诊断');
    $type = ($_POST['type'] ?? 'western') === 'tcm' ? 'tcm' : 'western';
    $st = $pdo->prepare('SELECT p.age, p.gender, r.allergy_history FROM visits v JOIN patients p ON p.id = v.patient_id LEFT JOIN opd_records r ON r.visit_id = v.id WHERE v.id = ?');
    $st->execute([$vid]);
    $c = $st->fetch();
    /* 优先采用前端传来的【实时采集病情】（最新主诉/现病史/查体/过敏史），数据库仅兜底 */
    $cc = trim($_POST['cc'] ?? '');
    $pi = trim($_POST['pi'] ?? '');
    $pe = trim($_POST['pe'] ?? '');
    $al = trim($_POST['al'] ?? '');
    if ($al === '') $al = (string)($c['allergy_history'] ?? '');
    if ($cc === '') $cc = '未填写';
    if ($pi === '') $pi = '未填写';
    if ($pe === '') $pe = '未填写';
    $ctxBlock = "【主诉】{$cc}\n【现病史】{$pi}\n【体格检查】{$pe}\n【过敏史】{$al}";
    if ($type === 'tcm') {
        $prompt = <<<P
# 角色
你是一名中医师，为下述诊断拟定【中药饮片处方模板】（辨证参考主诉症状），供接诊医师采纳后核对加减。输出仅供参考。处方必须出自经典方剂或《中医内科学》《方剂学》教材、《中国药典》2020版，严禁编造药味与剂量。

# 患者
【年龄】{$c['age']} 【性别】{$c['gender']}
{$ctxBlock}

# 诊断（西医病名，请按中医辨证思路拟定对应治法与方药）
{$dx}

# 输出要求（严格 JSON，只输出 JSON 本体不要代码块）
{"chinese_rx":[{"name":"饮片名","qty_g":10,"prep":"炮制（可空，如清炒/蜜炙）","decoct":"特殊煎法（可空，如先煎/后下/包煎）","note":"配伍用意","source":"出处（如：《方剂学》逍遥散／《中国药典》2020版）"}],"formula":"推荐方剂名（如：龙胆泻肝汤加减）","formula_source":"方剂出处（如：《方剂学》新世纪第5版／《太平惠民和剂局方》）","physio":[{"type":"针灸/艾灸/刮痧/拔罐/推拿","name":"方案名（如：针灸疏风散寒）","detail":"具体穴位或部位与操作（如：风池、风府、合谷、列缺，平补平泻，留针20分钟）","course":"疗程（如：每日1次×5天）","source":"出处（如：《针灸学》新世纪第5版）"}],"aux_tests":[{"name":"检查项目名","times":1}],"notes":"医嘱：忌口/煎服法等"}

# 规则
1. 6-12 味饮片，剂量以克为单位符合《中国药典》2020版常规用量；严守十八反十九畏；与过敏史冲突时换药并在 note 说明。
2. 每味药 source 标注出处；formula 与 formula_source 必填。note/source/医嘱等文本字段尽量精简（每处≤20字），加快生成。
3. aux_tests 为建议的辅助检查（0-3 项）。
4. physio 为该证的中医理疗方案（1-3 项，针灸须列具体穴位与手法，出处标《针灸学》等教材；与病情不符的理疗类型不要输出）。
P;
    } else {
        $prompt = <<<P
# 角色
你是一名基层诊所全科医生，为下述诊断制定【成人常规西医治疗方案模板】，供接诊医生采纳后核对修改。输出仅供参考。药物必须依据《内科学》人卫版教材、相关临床诊疗指南、《中国药典》2020版，严禁编造。

# 患者
【年龄】{$c['age']} 【性别】{$c['gender']}
{$ctxBlock}

# 诊断
{$dx}

# 输出要求（严格 JSON，只输出 JSON 本体不要代码块）
{"western_rx":[{"name":"通用药名","spec":"常见规格","usage":"口服","freq":"bid","dose":"1","dose_unit":"片","days":3,"qty":1,"qty_unit":"盒","note":"用药交代（可空）","source":"出处（如：《内科学》人卫第10版／相关指南名）"}],"aux_tests":[{"name":"检查项目名","times":1}],"notes":"注意事项，与过敏史冲突时在此提示"}

# 规则
1. 2-4 种常用药物，剂量符合成人常规；与过敏史冲突时换药并在 note 说明。
2. 每药 source 标注出处。note 等文本字段尽量精简（≤20字），加快生成。
3. aux_tests 为该诊断建议的辅助检查（0-3 项）。
P;
    }
    /* 缓存键：诊断+类型+实时病情（同一病情 15 分钟内秒回） */
    $ck = 'trt_' . $type . '_' . md5($dx . '|' . ($cc ?? '') . '|' . ($pi ?? '') . '|' . ($c['age'] ?? '') . ($c['gender'] ?? ''));
    $cached = ai_cache_get($ck);
    if ($cached !== null) ok($cached);
    $r = llm_chat([['role' => 'user', 'content' => $prompt]], 120);
    if ($r === null) fail('AI 服务暂不可用，请稍后重试');
    $s = strpos($r, '{'); $e = strrpos($r, '}');
    $j = ($s !== false && $e !== false) ? json_decode(substr($r, $s, $e - $s + 1), true) : null;
    if (!is_array($j)) fail('AI 返回格式异常，请重试');
    if ($type === 'tcm') {
        $out = ['chinese_rx' => (array)($j['chinese_rx'] ?? []), 'formula' => (string)($j['formula'] ?? ''),
            'physio' => (array)($j['physio'] ?? []),
            'aux_tests' => (array)($j['aux_tests'] ?? []), 'notes' => (string)($j['notes'] ?? ''), 'type' => 'tcm'];
        ai_cache_set($ck, $out);
        ok($out);
    }
    $out = ['western_rx' => (array)($j['western_rx'] ?? []), 'aux_tests' => (array)($j['aux_tests'] ?? []),
        'notes' => (string)($j['notes'] ?? ''), 'type' => 'western'];
    ai_cache_set($ck, $out);
    ok($out);
}

/* 问诊录音归档：上传保存（医疗录音留存，可回放/修正转写） */
case 'upload_recording': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) fail('缺少音频');
    $dur = (int)($_POST['duration'] ?? 0);
    $dir = __DIR__ . '/uploads/audio/visit_' . $vid;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = pathinfo($_FILES['audio']['name'] ?? '', PATHINFO_EXTENSION) ?: 'webm';
    $ext = preg_replace('/[^a-z0-9]/i', '', strtolower($ext)) ?: 'webm';
    $fname = date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $rel = 'uploads/audio/visit_' . $vid . '/' . $fname;
    if (!move_uploaded_file($_FILES['audio']['tmp_name'], __DIR__ . '/' . $rel)) fail('保存音频失败');
    $st = $pdo->prepare('INSERT INTO visit_recordings (visit_id, file_path, duration_sec) VALUES (?,?,?)');
    $st->execute([$vid, $rel, $dur]);
    $rid = (int)$pdo->lastInsertId();
    ok(['rec_id' => $rid, 'url' => $rel]);
}

/* 录音列表（含转写，供回放/修正） */
case 'recording_list': {
    $vid = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
    check_visit($vid);
    $st = $pdo->prepare('SELECT id, file_path, duration_sec, transcript, segments, created_at FROM visit_recordings WHERE visit_id = ? ORDER BY id DESC');
    $st->execute([$vid]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['url'] = $r['file_path']; unset($r['file_path']); }
    ok(['recordings' => $rows]);
}

/* 保存/修正归档转写 */
case 'save_recording_transcript': {
    $rid = (int)($_POST['rec_id'] ?? 0);
    $txt = $_POST['transcript'] ?? '';
    if ($rid <= 0) fail('缺少录音 ID');
    $st = $pdo->prepare('SELECT id FROM visit_recordings WHERE id = ?');
    $st->execute([$rid]);
    if (!$st->fetch()) fail('录音不存在', 404);
    /* 分段转写（逐句还原+回放）：JSON [{offset:秒,text}] */
    $segs = $_POST['segments'] ?? '';
    if ($segs !== '') {
        $j = json_decode($segs, true);
        if (is_array($j)) {
            $clean = [];
            foreach ($j as $s) {
                if (!is_array($s) || !isset($s['text'])) continue;
                $clean[] = ['offset' => (float)($s['offset'] ?? 0), 'text' => (string)$s['text']];
            }
            $segs = json_encode($clean, JSON_UNESCAPED_UNICODE);
        } else $segs = '';
    }
    $st = $pdo->prepare('UPDATE visit_recordings SET transcript = ?, segments = ? WHERE id = ?');
    $st->execute([$txt, $segs, $rid]);
    ok(['rec_id' => $rid]);
}

/* 整段录音精转：录音结束后用整段音频重新识别（精度高于 2s 分片），供对照修正 */
case 'asr_full': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    $ASR_FULL_ENDPOINTS = [
        ['base' => 'http://127.0.0.1:18792/v1', 'key' => 'local', 'model' => 'r2t2'],      // M5 MAX R2T2（隧道）
        ['base' => 'http://127.0.0.1:18765/v1', 'key' => 'local', 'model' => 'whisper-1'], // 服务器本机 whisper 兜底
    ];
    /* 优先用已归档的录音文件（rec_id）：服务器本地读取，免手机二次上传大文件 */
    $rid = (int)($_POST['rec_id'] ?? 0);
    if ($rid > 0) {
        $st = $pdo->prepare('SELECT file_path FROM visit_recordings WHERE id = ?');
        $st->execute([$rid]);
        $fp = $st->fetch()['file_path'] ?? '';
        $abs = realpath(__DIR__ . '/' . ltrim($fp, '/'));
        if ($abs && str_starts_with($abs, __DIR__ . '/uploads/') && is_file($abs)) {
            $_FILES['audio'] = ['tmp_name' => $abs, 'error' => UPLOAD_ERR_OK];
        }
    }
    if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) fail('缺少音频');
    $tmp = $_FILES['audio']['tmp_name'];
    $err = '';
    foreach ($ASR_FULL_ENDPOINTS as $ep) {
        $ch = curl_init(rtrim($ep['base'], '/') . '/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600, /* 整段精转耗时长（约 1/16 实时） */
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROXY => '',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $ep['key']],
            CURLOPT_POSTFIELDS => [
                'model' => $ep['model'] ?? 'whisper-1',
                'audio' => new CURLFile($tmp, 'audio/webm', 'full.webm'),
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($body !== false && $http === 200) {
            $j = json_decode($body, true);
            if (isset($j['text']) && trim((string)$j['text']) !== '') ok(['text' => trim((string)$j['text'], " |　")]);
        }
        $err = $cerr ?: "HTTP {$http} {$body}";
    }
    error_log("[yyb-asrfull] FAIL visit={$vid} size=" . filesize($tmp) . " err=" . substr($err, 0, 300));
    fail('整段精转暂不可用：' . $err);
}

/* 处方安全审查：规则引擎（rules.php 配对表+过敏交叉）优先，AI 补充；warnings 带 source 字段 */
case 'ai_rx_review': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    $v = check_visit($vid);
    $st = $pdo->prepare('SELECT p.name, p.gender, p.age, p.allergy, r.diagnosis, r.notes
        FROM visits v JOIN patients p ON p.id = v.patient_id LEFT JOIN opd_records r ON r.visit_id = v.id WHERE v.id = ?');
    $st->execute([$vid]);
    $c = $st->fetch();
    $st = $pdo->prepare('SELECT items FROM prescriptions WHERE visit_id=?'); $st->execute([$vid]);
    $items = [];
    foreach ($st->fetchAll() as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $it) $items[] = $it;
    if (!$items) fail('暂无处方可审查');
    /* ---- 规则引擎层（确定性，优先级最高）---- */
    $alerts = [];
    $allergy = (string)($c['allergy'] ?? '');
    if ($allergy !== '') {
        foreach ($items as $it) {
            $n = (string)($it['name'] ?? '');
            // 青霉素过敏 → 阿莫西林/头孢 交叉；其他过敏原直接子串匹配
            if (mb_strpos($allergy, '青霉素') !== false && preg_match('/阿莫西林|头孢|青霉素/', $n)) {
                $alerts[] = ['type' => 'allergy', 'drug' => $n, 'title' => "{$n} 与患者青霉素过敏史可能交叉过敏", 'mechanism' => 'β-内酰胺类交叉反应', 'suggestion' => '停用，换用大环内酯类等替代药'];
            } elseif ($allergy !== '' && $n !== '' && mb_strpos($n, $allergy) !== false) {
                $alerts[] = ['type' => 'allergy', 'drug' => $n, 'title' => "{$n} 与患者过敏史「{$allergy}」相关", 'mechanism' => '过敏原匹配', 'suggestion' => '停用并核实过敏史'];
            }
        }
    }
    $tcmItems = array_values(array_filter($items, fn($x) => ($x['type'] ?? 'western') === 'chinese'));
    if ($tcmItems) {
        require_once __DIR__ . '/rules.php';
        $parsed = ['ok' => true, 'chief_complaint' => 'x', 'syndrome' => 'x', 'present_illness' => 'x',
                   'tongue' => '', 'pulse' => '', 'pregnant' => (bool)preg_match('/妊娠|怀孕|孕[0-9一二三四五六七八九十周月]|孕妇/', (string)($c['notes'] ?? '')),
                   'drugs' => array_map(fn($t) => ['name' => (string)$t['name'], 'dose' => 1.0], $tcmItems)];
        $res = validate_record($parsed);
        foreach ($res['warnings'] as $w) {
            if (preg_match('/十八反|十九畏|妊娠|药典/', $w)) $alerts[] = ['type' => 'tcm', 'drug' => '', 'title' => $w, 'mechanism' => '中药配伍/妊娠/药典规则', 'suggestion' => '请复核处方'];
        }
    }
    foreach ($alerts as &$a) $a += ['severity' => 'high', 'source' => 'rule_engine'];
    unset($a);
    /* ---- AI 补充层 ---- */
    $rxText = '';
    foreach ($items as $i => $it) $rxText .= ($i + 1) . '. ' . $it['name'] . ' ' . ($it['spec'] ?? '') . ' 单次' . ($it['dose'] ?? '') . ' ' . ($it['freq'] ?? '') . ' ' . ($it['usage'] ?? '') . ' ' . ($it['days'] ?? '') . "天\n";
    $ruleJson = json_encode($alerts, JSON_UNESCAPED_UNICODE);
    $prompt = <<<P
# 角色
你是处方安全审查助手。你的输出仅供参考，最终处方决定权与责任在开方医生。

# 输入
【患者】年龄 {$c['age']}，性别 {$c['gender']}
【过敏史】{$c['allergy']}
【诊断】{$c['diagnosis']}
【拟开处方】
$rxText
# 系统规则命中结果（来自规则引擎，优先级最高）
$ruleJson

# 任务
逐条审查拟开处方，输出 warnings。审查维度：过敏冲突（含交叉过敏）、剂量异常、配伍禁忌/重复用药、特殊人群、中药十八反十九畏。

# 输出要求（严格 JSON）
{"pass":true,"warnings":[{"severity":"critical|high|medium|info","type":"allergy|dosage|interaction|special_population|tcm","drug":"药名","title":"一句话标题","mechanism":"机制","suggestion":"建议","source":"ai_review"}],"checked_scope":"已审查 N 条处方","disclaimer":"本审查由 AI 与规则库生成，仅供开方医生参考，不能替代医生的临床判断与药典核对，开方前请再次核对药品说明书。"}

# 规则
1. rule_engine_alerts 中已有的命中必须原样保留在 warnings 中（source=rule_engine），不得删除或降级。
2. 无法核实的剂量范围不得报 dosage warning。
3. 输出 JSON 之外不得有任何解释文字；disclaimer 字段原样保留。
P;
    $r = llm_chat([['role' => 'user', 'content' => $prompt]], 120);
    $aiWarnings = [];
    if ($r !== null) {
        $s = strpos($r, '{'); $e = strrpos($r, '}');
        $j = ($s !== false && $e !== false) ? json_decode(substr($r, $s, $e - $s + 1), true) : null;
        if (is_array($j) && isset($j['warnings']) && is_array($j['warnings'])) {
            foreach ($j['warnings'] as $w) {
                if (($w['source'] ?? 'ai_review') === 'rule_engine') continue; // 规则命中不重复
                $aiWarnings[] = $w + ['source' => 'ai_review'];
            }
        }
    }
    ok(['pass' => !array_filter($alerts, fn($a) => $a['severity'] === 'critical') && empty($aiWarnings),
        'warnings' => array_merge($alerts, $aiWarnings),
        'note' => $r === null ? 'AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效），仅返回规则引擎结果' : 'AI 建议仅供参考',
        'disclaimer' => '本审查由 AI 与规则库生成，仅供开方医生参考，不能替代医生的临床判断与药典核对，开方前请再次核对药品说明书。']);
}

/* 诊断字典搜索（diag_dict，来源 OpenHIS xt_zd） */
case 'search_diag': {
    $kw = trim($_GET['kw'] ?? '');
    if ($kw === '') ok(['diags' => []]);
    $st = $pdo->prepare('SELECT id, zd_code, name, name_tcm, icd_code, category FROM diag_dict
        WHERE name LIKE ? OR name_tcm LIKE ? OR icd_code LIKE ? OR zd_code LIKE ? ORDER BY category DESC, id LIMIT 15');
    $st->execute(["%$kw%", "%$kw%", "%$kw%", "%$kw%"]);
    ok(['diags' => $st->fetchAll()]);
}

/* 处方模板：保存 / 列表（按医生） */
case 'save_rx_template': {
    $uid = (int)($_SESSION['uid'] ?? 1);
    $name = trim($_POST['name'] ?? '');
    if ($name === '') fail('模板名为空');
    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) fail('items JSON 格式错误');
    $pdo->prepare('INSERT INTO rx_templates (doctor_id, name, items) VALUES (?,?,?)')
        ->execute([$uid, $name, json_encode($items, JSON_UNESCAPED_UNICODE)]);
    ok(['id' => (int)$pdo->lastInsertId()]);
}
case 'get_templates': {
    $uid = (int)($_SESSION['uid'] ?? 1);
    $st = $pdo->prepare('SELECT id, name, items FROM rx_templates WHERE doctor_id=? ORDER BY id DESC LIMIT 50');
    $st->execute([$uid]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['items'] = json_decode($r['items'], true);
    ok(['templates' => $rows]);
}

/* 结束接诊 */
case 'finish_visit': {
    $vid = (int)($_POST['visit_id'] ?? 0);
    check_visit($vid);
    $pdo->prepare("UPDATE visits SET status='done', finished_at=NOW() WHERE id=?")->execute([$vid]);
    ok(['visit_id' => $vid, 'status' => 'done']);
}

/* 打印输出：病历 + 处方单（A4） */
case 'print_opd': {
    $vid = (int)($_GET['visit_id'] ?? 0);
    check_visit($vid);
    $st = $pdo->prepare('SELECT p.*, v.reg_fee, v.registered_at, v.id AS vid FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id=?');
    $st->execute([$vid]);
    $p = $st->fetch();
    $st = $pdo->prepare('SELECT * FROM opd_records WHERE visit_id=?'); $st->execute([$vid]);
    $rec = $st->fetch() ?: [];
    $st = $pdo->prepare('SELECT type, items, total FROM prescriptions WHERE visit_id=? ORDER BY id'); $st->execute([$vid]);
    $rx = $st->fetchAll();
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $typeCn = ['western' => '西药处方', 'chinese' => '中药处方', 'infusion' => '输液处方'];
    $rxHtml = '';
    $grand = 0.0;
    foreach ($rx as $r) {
        $items = json_decode((string)$r['items'], true) ?: [];
        $rows = '';
        foreach ($items as $i => $it) {
            $grand += (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0);
            $isTcm = $r['type'] === 'chinese';
            if ($isTcm) {
                /* 中药饮片：名称+炮制+克数+特殊煎法 */
                $detail = $e($it['name'] ?? '') . (($it['prep'] ?? '') ? '（' . $e($it['prep']) . '）' : '')
                    . '</td><td>' . $e(($it['qty_g'] ?? $it['dose'] ?? '') . 'g' . (($it['decoct'] ?? '') ? '，' . $e($it['decoct']) : ''))
                    . '</td><td>' . $e(($it['qty'] ?? '') ? '×' . $it['qty'] . '剂' : '') . '</td><td>' . number_format((float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0), 2);
                $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . $detail . '</td></tr>';
            } else {
                $spec = (string)($it['spec'] ?? '');
                $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . $e($it['name'] ?? '') . ($spec !== '' ? '（' . $e($spec) . '）' : '')
                    . '</td><td>' . $e($it['usage'] ?? '') . '</td><td>' . $e($it['freq'] ?? '') . '</td><td>' . $e(($it['dose'] ?? '') . '×' . ($it['days'] ?? '') . '天')
                    . '</td><td>' . $e($it['qty'] ?? '') . '</td><td>' . number_format((float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0), 2) . '</td></tr>';
            }
        }
        if ($rows) $rxHtml .= '<h2>' . $e($typeCn[$r['type']] ?? $r['type']) . '</h2><table>' . ($isTcm ? '<tr><th>#</th><th>饮片（炮制）</th><th>克数/煎法</th><th>剂数</th><th>金额</th></tr>' : '<tr><th>#</th><th>药品/项目</th><th>用法</th><th>频次</th><th>剂量/天数</th><th>数量</th><th>金额</th></tr>') . $rows . '</table>';
    }
    $aux = json_decode($rec['auxiliary_exam'] ?? '[]', true) ?: [];
    $trt = json_decode($rec['treatments'] ?? '[]', true) ?: [];
    $auxSum = 0.0; $trtSum = 0.0;
    $auxHtml = '';
    foreach ($aux as $i => $a) { $amt = (float)($a['price'] ?? 0) * (float)($a['times'] ?? 1); $auxSum += $amt;
        $auxHtml .= ($i + 1) . '. ' . $e(($a['name'] ?? '') . ' ×' . ($a['times'] ?? 1) . '  ¥' . number_format($amt, 2)) . '　'; }
    $trtHtml = '';
    foreach ($trt as $i => $a) { $amt = (float)($a['price'] ?? 0) * (float)($a['times'] ?? 1); $trtSum += $amt;
        $trtHtml .= ($i + 1) . '. ' . $e(($a['name'] ?? '') . ' ×' . ($a['times'] ?? 1) . '  ¥' . number_format($amt, 2)) . '　'; }
    $grand += $auxSum + $trtSum;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>接诊单 #' . $e($vid) . '</title><style>
@page { size: A4; margin: 18mm 16mm; }
body { font: 12pt/1.7 "Songti SC","SimSun",serif; max-width: 178mm; margin: 0 auto; color: #111; }
h1 { text-align: center; letter-spacing: 8px; font-size: 20pt; margin: 0 0 6px; }
.sub { text-align: center; color: #555; font-size: 9pt; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
h2 { font-size: 12pt; border-left: 4px solid #111; padding-left: 8px; margin: 12px 0 6px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #444; padding: 4px 8px; font-size: 10.5pt; text-align: left; }
pre { white-space: pre-wrap; font: inherit; }
.total { text-align: right; font-weight: bold; }
.noprint { text-align: center; margin: 10px 0; }
@media print { .noprint { display: none; } }
</style></head><body>
<div class="noprint"><button onclick="window.print()">🖨 打印 / 另存为 PDF</button></div>
<h1>有医帮 · 门诊病历及处方</h1>
<div class="sub">youyibang.com ｜ 接诊单 #' . $e($vid) . ' ｜ ' . $e($p['registered_at']) . '</div>
<h2>患者信息</h2>
<table><tr><th>姓名</th><td>' . $e($p['name']) . '</td><th>性别</th><td>' . $e($p['gender']) . '</td><th>年龄</th><td>' . $e($p['age']) . '</td></tr>
<tr><th>档案号</th><td>' . $e($p['archive_no']) . '</td><th>电话</th><td>' . $e($p['phone']) . '</td><th>挂号费</th><td>¥' . number_format((float)$p['reg_fee'], 2) . '</td></tr></table>
<h2>病历</h2><pre>主诉：' . $e($rec['chief_complaint'] ?? '无') . "\n现病史：" . $e($rec['present_illness'] ?? '无') . "\n过敏史：" . $e($rec['allergy_history'] ?? '无') . "\n体格检查：" . $e($rec['physical_exam'] ?? '无') . "\n诊断：" . $e($rec['diagnosis'] ?? '无') . '</pre>
' . ($auxHtml ? '<h2>辅助检查</h2><p>' . $auxHtml . '</p>' : '') . '
' . ($trtHtml ? '<h2>治疗项目</h2><p>' . $trtHtml . '</p>' : '') . '
' . $rxHtml . '
<p class="total">分项小计：药品 ¥' . number_format($grand - $auxSum - $trtSum, 2) . '｜辅助检查 ¥' . number_format($auxSum, 2) . '｜治疗项目 ¥' . number_format($trtSum, 2) . '</p>
<p class="total">合计金额：¥' . number_format($grand, 2) . '</p>
<p style="margin-top:30px">医师签名：____________　　日期：' . $e(date('Y-m-d')) . '</p>
<div style="border-top:1px solid #999;margin-top:14px;padding-top:6px;font-size:8.5pt;color:#666">免责声明：本单据由有医帮系统辅助生成，仅供参考，不构成诊疗指令。youyibang.com</div>
</body></html>';
    exit;
}

/* 一键整理医疗病历：口语转写 → 中医病历结构化（主诉/现病史/四诊摘要）
 * 上游离线时降级：返回 ok:false + raw 原始转写，前端兜底插入现病史，不丢数据。 */
case 'asr_chunk': {
    /* 云端分片转写：接收浏览器录音分片（WAV PCM16），转发到 OpenAI 兼容 /v1/audio/transcriptions */
    if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) fail('缺少音频分片');
    error_log('[yyb-asr] recv size=' . $_FILES['audio']['size'] . ' type=' . $_FILES['audio']['type']);
    if (empty($ASR_ENDPOINTS)) ok(['text' => '', 'note' => '未配置 ASR 端点']);
    $tmp = $_FILES['audio']['tmp_name'];
    $err = '';
    foreach ($ASR_ENDPOINTS as $ep) {
        $ch = curl_init(rtrim($ep['base'], '/') . '/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROXY => '', /* 强制直连：fpm 环境若有 http_proxy 会导致内网请求挂起 */
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $ep['key']],
            CURLOPT_POSTFIELDS => [
                'model' => $ep['model'] ?? 'whisper-1',
                'audio' => new CURLFile($tmp, 'audio/wav', 'chunk.wav'),
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($body !== false && $http === 200) {
            $j = json_decode($body, true);
            /* text 字段存在即成功（空 text = 静音分片或 ASR 忙丢弃，属正常） */
            if (is_array($j) && array_key_exists('text', $j)) {
                ok(['text' => trim((string)$j['text'], " |　"), 'note' => (string)($j['note'] ?? '')]);
            }
        }
        $err = $cerr ?: "HTTP {$http} {$body}";
    }
    echo json_encode(['ok' => false, 'error' => 'ASR 上游不可用：' . mb_substr($err, 0, 120)], JSON_UNESCAPED_UNICODE);
    exit;
}

case 'organize_transcript': {
    $transcript = trim($_POST['transcript'] ?? '');
    if ($transcript === '') fail('转写内容为空');
    /* 归属校验（可选参数，二者至少传其一才校验） */
    $vid = (int)($_POST['visit_id'] ?? 0);
    $cid = (int)($_POST['case_id'] ?? 0);
    if ($vid) check_visit($vid);
    if ($cid) check_owner($cid);
    $target = trim($_POST['target'] ?? ''); /* 可选侧重：主诉/现病史 */
    $targetNote = $target !== '' ? "\n本次整理侧重点：【{$target}】，其余部分仍需完整输出。" : '';
    $mode = ($_POST['mode'] ?? 'western') === 'tcm' ? 'tcm' : 'western';
    if ($mode === 'tcm') {
        $stdRule = "2. 按中医病历规范（参照《中医病历书写基本规范》）整理：【主诉】【现病史】【四诊摘要】三段结构，四诊摘要须含望闻问切所得（舌象、脉象等，未提及标注\"未提及\"）；语言使用规范中医术语（如\"胃脘胀满\"而非\"肚子胀\"）；";
    } else {
        $stdRule = "2. 按西医病历规范（《病历书写基本规范》）整理：【主诉】【现病史】【体格检查要点】三段结构；语言使用规范西医术语；";
    }
    $modeLabel2 = $mode === 'tcm' ? '中医' : '西医';
    /* 临床诊断（可传）：用于按诊断规范补全病历结构，但不得编造未提及的内容 */
    $dxCtx = trim($_POST['dx'] ?? '');
    $ckOrg = 'org_' . $mode . '_' . md5($transcript . '|' . $dxCtx . '|' . $target);
    $cachedOrg = ai_cache_get($ckOrg);
    if ($cachedOrg !== null) ok($cachedOrg);
    $dxNote = $dxCtx !== '' ? "\n6. 医生已初步诊断：【{$dxCtx}】——请参照该诊断的规范病历结构补全现病史要素（起病情况与诱因、主要症状特点、伴随症状、诊疗经过、精神饮食睡眠等一般情况）。【只允许整理对话中真实提及的内容并按规范措辞重组，对话未提及的要素一律写「未提及」，严禁编造】。" : '';
    $prompt = <<<P
你是门诊病历整理助手（{$modeLabel2}标准）。下面是一段医患问诊的口语转写，请整理为规范病历文本：
1. 去除语气词（嗯、啊、呃）、重复、寒暄与无关内容；
{$stdRule}
3. 保留所有医学信息，不得编造、不得遗漏症状/病程/诱因/伴随症/舌脉等；医学表述须符合教材规范；
4. 末尾固定加一行："本整理由 AI 生成，请医生核对"。{$targetNote}{$dxNote}
5. 只输出严格 JSON，不要任何解释文字：{"chief_complaint":"主诉文本","present_illness":"现病史文本","tcm_summary":"四诊摘要/体格检查要点文本"}

# 口语转写
{$transcript}
P;
    $r = llm_chat([['role' => 'user', 'content' => $prompt]], 120);
    if ($r === null) {
        /* 上游离线：兜底返回原始转写，前端插入现病史末尾 */
        echo json_encode(['ok' => false, 'note' => 'AI 整理暂不可用（上游模型离线）。已插入原始转写，恢复后可重新整理', 'raw' => $transcript], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $s = strpos($r, '{'); $e = strrpos($r, '}');
    $j = ($s !== false && $e !== false) ? json_decode(substr($r, $s, $e - $s + 1), true) : null;
    if (!is_array($j) || !isset($j['chief_complaint'])) {
        echo json_encode(['ok' => false, 'note' => 'AI 返回格式异常，已插入原始转写，恢复后可重新整理', 'raw' => $transcript], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out = [
        'chief_complaint' => trim((string)$j['chief_complaint']),
        'present_illness' => trim((string)($j['present_illness'] ?? '未提及')),
        'tcm_summary'     => trim((string)($j['tcm_summary'] ?? '未提及')),
    ];
    ai_cache_set($ckOrg, $out);
    ok($out);
}

default:
    fail('未知操作', 404);
}
