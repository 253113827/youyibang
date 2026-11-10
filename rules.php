<?php
/* 有医帮 - 病历规则引擎
 * 职责：解析 AI 生成的 Markdown 病历 → 结构化字段；执行校验规则返回 errors(阻断)/warnings(提示)。
 * 用法：require 'rules.php'; $parsed = parse_record($md); $res = validate_record($parsed, $isPregnant);
 */

declare(strict_types=1);

/* ---------- 配方名归一化：去炮制前缀/空格，映射常见炮制品到基础药 ---------- */
function normalize_drug(string $name): string {
    $name = trim($name);
    // 去常见炮制前缀（反复剥一层即可覆盖 炙甘草/法半夏/酒白芍 等）
    $prefixes = ['酒', '盐', '姜', '醋', '蜜', '煅', '焦', '熟', '法', '炙', '生', '炒'];
    foreach ($prefixes as $p) {
        if (mb_strlen($name) > 1 && mb_strpos($name, $p) === 0) $name = mb_substr($name, 1);
    }
    // 炮制品别名归并
    $alias = [
        '天花粉' => '瓜蒌', '瓜蒌皮' => '瓜蒌', '瓜蒌仁' => '瓜蒌', '全瓜蒌' => '瓜蒌',
        '川贝' => '贝母', '浙贝' => '贝母', '川贝母' => '贝母', '浙贝母' => '贝母',
        '京大戟' => '大戟', '红大戟' => '大戟',
        '北沙参' => '沙参', '南沙参' => '沙参',
        '赤芍' => '芍药', '白芍' => '芍药', '白芍药' => '芍药',
        '党参' => '人参', // 党参归人参组（藜芦反诸参谨慎处理）
        '牵牛' => '牵牛子', '二丑' => '牵牛子',
        '牙硝' => '芒硝', '朴硝' => '芒硝', '皮硝' => '芒硝',
        '官桂' => '肉桂', '犀角' => '犀角', '五灵脂' => '五灵脂',
        '郁金' => '郁金', '母丁香' => '丁香',
    ];
    return $alias[$name] ?? $name;
}

/* ---------- 十八反（组内互反） ----------
 * SHIBAFAN_GROUPS 下标：0 乌头组；1 半夏 2 瓜蒌 3 贝母 4 白蔹 5 白及；
 * 6 甘草 7 甘遂 8 大戟 9 海藻 10 芫花；11 藜芦；
 * 12 人参 13 沙参 14 丹参 15 玄参 16 苦参 17 细辛 18 芍药 */
const SHIBAFAN_GROUPS = [
    ['川乌', '草乌', '乌头', '附子'],                                  // 乌头组
    ['半夏'], ['瓜蒌'], ['贝母'], ['白蔹'], ['白及'],
    ['甘草'], ['甘遂'], ['大戟'], ['海藻'], ['芫花'],
    ['藜芦'], ['人参'], ['沙参'], ['丹参'], ['玄参'], ['苦参'], ['细辛'], ['芍药'],
];

/* 十八反互反对（按 SHIBAFAN_GROUPS 下标）：乌头组反半夏/瓜蒌/贝母/白蔹/白及；
   甘草反甘遂/大戟/海藻/芫花；藜芦反人参/沙参/丹参/玄参/苦参/细辛/芍药 */
const SHIBAFAN_PAIRS = [
    [0, 1], [0, 2], [0, 3], [0, 4], [0, 5],
    [6, 7], [6, 8], [6, 9], [6, 10],
    [11, 12], [11, 13], [11, 14], [11, 15], [11, 16], [11, 17], [11, 18],
];

/* ---------- 十九畏 ---------- */
const SHIJIUWEI_PAIRS = [
    [['硫黄'], ['芒硝', '朴硝']],
    [['水银'], ['砒霜', '砒']],
    [['狼毒'], ['密陀僧']],
    [['巴豆'], ['牵牛子']],
    [['丁香'], ['郁金']],
    [['川乌', '草乌', '乌头', '附子'], ['犀角']],
    [['芒硝'], ['三棱']],
    [['肉桂', '官桂'], ['赤石脂', '石脂']],
    [['人参'], ['五灵脂']],
];

/* ---------- 药典常规剂量上限（g），代表药 20 个；超出仅 warning ---------- */
const DOSE_LIMITS = [
    '细辛' => 3, '附子' => 15, '川乌' => 6, '草乌' => 6, '麻黄' => 10,
    '半夏' => 9, '大黄' => 15, '黄连' => 5, '甘遂' => 2, '芫花' => 3,
    '大戟' => 3, '藜芦' => 1, '巴豆' => 1, '马钱子' => 1, '斑蝥' => 1,
    '雄黄' => 1, '朱砂' => 1, '牵牛子' => 6, '吴茱萸' => 5, '肉桂' => 6,
];

