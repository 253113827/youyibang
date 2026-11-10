<?php
/* 有医帮 - 门诊接诊工作台（三栏：队列 | 病历处方 | AI诊断/患者历史）
 * v2：中医四诊勾选面板 / 语音转文字 / 处方签步进器 / 黄色高亮编辑区 / AI 症状 chip */
require __DIR__ . '/config.php';
need_login();
$uid = (int)($_SESSION['uid'] ?? 1);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>有医帮 - 接诊工作台</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/speech.js"></script>
<script src="assets/recorder.js"></script>
<style>
body { margin:0; font:14px/1.5 -apple-system,"PingFang SC","Microsoft YaHei",sans-serif; background:#f0f2f5; }
.navbar { display:flex; gap:4px; align-items:center; background:#1f6f43; color:#fff; padding:0 12px; height:44px; position:sticky; top:0; z-index:50; }
.navbar .logo { font-weight:700; margin-right:14px; }
.navbar a, .navbar span.mod { color:#d6efe0; text-decoration:none; padding:6px 10px; border-radius:6px; font-size:13px; }
.navbar a.act { background:#2e9159; color:#fff; }
.navbar .mod.dis { opacity:.45; cursor:not-allowed; }
.navbar .right { margin-left:auto; }
.workspace { display:grid; grid-template-columns: 280px 1fr 320px; gap:10px; padding:10px; height:calc(100vh - 44px); box-sizing:border-box; }
.col { background:#fff; border-radius:10px; overflow:auto; padding:10px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.col h3 { margin:4px 0 8px; font-size:14px; color:#333; }
.qitem { padding:8px 10px; border-radius:8px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border:1px solid transparent; }
.qitem:hover { background:#f5faf7; }
.qitem.sel { border-color:#1f6f43; background:#eaf6ef; }
.avatar { width:30px; height:30px; border-radius:50%; background:#1f6f43; color:#fff; display:inline-flex; align-items:center; justify-content:center; margin-right:8px; font-size:13px; flex:none; }
.tag { font-size:11px; padding:2px 8px; border-radius:10px; }
.tag.waiting { background:#e6f0ff; color:#2563eb; } .tag.in_visit { background:#fff7e6; color:#d48806; } .tag.done { background:#f0f0f0; color:#888; }
.qname { display:flex; align-items:center; flex:1; min-width:0; }
.qmeta { font-size:12px; color:#666; }
.btn { background:#1f6f43; color:#fff; border:0; border-radius:6px; padding:7px 14px; cursor:pointer; font-size:13px; }
.btn.gray { background:#6b7280; } .btn.red { background:#c0392b; } .btn.blue { background:#2563eb; } .btn.ghost { background:#fff; color:#1f6f43; border:1px solid #1f6f43; }
.btn.sm { padding:4px 10px; font-size:12px; }
.ptbar { display:flex; align-items:center; gap:10px; padding:8px 10px; background:#eaf6ef; border-radius:8px; margin-bottom:10px; flex-wrap:wrap; }
.ptbar b { font-size:15px; }
.ptbar .actions { margin-left:auto; display:flex; gap:6px; flex-wrap:wrap; }
.frow { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
label.fl { display:block; margin-bottom:8px; font-size:13px; color:#333; }
label.fl textarea, label.fl input, label.fl select { width:100%; box-sizing:border-box; margin-top:3px; padding:6px 8px; border:1px solid #d9d9d9; border-radius:6px; font:inherit; }
textarea { resize:vertical; }
/* 黄色高亮编辑区（对齐 ABC 诊所管家截图） */
.editzone { background:#fffbe6; border:2px solid #fadb14; border-radius:8px; padding:8px; margin-bottom:10px; }
.editzone label.fl:last-child { margin-bottom:0; }
.pe3 { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.pe3 input { width:90px !important; }
.pe3 span { font-size:12px; color:#666; }
/* 语音按钮 */
.mic-btn { background:#eef7f1; border:1px solid #bcd9c8; border-radius:6px; margin-left:4px; padding:4px 7px; cursor:pointer; font-size:13px; line-height:1; }
.mic-btn.rec { background:#1f6f43; color:#fff; animation:pulse 1.2s infinite; }
@keyframes pulse { 50% { opacity:.55; } }
/* 中医四诊勾选面板 */
details.tcm { border:1px solid #d9e5dd; border-radius:8px; margin-bottom:10px; background:#fbfdfc; }
details.tcm summary { cursor:pointer; font-weight:700; font-size:13px; padding:8px 10px; color:#1f6f43; }
details.tcm .body { padding:4px 10px 10px; }
.tcmgrp { margin-bottom:8px; }
.tcmgrp > b { font-size:12px; color:#555; display:inline-block; min-width:64px; }
.tcmrow { display:flex; align-items:center; gap:4px; flex-wrap:wrap; margin:3px 0 3px 8px; }
.tcmrow .rl { font-size:11px; color:#888; min-width:56px; }
.chip { display:inline-block; font-size:12px; padding:2px 10px; border-radius:12px; border:1px solid #cfd8d2; background:#fff; cursor:pointer; margin:1px 2px; user-select:none; }
.chip.on { background:#1f6f43; border-color:#1f6f43; color:#fff; }
.chip.sym { border-color:#bcd9c8; background:#eaf6ef; color:#1f6f43; cursor:pointer; }
/* 处方签 */
.tabs { display:flex; gap:4px; border-bottom:2px solid #1f6f43; margin-bottom:8px; flex-wrap:wrap; }
.tabs button { border:0; background:#e8ebe9; padding:5px 12px; cursor:pointer; border-radius:6px 6px 0 0; font-size:12px; }
.tabs button.on { background:#1f6f43; color:#fff; }
table.rx { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:6px; }
table.rx th, table.rx td { border:1px solid #e0e0e0; padding:3px 5px; }
table.rx th { background:#f5f5f5; font-weight:600; }
table.rx input, table.rx select { width:100%; border:0; font:inherit; box-sizing:border-box; background:transparent; }
.rxtotal { text-align:right; font-weight:700; color:#c0392b; margin:4px 0 8px; }
.rxsheet { margin-bottom:10px; }
.rxsheet .hd { display:flex; align-items:center; gap:8px; }
.rxsub { text-align:right; font-size:12px; color:#c0392b; font-weight:600; }
.step { display:inline-flex; align-items:center; gap:2px; }
.step button { border:1px solid #ccc; background:#f7f7f7; width:18px; height:20px; line-height:1; cursor:pointer; font-size:12px; border-radius:4px; padding:0; }
.step input { width:42px; text-align:center; }
.dd { position:relative; }
.dd-list { position:absolute; z-index:30; background:#fff; border:1px solid #ddd; border-radius:6px; max-height:220px; overflow:auto; width:100%; box-shadow:0 4px 12px rgba(0,0,0,.12); }
.dd-list div { padding:6px 10px; cursor:pointer; font-size:12px; }
.dd-list div:hover { background:#eaf6ef; }
.aibox { border:1px dashed #bbb; border-radius:8px; padding:10px; margin-bottom:10px; }
.dxname { font-weight:700; color:#1f6f43; margin:6px 0 4px; }
.pill { display:inline-block; background:#eaf6ef; color:#1f6f43; border-radius:12px; padding:2px 10px; font-size:11px; margin:2px 4px 2px 0; }
.ai-note { font-size:11px; color:#999; }
.airow { font-size:12px; margin:3px 0; }
.airow b { color:#555; }
.histitem { border-bottom:1px solid #f0f0f0; padding:8px 4px; font-size:12px; }
.histitem b { color:#333; }
.modal { position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:100; }
.modal.show { display:flex; }
.modal .box { background:#fff; border-radius:12px; padding:18px; width:360px; max-width:92vw; max-height:88vh; overflow:auto; }
.modal h3 { margin-top:0; }
.hint { font-size:11px; color:#999; }
@media (max-width: 768px) {
  .workspace { display:block; height:auto; padding:6px; }
  .col { margin-bottom:8px; }
  .col.left, .col.right { display:none; }
  body[data-view="queue"] .col.left { display:block; }
  body[data-view="dx"] .col.right { display:block; }
  .mview { display:flex; gap:6px; margin:6px; }
  .mview button { flex:1; }
  .frow { grid-template-columns:1fr; }
}
</style>
</head>
<body data-view="mid">
<div class="mview" style="display:none">
  <button class="btn gray sm" onclick="document.body.dataset.view='queue'">队列</button>
  <button class="btn sm" onclick="document.body.dataset.view='mid'">接诊</button>
  <button class="btn gray sm" onclick="document.body.dataset.view='dx'">AI/历史</button>
</div>
<nav class="navbar">
  <span class="logo">有医帮</span>
  <a class="act" href="reception.php">门诊</a>
  <a href="index.php">医案问诊</a>
  <span class="mod dis">预约</span><span class="mod dis">收费</span><span class="mod dis">药房</span><span class="mod dis">库存</span><span class="mod dis">统计</span>
  <span class="right"><a href="logout.php">退出</a></span>
</nav>

<div class="workspace">
<!-- 左栏：队列 -->
<div class="col left">
  <div style="display:flex;gap:6px;margin-bottom:8px">
    <input id="qsearch" placeholder="搜索患者…" style="flex:1;padding:6px 8px;border:1px solid #d9d9d9;border-radius:6px" oninput="renderQueue()">
    <button class="btn sm" onclick="openNewPt()">快速接诊</button>
  </div>
  <h3>今天</h3><div id="todayList"></div>
  <h3 style="margin-top:12px">历史就诊</h3><div id="histList"></div>
</div>

<!-- 中栏：病历+处方 -->
<div class="col mid">
  <div class="ptbar" id="ptbar">未选择患者（左侧点击「今天」列表）</div>
  <div id="recForm" style="display:none">
  <!-- 语音问诊横条：录音按钮 + 计时 + 波形 + 实时转写 + 转写全文 + 整理成病历 -->
  <div class="voicebar" id="voiceBar">
    <div class="vb-left" id="recMount"></div>
    <div class="vb-right">
      <label class="fl">转写全文（可编辑后再整理）<textarea id="f_transcript" rows="3" placeholder="点击左侧麦克风开始问诊录音，转写自动填入此处"></textarea></label>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn blue sm" onclick="organizeTranscript()">🩺 整理成病历</button>
        <span id="orgNote" class="hint"></span>
      </div>
    </div>
  </div>
  <!-- 患者信息条 + 保存/取消/打印按钮组 -->
  <div style="display:flex;justify-content:flex-end;gap:6px;margin-bottom:8px;flex-wrap:wrap">
    <button class="btn blue" onclick="saveOpd()">保存</button>
    <button class="btn gray" onclick="cancelOpd()">取消</button>
    <button class="btn" style="background:#8a8f8c" onclick="printOpd()">打印</button>
  </div>

  <!-- 中医四诊勾选面板（一页全勾） -->
  <details class="tcm" id="tcmPanel">
    <summary>🀄 中医四诊勾选（问诊 · 舌象 · 面色 · 脉象）<span id="tcmCount" class="hint"></span></summary>
    <div class="body">
      <?php
      /* 四诊勾选数据（服务端静态渲染，JS 仅负责勾选状态） */
      $TCM_GROUPS = [
        '问诊' => ['寒热' => '畏寒,发热,寒热往来,午后潮热', '汗' => '自汗,盗汗,无汗,大汗', '头身' => '头痛,头晕,身重,关节痛',
                   '胸腹' => '胸闷,心悸,胁胀,脘痞,腹痛', '饮食口味' => '口渴,口苦,口淡,纳呆,多食易饥', '睡眠' => '失眠,多梦,嗜睡',
                   '二便' => '便秘,便溏,腹泻,尿黄,尿频,夜尿多', '其他' => '乏力,气短,五心烦热,动则汗出'],
        '舌象' => ['舌色' => '淡白,红,绛,紫暗', '苔色' => '白,黄,灰黑', '苔质' => '薄,厚,腻,燥,剥落', '舌形' => '胖大,齿痕,瘦薄,裂纹'],
        '面色' => ['面色' => '红,白,黄,青,黑,正常光泽'],
        '脉象' => ['脉象' => '浮,沉,迟,数,滑,涩,弦,细,弱,结代'],
      ];
      foreach ($TCM_GROUPS as $g => $rows) {
        echo '<div class="tcmgrp"><b>' . $g . '</b>';
        foreach ($rows as $rl => $opts) {
          echo '<div class="tcmrow"><span class="rl">' . $rl . '</span>';
          foreach (explode(',', $opts) as $o) {
            echo '<span class="chip" data-k="' . $rl . ':' . $o . '" onclick="tcmToggle(this)">' . $o . '</span>';
          }
          echo '</div>';
        }
        echo '</div>';
      } ?>
      <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
        <button class="btn sm" onclick="tcmInsert()">📋 一键插入病历</button>
        <button class="btn gray sm" onclick="tcmClear()">清空勾选</button>
        <span class="hint">勾选结果将汇编为结构化四诊文本写入「现病史」，并随病历保存以便回显</span>
      </div>
    </div>
  </details>

  <div class="editzone">
  <div class="frow">
  <label class="fl">主诉<textarea id="f_cc" rows="2"></textarea></label>
  <label class="fl">现病史/既往史<textarea id="f_pi" rows="2"></textarea></label>
  </div>
  <div class="frow">
  <label class="fl">过敏史/个人史<input id="f_al"></label>
  <label class="fl">体格检查<div class="pe3">
    <span>体温</span><input id="pe_t" inputmode="decimal" placeholder="35.6">C
    <span>血压</span><input id="pe_bp" placeholder="105/90">mmhg
    <span>心率</span><input id="pe_hr" inputmode="numeric" placeholder="120">bpm
  </div></label>
  </div>
  <label class="fl">诊断（输入可联想诊断字典）<textarea id="f_dx" rows="2" oninput="diagSearch(this.value)"></textarea></label>
  <label class="fl">辅助检查（自动计费）<div id="auxRows"></div>
    <div class="dd"><input id="f_aux" placeholder="输入项目名搜索，如：血常规" oninput="auxSearch(this.value)" autocomplete="off"></div>
  </label>
  <label class="fl">治疗项目<div id="trtRows"></div>
    <div class="dd"><input id="f_trt" placeholder="输入治疗项目搜索，如：针灸" oninput="trtSearch(this.value)" autocomplete="off"></div>
  </label>
  </div>

  <div class="tabs" id="rxTabs">
    <button data-t="western" class="on">+ 西药处方</button>
    <button data-t="infusion">+ 输液处方</button>
    <button data-t="chinese">+ 中药处方</button>
    <button onclick="applyTemplate()">选择模板</button>
    <button onclick="saveTemplate()">保存模板</button>
  </div>
  <div id="rxArea"></div>
  <div class="rxtotal">合计金额：<span id="grandTotal">¥0.00</span></div>
  <label class="fl">注意事项<textarea id="f_notes" rows="2"></textarea></label>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" onclick="saveOpd()">💾 保存</button>
    <button class="btn red" onclick="finishVisit()">✅ 完成接诊</button>
    <span class="hint">AI 调用与打印需先保存</span>
  </div>
  </div>
</div>

<!-- 右栏：AI诊断 / 患者历史 -->
<div class="col right">
  <div class="tabs">
    <button class="on" data-rtab="dx" onclick="rTab(this)">智能诊断</button>
    <button data-rtab="hist" onclick="rTab(this)">患者历史</button>
  </div>
  <div id="rtab-dx">
    <button class="btn blue sm" onclick="aiDx()">🤖 AI 智能诊断</button>
    <button class="btn gray sm" onclick="aiRxReview()">🛡 处方安全审查</button>
    <span class="hint">基于主诉/现病史/查体生成鉴别诊断 · 仅供参考</span>
    <div id="aiResult"></div>
  </div>
  <div id="rtab-hist" style="display:none"><div id="ptHist">（先选择患者）</div></div>
</div>
</div>

<!-- 整理成病历预览确认弹窗（医生可编辑后确认写入） -->
<div class="modal" id="mOrg">
  <div class="box" style="width:520px">
    <h3>🩺 整理成病历（预览确认）</h3>
    <p class="hint">以下为 AI 整理结果，请核对编辑后确认写入（追加到对应栏，不覆盖已有内容）</p>
    <label class="fl">主诉<textarea id="org_cc" rows="2"></textarea></label>
    <label class="fl">现病史<textarea id="org_pi" rows="4"></textarea></label>
    <label class="fl">四诊摘要（写入体格检查）<textarea id="org_tcm" rows="3"></textarea></label>
    <div style="display:flex;gap:8px"><button class="btn" onclick="orgApply()">确认写入病历</button>
    <button class="btn gray" onclick="document.getElementById('mOrg').classList.remove('show')">取消</button></div>
  </div>
</div>

<!-- 快速接诊弹窗 -->
<div class="modal" id="mNew">
  <div class="box">
    <h3>快速接诊（新建患者）</h3>
    <label class="fl">姓名*<input id="np_name"></label>
    <div class="frow">
    <label class="fl">性别<select id="np_g"><option>男</option><option>女</option><option>未知</option></select></label>
    <label class="fl">年龄<input id="np_age" inputmode="numeric"></label>
    </div>
    <label class="fl">电话<input id="np_phone"></label>
    <label class="fl">过敏史<input id="np_al"></label>
    <label class="fl">挂号费 ¥<input id="np_fee" value="10" inputmode="decimal"></label>
    <div style="display:flex;gap:8px"><button class="btn" onclick="createPatient()">挂号并接诊</button>
    <button class="btn gray" onclick="document.getElementById('mNew').classList.remove('show')">取消</button></div>
  </div>
</div>

<!-- 患者档案编辑弹窗 -->
<div class="modal" id="mEdit">
  <div class="box">
    <h3>编辑患者档案</h3>
    <label class="fl">姓名<input id="ep_name"></label>
    <div class="frow">
    <label class="fl">性别<select id="ep_g"><option>男</option><option>女</option><option>未知</option></select></label>
    <label class="fl">年龄<input id="ep_age" inputmode="numeric"></label>
    </div>
    <label class="fl">电话<input id="ep_phone"></label>
    <label class="fl">过敏史<input id="ep_al"></label>
    <div style="display:flex;gap:8px"><button class="btn" onclick="savePatient()">保存档案</button>
    <button class="btn gray" onclick="document.getElementById('mEdit').classList.remove('show')">取消</button></div>
  </div>
</div>

<script>
const A = 'api.php?act=';
let CUR = { visit: null, patient: null, record: null, rx: {} };
let Q = { today: [], history: [] };
const $ = id => document.getElementById(id);
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

/* ---------- 语音转文字：给所有文本输入区挂 🎤（不支持的环境自动隐藏） ---------- */
function speechInit(scope) {
  if (!window.speechSupported || !window.speechSupported()) return; // Safari iOS<14.5 等环境隐藏按钮
  const sel = '#f_cc,#f_pi,#f_al,#f_dx,#f_notes,#f_aux,#f_trt,#pe_t,#pe_bp,#pe_hr,#qsearch';
  (scope || document).querySelectorAll(sel).forEach(el => window.attachSpeech(el));
}

/* ---------- 中医四诊勾选（chips 由服务端渲染，此处仅状态管理） ---------- */
let _tcm = {}; /* key: 行名:选项 → true */
function tcmToggle(el) {
  const k = el.dataset.k;
  if (_tcm[k]) { delete _tcm[k]; el.classList.remove('on'); } else { _tcm[k] = true; el.classList.add('on'); }
  tcmCount();
}
function tcmCount() {
  $('tcmCount').textContent = Object.keys(_tcm).length ? '（已勾 ' + Object.keys(_tcm).length + ' 项）' : '';
}
function tcmClear() {
  _tcm = {};
  document.querySelectorAll('#tcmPanel .chip.on').forEach(el => el.classList.remove('on'));
  tcmCount();
}
function tcmText() {
  const LABELS = { '问诊': 1, '舌象': 1, '面色': 1, '脉象': 1 };
  const parts = [];
  document.querySelectorAll('#tcmPanel .tcmgrp').forEach(g => {
    const gname = g.querySelector('b').textContent;
    const seg = [];
    g.querySelectorAll('.tcmrow').forEach(row => {
      const rl = row.querySelector('.rl').textContent;
      const sel = Array.from(row.querySelectorAll('.chip.on')).map(c => c.textContent);
      if (!sel.length) return;
      seg.push(rl === '面色' ? '面色' + sel.join('、') : rl + sel.join('、'));
    });
    if (seg.length) parts.push(gname + '：' + seg.join('；'));
  });
  return parts.join('\n');
}
function tcmInsert() {
  const t = tcmText();
  if (!t) return alert('请先勾选四诊项目');
  $('f_pi').value = ($('f_pi').value ? $('f_pi').value.replace(/\s*$/, '\n') : '') + t;
  $('f_pi').dispatchEvent(new Event('change', { bubbles: true }));
}
function tcmCollect() { return Object.keys(_tcm).length ? { selected: _tcm } : null; }
function tcmRestore(json) {
  _tcm = {};
  try { const j = typeof json === 'string' ? JSON.parse(json) : json; if (j && j.selected) _tcm = j.selected; } catch (e) {}
  document.querySelectorAll('#tcmPanel .chip').forEach(el => el.classList.toggle('on', !!_tcm[el.dataset.k]));
  tcmCount();
}

/* ---------- 队列 ---------- */
async function loadQueue() {
  const r = await fetch(A + 'queue'); const j = await r.json();
  if (j.ok) { Q = j; renderQueue(); }
}
function renderQueue() {
  const kw = $('qsearch').value.trim();
  $('todayList').innerHTML = Q.today.filter(v => !kw || v.name.includes(kw)).map(v =>
    `<div class="qitem ${CUR.visit === v.id ? 'sel' : ''}" onclick="openVisit(${v.id})">
      <span class="qname"><span class="avatar">${esc(v.name[0])}</span><span><b>${esc(v.name)}</b><br>
      <span class="qmeta">${esc(v.gender)}/${esc(v.age)} ${v.registered_at.slice(11,16) || ''}</span></span></span>
      <span class="tag ${v.status}">${({waiting:'待诊',in_visit:'就诊中',done:'已诊'})[v.status]}</span></div>`).join('')
    || '<p class="hint">今日暂无患者，点击「快速接诊」。</p>';
  $('histList').innerHTML = Q.history.map(v =>
    `<div class="histitem"><b>${esc(v.name)}</b> · ${esc(v.diagnosis || '未记录诊断')}<br><span class="hint">${esc(v.finished_at)} · 医生#${v.uid}</span></div>`).join('')
    || '<p class="hint">无历史就诊。</p>';
}

/* ---------- 打开接诊 ---------- */
async function openVisit(vid) {
  const r = await fetch(A + 'get_opd&visit_id=' + vid); const j = await r.json();
  if (!j.ok) { alert(j.error || '加载失败'); return; }
  CUR.visit = vid; CUR.patient = j.patient; CUR.record = j.record; CUR.rx = j.prescriptions || {};
  const p = j.patient;
  $('ptbar').innerHTML = `<b>${esc(p.name)}</b> ${esc(p.gender)}/${esc(p.age)}岁 档案号:${esc(p.archive_no)} 挂号费¥${(+p.reg_fee).toFixed(2)}
    <span>✏️</span><button class="btn ghost sm" title="编辑患者档案" onclick="openEditPt()">编辑</button>
    ${p.case_id ? `<a class="btn ghost sm" href="case.php?id=${p.case_id}">进入医案 →</a>` : '<span class="hint">未关联医案</span>'}
    <span class="actions">
      <button class="btn blue sm" onclick="saveOpd()">保存</button>
      <button class="btn gray sm" onclick="cancelOpd()">取消</button>
      <button class="btn sm" style="background:#8a8f8c" onclick="printOpd()">打印</button>
    </span>`;
  $('recForm').style.display = 'block';
  const r = j.record || {};
  $('f_cc').value = r.chief_complaint || ''; $('f_pi').value = r.present_illness || '';
  $('f_al').value = r.allergy_history || p.allergy || '';
  peFill(r.physical_exam || '');
  $('f_dx').value = r.diagnosis || ''; $('f_notes').value = r.notes || '';
  window._aux = JSON.parse(r.auxiliary_exam || '[]'); window._trt = JSON.parse(r.treatments || '[]');
  tcmRestore(r.tcm_checklist || null);
  renderAux(); renderTrt(); renderRx(); renderHist(j.history);
  $('aiResult').innerHTML = '<p class="hint">未运行。点击上方按钮生成鉴别诊断。</p>';
  renderQueue();
}
/* 体格检查三项（体温/血压/心率）与文本互转 */
function peCompose() {
  const t = $('pe_t').value.trim(), b = $('pe_bp').value.trim(), h = $('pe_hr').value.trim();
  const seg = [];
  if (t) seg.push('体温 ' + t + 'C');
  if (b) seg.push('血压 ' + b + 'mmhg');
  if (h) seg.push('心率 ' + h + 'bpm');
  return seg.join('  ');
}
function peFill(txt) {
  const m = { t: (txt.match(/体温\s*([\d.]+)/i) || [])[1], b: (txt.match(/血压\s*([\d/]+)/i) || [])[1], h: (txt.match(/心率\s*([\d.]+)/i) || [])[1] };
  $('pe_t').value = m.t || ''; $('pe_bp').value = m.b || ''; $('pe_hr').value = m.h || '';
  if (!m.t && !m.b && !m.h && txt) $('pe_t').value = txt; /* 旧自由文本兜底 */
}

/* ---------- 保存 / 取消 ---------- */
function collectRx() {
  const out = [];
  for (const t of ['western', 'infusion', 'chinese']) {
    (window['_rx_' + t] || []).forEach(it => out.push(Object.assign({ type: t }, it)));
  }
  return out;
}
function opdForm() {
  const fd = new FormData();
  fd.append('visit_id', CUR.visit);
  fd.append('chief_complaint', $('f_cc').value); fd.append('present_illness', $('f_pi').value);
  fd.append('allergy_history', $('f_al').value); fd.append('physical_exam', peCompose());
  fd.append('diagnosis', $('f_dx').value); fd.append('notes', $('f_notes').value);
  fd.append('auxiliary_exam', JSON.stringify(window._aux || [])); fd.append('treatments', JSON.stringify(window._trt || []));
  fd.append('rx_items', JSON.stringify(collectRx()));
  const tcm = tcmCollect(); if (tcm) fd.append('tcm_checklist', JSON.stringify(tcm));
  return fd;
}
async function saveOpd() {
  if (!CUR.visit) return alert('未选择患者');
  const r = await fetch(A + 'save_opd', { method: 'POST', body: opdForm() }); const j = await r.json();
  if (j.ok) { alert('已保存，合计 ¥' + (+j.total).toFixed(2)); loadQueue(); } else alert(j.error || '保存失败');
}
async function cancelOpd() {
  if (!CUR.visit) return;
  if (!confirm('放弃当前修改并重新载入？')) return;
  await openVisit(CUR.visit);
}

/* ---------- 辅助检查/治疗 ---------- */
function renderAux() {
  $('auxRows').innerHTML = (window._aux || []).map((a, i) =>
    `<div>${esc(a.name)} × <input style="width:50px" type="number" value="${a.times || 1}" onchange="window._aux[${i}].times=+this.value;calcTotals()"> ¥${(+a.price * (a.times || 1)).toFixed(2)}
     <button class="btn gray sm" onclick="window._aux.splice(${i},1);renderAux()">删</button></div>`).join('');
}
function renderTrt() {
  $('trtRows').innerHTML = (window._trt || []).map((a, i) =>
    `<div>${esc(a.name)} × <input style="width:50px" type="number" value="${a.times || 1}" onchange="window._trt[${i}].times=+this.value;calcTotals()"> ¥${((+a.price || 0) * (+a.times || 1)).toFixed(2)}
     <button class="btn gray sm" onclick="window._trt.splice(${i},1);renderTrt()">删</button></div>`).join('');
}
async function auxSearch(kw) {
  if (!kw) return; const r = await fetch(A + 'search_proc&kw=' + encodeURIComponent(kw)); const j = await r.json();
  showDd($('f_aux'), (j.procs || []).map(p => ({ label: `${p.name} ¥${(+p.price).toFixed(2)}`, fn: () => {
    (window._aux = window._aux || []).push({ name: p.name, price: +p.price, times: 1 }); renderAux(); $('f_aux').value = '';
  }})));
}
async function trtSearch(kw) {
  if (!kw) return; const r = await fetch(A + 'search_proc&kw=' + encodeURIComponent(kw)); const j = await r.json();
  showDd($('f_trt'), (j.procs || []).map(p => ({ label: `${p.name} ¥${(+p.price).toFixed(2)}`, fn: () => {
    (window._trt = window._trt || []).push({ name: p.name, price: +p.price, times: 1 }); renderTrt(); $('f_trt').value = '';
  }})));
}
function showDd(input, opts) {
  let dd = input.parentElement.querySelector('.dd-list'); if (dd) dd.remove();
  if (!opts.length) return;
  dd = document.createElement('div'); dd.className = 'dd-list';
  opts.forEach(o => { const d = document.createElement('div'); d.textContent = o.label; d.onclick = () => { o.fn(); dd.remove(); }; dd.appendChild(d); });
  input.parentElement.appendChild(dd);
  const close = e => { if (!dd.contains(e.target) && e.target !== input) { dd.remove(); document.removeEventListener('click', close); } };
  document.addEventListener('click', close);
}

/* ---------- 处方签（西药/输液/中药） ---------- */
const FREQS = ['qd', 'bid', 'tid', 'q8h', 'q12h', 'qn', 'prn', 'st'];
const DOSE_UNITS = ['片', '粒', '枚', '袋', '支', 'g', 'ml'];
const QTY_UNITS = { western: ['盒', '瓶', '袋', '支', '片'], infusion: ['瓶', '袋', '支'], chinese: ['g'] };
const RX_TITLE = { western: '西药处方', infusion: '输液处方', chinese: '中药处方' };
let _openSheets = { western: false, infusion: false, chinese: false };

function selHtml(opts, cur, onchange) {
  return `<select onchange="${onchange}">` + opts.map(o => `<option ${o === cur ? 'selected' : ''}>${o}</option>`).join('') + '</select>';
}
function stepHtml(t, i, key, unitKey, units) {
  const it = window['_rx_' + t][i];
  return `<span class="step">
    <button type="button" onclick="stepDose('${t}',${i},'${key}',-1)">−</button>
    <input value="${esc(it[key] ?? '')}" onchange="window._rx_${t}[${i}].${key}=this.value;calcTotals()">
    <button type="button" onclick="stepDose('${t}',${i},'${key}',1)">＋</button>
    ${selHtml(units, it[unitKey] || units[0], `window._rx_${t}[${i}].${unitKey}=this.value`)}
  </span>`;
}
function stepDose(t, i, key, d) {
  const arr = window['_rx_' + t]; const v = (parseFloat(arr[i][key]) || 0) + d;
  if (v < 0) return;
  arr[i][key] = String(v); renderRx();
}
function rxSheet(t) {
  const items = window['_rx_' + t] || (window['_rx_' + t] = []);
  const sub = items.reduce((s, it) => s + (+it.price || 0) * (+it.qty || 0), 0);
  const rows = items.map((it, i) => rxRow(t, it, i)).join('');
  return `<div class="rxsheet" id="sheet_${t}">
    <div class="hd"><b style="font-size:13px">${RX_TITLE[t]}</b>
      <button class="btn sm" onclick="addRxRow('${t}')">＋ 添加药行</button>
      <span class="rxsub">小计 ¥${sub.toFixed(2)}</span></div>
    <div class="dd"><input id="dd_${t}" placeholder="输入药名搜索添加，如：阿莫" oninput="drugSearch('${t}')" autocomplete="off" style="width:100%;box-sizing:border-box;padding:5px 8px;border:1px solid #d9d9d9;border-radius:6px;margin:4px 0;font:inherit"></div>
    ${rxTable(t, items, rows)}</div>`;
}
function rxTable(t, items, rows) {
  if (t === 'chinese') {
    return `<table class="rx"><tr><th>序号</th><th>药名</th><th>剂量(g)</th><th>炮制</th><th>特殊煎法</th><th>单价</th><th>金额</th><th></th></tr>${
      items.map((it, i) => `<tr><td>${i + 1}</td>
      <td><input value="${esc(it.name ?? '')}" onchange="window._rx_chinese[${i}].name=this.value"></td>
      <td>${stepHtml('chinese', i, 'qty', 'qty_unit', QTY_UNITS.chinese)}</td>
      <td><input value="${esc(it.prep ?? '')}" onchange="window._rx_chinese[${i}].prep=this.value" placeholder="清炒/蜜炙…"></td>
      <td><input value="${esc(it.decoct ?? '')}" onchange="window._rx_chinese[${i}].decoct=this.value" placeholder="先煎/后下…"></td>
      <td style="width:60px"><input value="${esc(it.price ?? '')}" onchange="window._rx_chinese[${i}].price=this.value;calcTotals()"></td>
      <td>¥${((+it.price || 0) * (+it.qty || 0)).toFixed(2)}</td>
      <td><button class="btn gray sm" onclick="window._rx_chinese.splice(${i},1);renderRx()">删</button></td></tr>`).join('')}</table>`;
  }
  if (t === 'infusion') {
    return `<table class="rx"><tr><th>序号</th><th>药品</th><th>溶媒</th><th>滴速</th><th>频次</th><th>单次剂量</th><th>天数</th><th>开药量</th><th>金额</th><th></th></tr>${
      items.map((it, i) => `<tr><td>${i + 1}</td>
      <td><input value="${esc(it.name ?? '')}" onchange="window._rx_infusion[${i}].name=this.value"></td>
      <td><input value="${esc(it.solvent ?? '')}" onchange="window._rx_infusion[${i}].solvent=this.value" placeholder="0.9%NS 250ml"></td>
      <td style="width:70px"><input value="${esc(it.drip ?? '')}" onchange="window._rx_infusion[${i}].drip=this.value" placeholder="40滴/分"></td>
      <td style="width:80px">${selHtml(FREQS, it.freq || 'qd', `window._rx_infusion[${i}].freq=this.value`)}</td>
      <td style="width:150px">${stepHtml('infusion', i, 'dose', 'dose_unit', DOSE_UNITS)}</td>
      <td style="width:50px"><input value="${esc(it.days ?? '')}" onchange="window._rx_infusion[${i}].days=this.value"></td>
      <td style="width:110px">${stepHtml('infusion', i, 'qty', 'qty_unit', QTY_UNITS.infusion)}</td>
      <td>¥${((+it.price || 0) * (+it.qty || 0)).toFixed(2)}</td>
      <td><button class="btn gray sm" onclick="window._rx_infusion.splice(${i},1);renderRx()">删</button></td></tr>`).join('')}</table>`;
  }
  /* western */
  return `<table class="rx"><tr><th>序号</th><th>药名(规格)</th><th>用法</th><th>频次</th><th>单次剂量</th><th>天数</th><th>开药量</th><th>金额</th><th></th></tr>${
    items.map((it, i) => `<tr><td>${i + 1}</td>
    <td><input value="${esc((it.name ?? '') + (it.spec ? '（' + it.spec + '）' : ''))}" onchange="window._rx_western[${i}].name=this.value"></td>
    <td style="width:70px"><input value="${esc(it.usage ?? '')}" onchange="window._rx_western[${i}].usage=this.value" placeholder="口服"></td>
    <td style="width:80px">${selHtml(FREQS, it.freq || 'qd', `window._rx_western[${i}].freq=this.value`)}</td>
    <td style="width:150px">${stepHtml('western', i, 'dose', 'dose_unit', DOSE_UNITS)}</td>
    <td style="width:50px"><input value="${esc(it.days ?? '')}" onchange="window._rx_western[${i}].days=this.value"></td>
    <td style="width:110px">${stepHtml('western', i, 'qty', 'qty_unit', QTY_UNITS.western)}</td>
    <td>¥${((+it.price || 0) * (+it.qty || 0)).toFixed(2)}</td>
    <td><button class="btn gray sm" onclick="window._rx_western.splice(${i},1);renderRx()">删</button></td></tr>`).join('')}</table>`;
}
function renderRx() {
  $('rxArea').innerHTML = ['western', 'infusion', 'chinese'].map(t => {
    if (!_openSheets[t] && !(CUR.rx && CUR.rx[t]) && !window['_rx_' + t]) return '';
    if (!window['_rx_' + t]) window['_rx_' + t] = (CUR.rx[t] && CUR.rx[t].items) || [];
    _openSheets[t] = true;
    return rxSheet(t);
  }).join('');
  ['western', 'infusion', 'chinese'].forEach(t => { const el = $('dd_' + t); if (el) window.attachSpeech(el); });
  calcTotals();
}
function addRxRow(t) {
  (window['_rx_' + t] = window['_rx_' + t] || []);
  if (t === 'chinese') window['_rx_' + t].push({ name: '', qty: 1, qty_unit: 'g', prep: '', decoct: '', price: 0, drug_id: null });
  else if (t === 'infusion') window['_rx_' + t].push({ name: '', solvent: '', drip: '', usage: '静滴', freq: 'qd', dose: '', dose_unit: '支', days: 1, qty: 1, qty_unit: '瓶', price: 0, drug_id: null, spec: '' });
  else window['_rx_' + t].push({ name: '', spec: '', usage: '口服', freq: 'qd', dose: 1, dose_unit: '片', days: 3, qty: 1, qty_unit: '盒', price: 0, drug_id: null });
  _openSheets[t] = true;
  renderRx();
}
function calcTotals() {
  let g = 0;
  for (const t of ['western', 'infusion', 'chinese']) {
    (window['_rx_' + t] || []).forEach(it => g += (+it.price || 0) * (+it.qty || 0));
    const el = $('sheet_' + t);
    if (el) el.querySelector('.rxsub').textContent = '小计 ¥' + (window['_rx_' + t] || []).reduce((s, it) => s + (+it.price || 0) * (+it.qty || 0), 0).toFixed(2);
  }
  (window._aux || []).forEach(a => g += (+a.price || 0) * (+a.times || 1));
  (window._trt || []).forEach(a => g += (+a.price || 0) * (+a.times || 1));
  $('grandTotal').textContent = '¥' + g.toFixed(2);
}
document.querySelectorAll('#rxTabs button[data-t]').forEach(b => b.onclick = () => {
  _openSheets[b.dataset.t] = true;
  if (!window['_rx_' + b.dataset.t]) addRxRow(b.dataset.t); else renderRx();
});

/* 药品/诊断搜索下拉 */
async function diagSearch(kw) {
  if (!kw || kw.length < 2) return;
  const r = await fetch(A + 'search_diag&kw=' + encodeURIComponent(kw)); const j = await r.json();
  showDd($('f_dx'), (j.diags || []).map(d => ({ label: `${d.name || d.name_tcm} ${d.icd_code} [${d.category === 'TCM' ? '中医' : '西医'}]`, fn: () => {
    $('f_dx').value = ($('f_dx').value ? $('f_dx').value.replace(/[；;]?\s*$/, '；') : '') + (d.name || d.name_tcm) + '；';
  }})));
}
async function drugSearch(t) {
  const inputId = 'dd_' + t;
  const input = $(inputId); const kw = input.value.trim();
  if (!kw) return;
  const r = await fetch(A + 'search_drug&kw=' + encodeURIComponent(kw)); const j = await r.json();
  showDd(input, (j.drugs || []).map(d => ({ label: `${d.name} ${d.spec} ¥${(+d.price).toFixed(2)}`, fn: () => {
    (window['_rx_' + t] = window['_rx_' + t] || []).push({ drug_id: d.id, name: d.name, spec: d.spec, usage: d.default_usage, freq: d.default_freq, dose: '', dose_unit: t === 'chinese' ? 'g' : '片', days: 3, qty: 1, qty_unit: (QTY_UNITS[t] || ['盒'])[0], price: +d.price });
    renderRx(); input.value = '';
  }})));
}

/* ---------- 模板 ---------- */
async function applyTemplate() {
  const r = await fetch(A + 'get_templates'); const j = await r.json();
  if (!j.ok || !j.templates.length) return alert('暂无模板');
  const pick = prompt('输入模板序号：\n' + j.templates.map((t, i) => `${i + 1}. ${t.name}（${(t.items || []).length} 项）`).join('\n'));
  if (pick === null) return;
  const t = j.templates[+pick - 1]; if (!t) return alert('序号无效');
  t.items.forEach(it => {
    const ty = it.type || 'western';
    _openSheets[ty] = true;
    (window['_rx_' + ty] = window['_rx_' + ty] || []).push(Object.assign({}, it));
  });
  renderRx();
}
async function saveTemplate() {
  const name = prompt('模板名称：'); if (!name) return;
  const fd = new FormData(); fd.append('name', name); fd.append('items', JSON.stringify(collectRx()));
  const r = await fetch(A + 'save_rx_template', { method: 'POST', body: fd }); const j = await r.json();
  alert(j.ok ? '模板已保存' : (j.error || '保存失败'));
}

/* ---------- AI 智能诊断（伴随症状 chip 可点击写入病历） ---------- */
function appendSymptom(s) {
  $('f_pi').value = ($('f_pi').value ? $('f_pi').value.replace(/[；;]?\s*$/, '；') : '') + s + '；';
  $('f_pi').dispatchEvent(new Event('change', { bubbles: true }));
  $('f_pi').focus();
}
async function aiDx() {
  if (!CUR.visit) return alert('未选择患者');
  $('aiResult').innerHTML = '<p class="hint">AI 分析中…</p>';
  const fd = new FormData(); fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'ai_dx', { method: 'POST', body: fd }); const j = await r.json();
  if (!j.ok) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.error || '失败') + '</p>'; return; }
  if (!(j.differentials || []).length) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.note || 'AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）') + '</p>'; return; }
  $('aiResult').innerHTML = '<p class="hint">⚠️ 以下内容仅供参考，点击症状标签可写入病历</p>' + j.differentials.map(d =>
    `<div class="aibox"><div class="dxname">${esc(d.diagnosis)}
      <span class="pill" style="background:${d.confidence === '较可能' ? '#1f6f43' : (d.confidence === '可能' ? '#d48806' : '#c0392b')};color:#fff">${esc(d.confidence || '可能')}</span></div>
     <div class="airow"><b>伴随：</b>${(d.associated_symptoms || []).map(s => `<span class="chip sym" onclick="appendSymptom('${esc(s)}')">＋ ${esc(s)}</span>`).join('') || '—'}</div>
     <div class="airow"><b>查体：</b>${esc(d.physical_findings || d.supporting || '—')}</div>
     <div class="airow"><b>检验：</b>${(d.suggested_tests || []).map(t => `<span class="pill">${esc(t)}</span>`).join('') || '—'}</div>
     ${d.against ? `<div class="airow"><b>不符点：</b>${esc(d.against)}</div>` : ''}
     ${d.red_flags ? `<div class="airow" style="color:#c0392b"><b>🚩 转诊指征：</b>${esc(d.red_flags)}</div>` : ''}</div>`).join('')
    + ((j.missing_info || []).length ? `<div class="aibox"><b style="font-size:12px">建议补充采集：</b>${j.missing_info.map(m => `<div style="font-size:12px">· ${esc(m)}</div>`).join('')}</div>` : '')
    + '<p class="ai-note">⚠️ ' + esc(j.disclaimer || 'AI 建议仅供参考，最终诊断以执业医师判断为准。') + '</p>';
}
async function aiRxReview() {
  if (!CUR.visit) return alert('未选择患者');
  $('aiResult').innerHTML = '<p class="hint">处方审查中…</p>';
  const fd = new FormData(); fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'ai_rx_review', { method: 'POST', body: fd }); const j = await r.json();
  if (!j.ok) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.error || '失败') + '</p>'; return; }
  const sevC = { critical: '#c0392b', high: '#d48806', medium: '#2563eb', info: '#888' };
  $('aiResult').innerHTML = '<div class="aibox"><b>' + (j.pass ? '✅ 审查通过' : '⚠️ 存在警示') + '</b><span class="hint">（' + esc(j.note || '') + '）</span></div>'
    + (j.warnings || []).map(w =>
      `<div class="aibox"><span class="pill" style="background:${sevC[w.severity] || '#888'};color:#fff">${esc((w.severity || '').toUpperCase())}</span>
       <span class="hint">[${esc(w.source || '')}] ${esc(w.type || '')}</span>
       <div style="font-size:12px"><b>${esc(w.title || w.drug || '')}</b> ${esc(w.drug && w.title ? '（' + w.drug + '）' : '')}</div>
       ${w.mechanism ? `<div style="font-size:12px">${esc(w.mechanism)}</div>` : ''}
       ${w.suggestion ? `<div style="font-size:12px"><b>建议：</b>${esc(w.suggestion)}</div>` : ''}</div>`).join('')
    + '<p class="ai-note">⚠️ ' + esc(j.disclaimer || '') + '</p>';
}
function rTab(btn) {
  document.querySelectorAll('.col.right .tabs button').forEach(b => b.classList.toggle('on', b === btn));
  $('rtab-dx').style.display = btn.dataset.rtab === 'dx' ? 'block' : 'none';
  $('rtab-hist').style.display = btn.dataset.rtab === 'hist' ? 'block' : 'none';
}

/* ---------- 打印 / 完成 / 建患者 / 编辑患者 ---------- */
function printOpd() {
  if (!CUR.visit) return alert('未选择患者');
  window.open(A + 'print_opd&visit_id=' + CUR.visit);
}
async function finishVisit() {
  if (!CUR.visit) return alert('未选择患者');
  if (!confirm('确认结束本次接诊？')) return;
  await fetch(A + 'save_opd', { method: 'POST', body: opdForm() });
  const fd = new FormData(); fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'finish_visit', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { alert('接诊已完成'); CUR.visit = null; $('recForm').style.display = 'none'; $('ptbar').textContent = '未选择患者'; loadQueue(); }
  else alert(j.error || '失败');
}
function openNewPt() { $('mNew').classList.add('show'); }
async function createPatient() {
  const name = $('np_name').value.trim(); if (!name) return alert('姓名必填');
  const fd = new FormData();
  fd.append('name', name); fd.append('gender', $('np_g').value); fd.append('age', $('np_age').value);
  fd.append('phone', $('np_phone').value); fd.append('allergy', $('np_al').value); fd.append('reg_fee', $('np_fee').value || 10);
  const r = await fetch(A + 'create_patient', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { $('mNew').classList.remove('show'); await loadQueue(); openVisit(j.visit_id); }
  else alert(j.error || '失败');
}
function openEditPt() {
  if (!CUR.patient) return;
  const p = CUR.patient;
  $('ep_name').value = p.name || ''; $('ep_g').value = p.gender || '未知'; $('ep_age').value = p.age || '';
  $('ep_phone').value = p.phone || ''; $('ep_al').value = p.allergy || '';
  $('mEdit').classList.add('show');
}
async function savePatient() {
  if (!CUR.patient) return;
  const fd = new FormData();
  fd.append('patient_id', CUR.patient.id);
  fd.append('name', $('ep_name').value); fd.append('gender', $('ep_g').value);
  fd.append('age', $('ep_age').value); fd.append('phone', $('ep_phone').value); fd.append('allergy', $('ep_al').value);
  const r = await fetch(A + 'update_patient', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { $('mEdit').classList.remove('show'); openVisit(CUR.visit); } else alert(j.error || '失败');
}

/* ---------- 语音问诊录音组件（边录边转 + 一键整理成病历） ---------- */
let REC = null;
function recorderInit() {
  const mount = $('recMount');
  if (!window.Recorder) return; /* recorder.js 未加载（理论不会） */
  REC = window.Recorder.mount(mount, {
    onFinal: function (fin) {
      /* final 结果追加进「转写全文」可编辑框（interim 由组件自身实时区显示） */
      const el = $('f_transcript');
      el.value = (el.value ? el.value.replace(/\s*$/, '') + '\n' : '') + fin;
    },
    onStop: function () { $('orgNote').textContent = '录音结束，可编辑转写后点击「整理成病历」'; }
  });
  if (!REC) mount.innerHTML = '<span class="hint">当前浏览器不支持语音录音，请手动输入</span>';
}

/* 整理成病历：调 act=organize_transcript，成功弹预览确认框；离线兜底插原始转写 */
async function organizeTranscript() {
  const transcript = $('f_transcript').value.trim();
  if (!transcript) return alert('请先录音或输入转写内容');
  if (!CUR.visit) return alert('未选择患者');
  $('orgNote').textContent = 'AI 整理中…';
  const fd = new FormData();
  fd.append('transcript', transcript);
  fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'organize_transcript', { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) {
    $('org_cc').value = j.chief_complaint || '';
    $('org_pi').value = j.present_illness || '';
    $('org_tcm').value = j.tcm_summary || '';
    $('orgNote').textContent = '';
    $('mOrg').classList.add('show');
  } else if (j.raw !== undefined) {
    /* 上游离线兜底：原始转写标注后插入现病史末尾，不丢数据 */
    appendField('f_pi', '【原始转写 待整理】\n' + j.raw);
    $('orgNote').textContent = j.note || 'AI 整理暂不可用';
  } else {
    $('orgNote').textContent = j.error || '整理失败';
  }
}
/* 预览确认：三段追加写入（不覆盖） */
function orgApply() {
  if ($('org_cc').value.trim()) appendField('f_cc', $('org_cc').value.trim());
  if ($('org_pi').value.trim()) appendField('f_pi', $('org_pi').value.trim());
  if ($('org_tcm').value.trim()) appendField('pe_t', $('org_tcm').value.trim(), true);
  $('mOrg').classList.remove('show');
  $('orgNote').textContent = '已写入病历，记得保存';
}
/* 向字段追加文本；peSpecial=true 时写入体格检查文本域语义（pe_t 为自由文本兜底位） */
function appendField(id, text, peSpecial) {
  const el = $(id);
  if (peSpecial) { /* 四诊摘要并入体格检查：优先追加到体温输入后的自由文本 */
    el.value = (el.value ? el.value + '  ' : '') + text;
  } else {
    el.value = (el.value ? el.value.replace(/\s*$/, '') + '\n' : '') + text;
  }
  el.dispatchEvent(new Event('change', { bubbles: true }));
}

/* ---------- 初始化 ---------- */
tcmCount();
speechInit();
recorderInit();
if (window.matchMedia('(max-width: 768px)').matches) document.querySelector('.mview').style.display = 'flex';
loadQueue(); setInterval(loadQueue, 30000);
</script>
</body>
</html>
