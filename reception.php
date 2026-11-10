<?php
/* 有医帮 - 门诊接诊工作台（三栏：队列 | 病历处方 | AI诊断/患者历史） */
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
.frow { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
label.fl { display:block; margin-bottom:8px; font-size:13px; color:#333; }
label.fl textarea, label.fl input, label.fl select { width:100%; box-sizing:border-box; margin-top:3px; padding:6px 8px; border:1px solid #d9d9d9; border-radius:6px; font:inherit; }
textarea { resize:vertical; }
.tabs { display:flex; gap:4px; border-bottom:2px solid #1f6f43; margin-bottom:8px; flex-wrap:wrap; }
.tabs button { border:0; background:#e8ebe9; padding:5px 12px; cursor:pointer; border-radius:6px 6px 0 0; font-size:12px; }
.tabs button.on { background:#1f6f43; color:#fff; }
table.rx { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:6px; }
table.rx th, table.rx td { border:1px solid #e0e0e0; padding:3px 5px; }
table.rx th { background:#f5f5f5; font-weight:600; }
table.rx input { width:100%; border:0; font:inherit; box-sizing:border-box; }
.rxtotal { text-align:right; font-weight:700; color:#c0392b; margin:4px 0 8px; }
.dd { position:relative; }
.dd-list { position:absolute; z-index:30; background:#fff; border:1px solid #ddd; border-radius:6px; max-height:220px; overflow:auto; width:100%; box-shadow:0 4px 12px rgba(0,0,0,.12); }
.dd-list div { padding:6px 10px; cursor:pointer; font-size:12px; }
.dd-list div:hover { background:#eaf6ef; }
.aibox { border:1px dashed #bbb; border-radius:8px; padding:10px; margin-bottom:10px; }
.dxname { font-weight:700; color:#1f6f43; margin:6px 0 4px; }
.pill { display:inline-block; background:#eaf6ef; color:#1f6f43; border-radius:12px; padding:2px 10px; font-size:11px; margin:2px 4px 2px 0; }
.ai-note { font-size:11px; color:#999; }
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
  <div class="frow">
  <label class="fl">主诉<textarea id="f_cc" rows="2"></textarea></label>
  <label class="fl">现病史/既往史<textarea id="f_pi" rows="2"></textarea></label>
  </div>
  <div class="frow">
  <label class="fl">过敏史/个人史<input id="f_al"></label>
  <label class="fl">体格检查（体温/血压/心率）<input id="f_pe" placeholder="T 36.5℃  BP 120/80  HR 76"></label>
  </div>
  <label class="fl">诊断（输入可联想诊断字典）<textarea id="f_dx" rows="2" oninput="diagSearch(this.value)"></textarea></label>
  <label class="fl">辅助检查（自动计费）<div id="auxRows"></div>
    <div class="dd"><input id="f_aux" placeholder="输入项目名搜索，如：血常规" oninput="auxSearch(this.value)" autocomplete="off"></div>
  </label>
  <label class="fl">治疗项目<div id="trtRows"></div>
    <div class="dd"><input id="f_trt" placeholder="输入治疗项目搜索，如：针灸" oninput="trtSearch(this.value)" autocomplete="off"></div>
  </label>

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
    <button class="btn blue" onclick="printOpd()">🖨 打印</button>
    <button class="btn red" onclick="finishVisit()">✅ 完成接诊</button>
    <a id="caseLink" class="btn ghost" href="#" style="display:none;text-decoration:none;line-height:14px">进入医案 →</a>
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
    <span class="hint">基于主诉/现病史/查体生成鉴别诊断</span>
    <div id="aiResult"></div>
  </div>
  <div id="rtab-hist" style="display:none"><div id="ptHist">（先选择患者）</div></div>
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

<script>
const A = 'api.php?act=';
let CUR = { visit: null, patient: null, record: null, rx: {} };
let Q = { today: [], history: [] };
const $ = id => document.getElementById(id);
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

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
    ${p.case_id ? `<a class="btn ghost sm" href="case.php?id=${p.case_id}">进入医案 →</a>` : '<span class="hint">未关联医案</span>'}`;
  $('recForm').style.display = 'block';
  const r = j.record || {};
  $('f_cc').value = r.chief_complaint || ''; $('f_pi').value = r.present_illness || '';
  $('f_al').value = r.allergy_history || p.allergy || ''; $('f_pe').value = r.physical_exam || '';
  $('f_dx').value = r.diagnosis || ''; $('f_notes').value = r.notes || '';
  window._aux = JSON.parse(r.auxiliary_exam || '[]'); window._trt = JSON.parse(r.treatments || '[]');
  renderAux(); renderTrt(); renderRx(); renderHist(j.history);
  $('aiResult').innerHTML = '<p class="hint">未运行。点击上方按钮生成鉴别诊断。</p>';
  renderQueue();
}
function renderHist(list) {
  $('ptHist').innerHTML = (list || []).map(v =>
    `<div class="histitem"><b>${esc(v.registered_at.slice(0, 10))}</b> ${({waiting:'待诊',in_visit:'就诊中',done:'已诊'})[v.status]}<br>诊断：${esc(v.diagnosis || '—')}</div>`).join('')
    || '<p class="hint">该患者无既往就诊。</p>';
}

/* ---------- 保存 ---------- */
function collectRx() {
  const out = [];
  for (const t of ['western', 'infusion', 'chinese']) {
    (window['_rx_' + t] || []).forEach(it => out.push(Object.assign({ type: t }, it)));
  }
  return out;
}
async function saveOpd() {
  if (!CUR.visit) return alert('未选择患者');
  const fd = new FormData();
  fd.append('visit_id', CUR.visit);
  fd.append('chief_complaint', $('f_cc').value); fd.append('present_illness', $('f_pi').value);
  fd.append('allergy_history', $('f_al').value); fd.append('physical_exam', $('f_pe').value);
  fd.append('diagnosis', $('f_dx').value); fd.append('notes', $('f_notes').value);
  fd.append('auxiliary_exam', JSON.stringify(window._aux || [])); fd.append('treatments', JSON.stringify(window._trt || []));
  fd.append('rx_items', JSON.stringify(collectRx()));
  const r = await fetch(A + 'save_opd', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { alert('已保存，合计 ¥' + (+j.total).toFixed(2)); loadQueue(); } else alert(j.error || '保存失败');
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

/* ---------- 处方 ---------- */
const RXCOLS = { western: ['药品名', '用法', '频次', '单次剂量', '天数', '开药量', '单价', '金额', ''],
                 infusion: ['药品名', '用法', '频次', '剂量', '天数', '数量', '单价', '金额', ''],
                 chinese: ['药材名', '煎法', '频次', '剂量(g)', '天数', '帖数', '单价', '金额', ''] };
function renderRx() {
  $('rxArea').innerHTML = ['western', 'infusion', 'chinese'].map(t => {
    if (!(CUR.rx && CUR.rx[t]) && !window['_rx_' + t]) return '';
    const items = window['_rx_' + t] || (window['_rx_' + t] = CUR.rx[t].items || []);
    const rows = items.map((it, i) => rxRow(t, it, i)).join('');
    return `<div><b style="font-size:13px">${({western:'西药',infusion:'输液',chinese:'中药'})[t]}处方</b>
      <div class="dd"><input id="dd_${t}" placeholder="输入药名搜索添加，如：阿莫" oninput="drugSearch('${t}')" autocomplete="off" style="width:100%;box-sizing:border-box;padding:5px 8px;border:1px solid #d9d9d9;border-radius:6px;margin-bottom:4px;font:inherit"></div>
      <table class="rx"><tr>${RXCOLS[t].map(h => `<th>${h}</th>`).join('')}</tr>${rows}</table></div>`;
  }).join('');
  calcTotals();
}
function rxRow(t, it, i) {
  const inp = (k, w) => `<input value="${esc(it[k] ?? '')}" onchange="window._rx_${t}[${i}].${k}=this.value;rxChanged()">`;
  return `<tr><td>${inp('name')}</td><td>${inp('usage')}</td><td>${inp('freq')}</td><td style="width:70px">${inp('dose')}</td>
  <td style="width:50px">${inp('days')}</td><td style="width:60px">${inp('qty')}</td><td style="width:60px">${inp('price')}</td>
  <td>¥${((+it.price || 0) * (+it.qty || 0)).toFixed(2)}</td>
  <td><button class="btn gray sm" onclick="window._rx_${t}.splice(${i},1);renderRx()">删</button></td></tr>`;
}
function addRxRow(t) {
  (window['_rx_' + t] = window['_rx_' + t] || []).push({ name: '', usage: '口服', freq: 'qd', dose: '', days: '', qty: '', price: 0, drug_id: null, spec: '' });
  renderRx();
}
function rxChanged() { calcTotals(); }
function calcTotals() {
  let g = 0;
  for (const t of ['western', 'infusion', 'chinese']) {
    (window['_rx_' + t] || []).forEach(it => g += (+it.price || 0) * (+it.qty || 0));
  }
  (window._aux || []).forEach(a => g += (+a.price || 0) * (+a.times || 1));
  (window._trt || []).forEach(a => g += (+a.price || 0) * (+a.times || 1));
  $('grandTotal').textContent = '¥' + g.toFixed(2);
}
document.querySelectorAll('#rxTabs button[data-t]').forEach(b => b.onclick = () => addRxRow(b.dataset.t));

/* 药品搜索下拉 */
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
    (window['_rx_' + t] = window['_rx_' + t] || []).push({ drug_id: d.id, name: d.name, spec: d.spec, usage: d.default_usage, freq: d.default_freq, dose: '', days: '', qty: 1, price: +d.price });
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
  t.items.forEach(it => { (window['_rx_' + (it.type || 'western')] = window['_rx_' + (it.type || 'western')] || []).push(Object.assign({}, it)); });
  renderRx();
}
async function saveTemplate() {
  const name = prompt('模板名称：'); if (!name) return;
  const fd = new FormData(); fd.append('name', name); fd.append('items', JSON.stringify(collectRx()));
  const r = await fetch(A + 'save_rx_template', { method: 'POST', body: fd }); const j = await r.json();
  alert(j.ok ? '模板已保存' : (j.error || '保存失败'));
}

/* ---------- AI ---------- */
async function aiDx() {
  if (!CUR.visit) return alert('未选择患者');
  $('aiResult').innerHTML = '<p class="hint">AI 分析中…</p>';
  const fd = new FormData(); fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'ai_dx', { method: 'POST', body: fd }); const j = await r.json();
  if (!j.ok) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.error || '失败') + '</p>'; return; }
  if (!(j.differentials || []).length) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.note || 'AI 服务暂不可用') + '</p>'; return; }
  const confC = { '较可能': '#1f6f43', '可能': '#d48806' };
  $('aiResult').innerHTML = j.differentials.map(d =>
    `<div class="aibox"><div class="dxname">${esc(d.diagnosis)}
      <span class="pill" style="background:${d.confidence === '较可能' ? '#1f6f43' : (d.confidence === '可能' ? '#d48806' : '#c0392b')};color:#fff">${esc(d.confidence || '可能')}</span></div>
     <div style="font-size:12px"><b>依据：</b>${esc(d.supporting || '—')}${d.against ? '<br><b>不符点：</b>' + esc(d.against) : ''}</div>
     <div style="font-size:12px"><b>建议检查：</b>${(d.suggested_tests || []).map(t => `<span class="pill">${esc(t)}</span>`).join('') || '—'}</div>
     ${d.red_flags ? `<div style="font-size:12px;color:#c0392b"><b>🚩 转诊指征：</b>${esc(d.red_flags)}</div>` : ''}</div>`).join('')
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

/* ---------- 打印 / 完成 / 建患者 ---------- */
function printOpd() {
  if (!CUR.visit) return alert('未选择患者');
  window.open(A + 'print_opd&visit_id=' + CUR.visit);
}
async function finishVisit() {
  if (!CUR.visit) return alert('未选择患者');
  if (!confirm('确认结束本次接诊？')) return;
  await saveOpdSilent();
  const fd = new FormData(); fd.append('visit_id', CUR.visit);
  const r = await fetch(A + 'finish_visit', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { alert('接诊已完成'); CUR.visit = null; $('recForm').style.display = 'none'; $('ptbar').textContent = '未选择患者'; loadQueue(); }
  else alert(j.error || '失败');
}
async function saveOpdSilent() { try { await saveOpd(); } catch (e) {} }
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

if (window.matchMedia('(max-width: 768px)').matches) document.querySelector('.mview').style.display = 'flex';
loadQueue(); setInterval(loadQueue, 30000);
</script>
</body>
</html>