/* ---------- 妊娠禁忌药（出现即 warning，孕妇医案） ---------- */
const PREGNANCY_FORBIDDEN = [
    '麝香', '三棱', '莪术', '水蛭', '虻虫', '牵牛子', '芫花', '甘遂', '大戟', '巴豆',
    '附子', '川乌', '草乌', '桃仁', '红花', '大黄', '芒硝', '枳实', '肉桂', '番泻叶',
];

/* ---------- 病历 Markdown 解析 ----------
 * 返回 ['ok'=>bool, 'chief_complaint'=>'', 'present_illness'=>'',
 *       'tongue'=>'', 'pulse'=>'', 'syndrome'=>'', 'drugs'=>[['name','dose'(float|null),'raw'=>'']]]
 * ok=false 表示解析失败（结构不完整，调用方应整体报 error）。
 */
function parse_record(string $md): array {
    $out = ['ok' => false, 'chief_complaint' => '', 'present_illness' => '',
            'tongue' => '', 'pulse' => '', 'syndrome' => '', 'drugs' => [], 'raw' => $md];

    // 按标题切块（## 一、主诉 / ## 主诉 等）
    $lines = preg_split('/\r\n|\r|\n/', $md);
    $sections = []; $cur = '前言';
    foreach ($lines as $ln) {
        if (preg_match('/^#{1,6}\s*(.+)$/u', $ln, $m)) { $cur = $m[1]; $sections[$cur] = $cur === '前言' ? '' : ''; }
        elseif (isset($sections[$cur])) $sections[$cur] .= $ln . "\n";
    }
    // 关键词定位各段
    $find = function (array $keys) use ($sections): ?string {
        foreach ($sections as $title => $body) {
            foreach ($keys as $k) if (mb_strpos($title, $k) !== false) return $body;
        }
        return null;
    };
    $chief  = $find(['主诉']);
    $hist   = $find(['现病史']);
    $four   = $find(['四诊', '望闻问切', '望诊', '切诊']);
    $synd   = $find(['辨证', '诊断']);
    $rx     = $find(['处方', '方剂', '参考方剂']);
    if ($chief === null || $synd === null || $rx === null) return $out; // 结构缺失

    $out['chief_complaint'] = trim(preg_replace('/\s+/', ' ', strip_md($chief)));
    $out['present_illness'] = $hist !== null ? trim(strip_md($hist)) : '';
    if ($four !== null) {
        foreach (preg_split('/\r\n|\r|\n/', $four) as $ln) {
            if (preg_match('/舌/', $ln) && $out['tongue'] === '') $out['tongue'] = trim(strip_md($ln));
            if (preg_match('/脉/', $ln) && $out['pulse'] === '') $out['pulse'] = trim(strip_md($ln));
        }
    }
    $out['syndrome'] = trim(strip_md($synd));
    $out['drugs'] = parse_drugs($rx);
    // 孕妇标记：病历文本中出现妊娠/怀孕/孕周等
    $out['pregnant'] = (bool)preg_match('/妊娠|怀孕|孕[0-9一二三四五六七八九十周月]|孕妇/', $md);
    $out['ok'] = true;
    return $out;
}

function strip_md(string $s): string {
    $s = preg_replace('/[*_`#>|]/u', '', $s);
    return trim($s, " \t-·");
}

/* 从处方段提取药物数组；无剂量的清单行 → dose=null */
function parse_drugs(string $section): array {
    $drugs = [];
    $lines = preg_split('/\r\n|\r|\n/', $section);
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '' || preg_match('/^#{1,6}\s/u', $ln)) continue;
        if (preg_match('/加减|煎服法|用法|水煎服|每日|分.*服|温服|随证/iu', $ln) && !preg_match('/\d+\s*(?:g|克)/u', $ln)) {
            // 纯说明行跳过（但若含剂量仍解析）
            if (!preg_match('/[\x{4e00}-\x{9fa5}]{2,8}\s*\d/u', $ln)) continue;
        }
        // 形如：黄芪 30g（先煎） 或 黄芪30克 或 黄芪：30
        if (preg_match_all('/([\x{4e00}-\x{9fa5}A-Za-z]{1,8})\s*(\d+(?:\.\d+)?)\s*(?:g|克|G)?/u', $ln, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                // 剂量≤0 不丢弃：保留该药（dose=0），由 validate_record 报"剂量不合法（≤0）"
                $drugs[] = ['name' => normalize_drug($m[1]), 'dose' => (float)$m[2], 'raw' => $m[0]];
            }
            continue;
        }
        // 无剂量行：药名清单（顿号/逗号/空白分隔）或单药一行 → 剂量缺失（dose=null），不得静默跳过
        if (preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z、，,\s\/]+$/u', $ln)
            && !preg_match('/加减|煎服|用法|每日|温服|冲服|随证|一剂|分.*服|以上|诸药|共为/u', $ln)) {
            foreach (preg_split('/[、，,\/\s]+/u', $ln) as $d) {
                $d = normalize_drug(trim($d));
                if ($d !== '' && mb_strlen($d) >= 2 && !preg_match('/^(组成|方名|出处|化裁|加减|煎服法|用法)$/u', $d)) {
                    $drugs[] = ['name' => $d, 'dose' => null, 'raw' => $d];
                }
            }
        }
    }
    return $drugs;
}

/* ---------- 规则校验 ----------
 * @param array $parsed parse_record 结果
 * @param bool  $isPregnant 患者是否孕妇（优先用 $parsed['pregnant']，也可外部强制）
 * @return array ['errors'=>[], 'warnings'=>[]] 元素为字符串
 */
function validate_record(array $parsed, bool $isPregnant = false): array {
    $errors = []; $warnings = [];

    if (empty($parsed['ok'])) {
        $errors[] = '病历解析失败：缺少主诉/辨证/处方等必要结构，请重新生成或人工补全。';
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // errors：阻断签名
    if ($parsed['chief_complaint'] === '') $errors[] = '主诉缺失（阻断项）。';
    if ($parsed['syndrome'] === '') $errors[] = '辨证诊断缺失（阻断项）。';
    if (empty($parsed['drugs'])) {
        $errors[] = '处方为空（阻断项）。';
    } else {
        foreach ($parsed['drugs'] as $d) {
            if ($d['dose'] === null) $errors[] = "处方药物「{$d['name']}」缺少剂量（阻断项）。";
            elseif ($d['dose'] <= 0) $errors[] = "处方药物「{$d['name']}」剂量不合法（≤0）（阻断项）。";
            elseif ($d['dose'] > 500) $errors[] = "处方药物「{$d['name']}」剂量 {$d['dose']}g 超过单味药上限 500g（阻断项）。";
        }
    }

    // warnings：提示不阻断
    if ($parsed['present_illness'] === '') $warnings[] = '现病史缺失，建议补充。';
    if ($parsed['tongue'] === '') $warnings[] = '舌象未采集，建议补充望诊。';
    if ($parsed['pulse'] === '') $warnings[] = '脉象未采集，建议补充切诊。';

    // 十八反 / 十九畏（关键词模糊匹配）
    $names = array_column($parsed['drugs'], 'name');
    $matchGroup = function (array $keys) use ($names): bool {
        foreach ($names as $n) foreach ($keys as $k) if (mb_strpos($n, $k) !== false) return true;
        return false;
    };
    foreach (SHIBAFAN_PAIRS as [$a, $b]) {
        if ($matchGroup(SHIBAFAN_GROUPS[$a]) && $matchGroup(SHIBAFAN_GROUPS[$b])) {
            $warnings[] = '【十八反】处方含配伍禁忌：' . implode('/', SHIBAFAN_GROUPS[$a]) . ' 反 ' . implode('/', SHIBAFAN_GROUPS[$b]) . '，请复核！';
        }
    }
    foreach (SHIJIUWEI_PAIRS as [$a, $b]) {
        if ($matchGroup($a) && $matchGroup($b)) {
            $warnings[] = '【十九畏】处方含配伍禁忌：' . implode('/', $a) . ' 畏 ' . implode('/', $b) . '，请复核！';
        }
    }

    // 药典常规剂量上限
    foreach ($parsed['drugs'] as $d) {
        if ($d['dose'] === null) continue;
        foreach (DOSE_LIMITS as $lim => $max) {
            if (mb_strpos($d['name'], $lim) !== false && $d['dose'] > $max) {
                $warnings[] = "「{$d['name']}」剂量 {$d['dose']}g 超出药典常规上限（{$lim}≤{$max}g），请确认特殊用法（如先煎/遵药典调整）。";
                break;
            }
        }
    }

    // 妊娠禁忌
    if ($isPregnant || !empty($parsed['pregnant'])) {
        foreach ($parsed['drugs'] as $d) {
            foreach (PREGNANCY_FORBIDDEN as $p) {
                if (mb_strpos($d['name'], $p) !== false) { $warnings[] = "孕妇医案：处方含妊娠禁忌药「{$d['name']}」，请高度警惕！"; break; }
            }
        }
    }

    return ['errors' => $errors, 'warnings' => $warnings];
}
