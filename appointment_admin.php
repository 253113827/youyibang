<?php
/* 福俭堂国医馆 - 预约管理（医生端：叫号/就诊/完成/爽约 + 号源设置） */
require __DIR__ . '/config.php';
need_login();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>预约管理 - 福俭堂国医馆</title>
<style>
.navbar{display:flex;gap:4px;align-items:center;background:#1f6f43;color:#fff;padding:0 12px;height:44px}
.navbar .logo{font-weight:700;margin-right:14px}
.navbar a,.navbar span.mod{color:#d6efe0;text-decoration:none;padding:6px 10px;border-radius:6px;font-size:13px}
.navbar a.act{background:#2e9159;color:#fff}
.navbar .right{margin-left:auto}
body { background:#f5f6f7; }
.awrap { max-width:1100px; margin:0 auto; padding:14px; }
.atop { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
.atop h1 { font-size:18px; margin-right:auto; }
.atop input[type=date] { padding:7px 10px; border:1px solid #ddd; border-radius:6px; font:inherit; }
.atop .slots { font-size:13px; color:#555; display:flex; gap:6px; align-items:center; }
.atop .slots input { width:56px; padding:6px; border:1px solid #ddd; border-radius:6px; text-align:center; }
.nowbanner { background:linear-gradient(135deg,#1565c0,#42a5f5); color:#fff; border-radius:10px; padding:10px 16px; margin-bottom:12px; font-size:15px; display:flex; gap:16px; align-items:center; }
.nowbanner b { font-size:24px; }
.atab { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.atab th, .atab td { padding:9px 10px; border-bottom:1px solid #f0f0f0; font-size:13px; text-align:left; white-space:nowrap; }
.atab th { background:#fafbfc; color:#66707a; font-weight:600; }
.atab tr:hover td { background:#f8fbfa; }
.st { font-size:11px; padding:2px 8px; border-radius:8px; }
.st.pending { color:#ef6c00; background:#fff3e0; }
.st.called { color:#fff; background:#e53935; font-weight:700; }
.st.in_visit { color:#1565c0; background:#e3f2fd; }
.st.done { color:#99a1ad; background:#f0f1f2; }
.st.noshow, .st.cancelled { color:#99a1ad; background:#f0f1f2; }
.atab .ops button { border:0; background:none; cursor:pointer; font-size:12px; padding:3px 6px; border-radius:4px; margin-right:2px; }
.b-call { background:#e53935; color:#fff; }
.b-vis { background:#1565c0; color:#fff; }
.b-done { background:#00b578; color:#fff; }
.b-ns { background:#eee; color:#888; }
.modal { position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:50; }
.modal.show { display:flex; }
.mbox { background:#fff; border-radius:12px; width:min(420px,94vw); padding:18px; }
@media (max-width:640px) {
  .atab thead { display:none; }
  .atab tr { display:block; background:#fff; border-radius:10px; margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
  .atab td { display:flex; justify-content:space-between; white-space:normal; border:0; }
  .atab td::before { content:attr(data-l); color:#99a1ad; font-size:12px; flex:none; margin-right:10px; }
}
</style>
</head>
<body>
<nav class="navbar">
  <span class="logo">有医帮</span>
  <a href="reception.php">门诊</a>
  <a href="index.php">医案问诊</a>
  <a href="patients.php">患者管理</a>
  <a class="act" href="appointment_admin.php">预约管理</a>
  <span class="right"><a href="logout.php">退出</a></span>
</nav>

<div class="awrap">
  <div class="atop">
    <h1>📅 预约管理</h1>
    <input type="date" id="d" value="<?= date('Y-m-d') ?>" onchange="load()">
    <div class="slots" hidden>预留设置（已停用）<input id="rv" type="number" value="0"></div>
    <button class="btn outline-blue sm" onclick="showRemind()">📨 诊前提醒(明日)</button>
    <button class="btn primary sm" onclick="openAdminBook()">＋ 代录预约</button>
    <button class="btn outline sm" style="border-color:#1565c0;color:#1565c0" onclick="openImport()">📥 导入预约(拍照识别)</button>
    <a class="btn outline sm" href="appointment.php" target="_blank">患者端</a>
  </div>

  <div class="nowbanner" id="nowBanner" style="display:none">
    <span>当前叫号</span><b id="nbNo">--</b><span id="nbMeta"></span>
  </div>

  <table class="atab">
    <thead><tr><th>排号</th><th>患者</th><th>性别/年龄</th><th>电话</th><th>医生/时段</th><th>科室</th><th>挂号费</th><th>来源</th><th>状态</th><th>操作</th></tr></thead>
    <tbody id="tbody"><tr><td colspan="8" style="color:#99a1ad">加载中…</td></tr></tbody>
  </table>
</div>

<script>
const A = 'api.php?act=';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
const stCn = { pending: '待诊', called: '已叫号', in_visit: '就诊中', done: '已完成', noshow: '过号', cancelled: '已取消' };
const stCls = { pending: 'pending', called: 'called', in_visit: 'in_visit', done: 'done', noshow: 'noshow', cancelled: 'cancelled' };
async function _initDoctors() {
  try { const r = await fetch(A + 'appt_doctors'); const j = await r.json();
    if (j.ok) { const opts = (j.doctors || []).map(d => `<option>${esc(d.name)}</option>`).join('');
      $('ab_doctor').innerHTML = opts;
      const im = $('im_doctor'); if (im && !im.innerHTML.trim()) im.innerHTML = opts; }
  } catch (e) {}
}
_initDoctors();
async function loadSlots() {
  const r = await fetch(A + 'appt_slots_get'); const j = await r.json();
  if (j.ok) { $('rv').value = j.reserve || 0; }
}
async function saveSlots() {
  const fd = new FormData();
  fd.append('am_slots', 10); fd.append('pm_slots', 10);
  fd.append('reserve_slots', $('rv').value || 0);
  const r = await fetch(A + 'appt_slots_set', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) alert('预留号已保存：每时段末尾 ' + j.reserve + ' 格');
  load();
}
async function load() {
  const r = await fetch(A + 'appt_admin_list&date=' + $('d').value); const j = await r.json();
  if (!j.ok) return;
  const list = j.list || [];
  $('tbody').innerHTML = list.map(a => `<tr>
    <td data-l="排号"><b style="font-size:16px;color:#e53935">${a.queue_no} 号</b></td>
    <td data-l="患者"><b>${esc(a.name)}</b>${a.time_slot ? ' <small style="color:#1565c0">' + esc(a.time_period) + ' ' + esc(a.time_slot) + '</small>' : ' <small style="color:#999">' + esc(a.time_period) + '</small>'}</td>
    <td data-l="性别/年龄">${esc(a.gender)} / ${esc(a.age) || '-'}岁</td>
    <td data-l="电话">${esc(a.phone)}</td>
    <td data-l="医生/时段">${esc(a.doctor || '-')}</td>
    <td data-l="科室">${esc(a.dept || '中医科')}</td>
    <td data-l="挂号费">¥10.00</td>
    <td data-l="来源">${esc(a.source)}</td>
    <td data-l="状态"><span class="st ${stCls[a.status]}">${stCn[a.status]}</span></td>
    <td class="ops" data-l="操作">
      ${a.status === 'pending' ? `<button class="b-call" onclick="act('appt_call', ${a.id})">📢 叫号</button>` : ''}
      ${a.status === 'called' ? `<button class="b-vis" onclick="act('appt_set_status&x=1', ${a.id}, 'in_visit')">🩺 开始就诊</button>` : ''}
      ${a.status === 'in_visit' ? `<button class="b-done" onclick="act('appt_set_status&x=1', ${a.id}, 'done')">✅ 完成</button>` : ''}
      ${['pending', 'called', 'in_visit', 'done'].includes(a.status) ? `<button class="b-ns" style="background:#e3f2fd;color:#1565c0" onclick="openRebook(${a.id}, '${esc(a.name)}', '${esc(a.phone)}', '${esc(a.id_card || '')}')">📅 复诊</button>` : ''}
      ${['pending', 'called'].includes(a.status) ? `<button class="b-ns" onclick="if(confirm('标记为过号？'))act('appt_set_status&x=1', ${a.id}, 'noshow')">过号</button>
      <button class="b-ns" onclick="if(confirm('确定取消该预约？'))act('appt_set_status&x=1', ${a.id}, 'cancelled')">取消</button>` : ''}
    </td></tr>`).join('') || '<tr><td colspan="10" style="color:#99a1ad">该日期暂无预约</td></tr>';
  /* 当前叫号横幅 */
  const cur = list.filter(a => ['called', 'in_visit'].includes(a.status)).sort((a, b) => (b.called_at || '').localeCompare(a.called_at || ''))[0];
  const nb = $('nowBanner');
  if (cur) { nb.style.display = 'flex'; $('nbNo').textContent = cur.queue_no + ' 号 ' + cur.name[0] + '**'; $('nbMeta').textContent = cur.time_period + ' · ' + stCn[cur.status] + (cur.called_at ? ' · ' + cur.called_at.slice(11, 16) : ''); }
  else nb.style.display = 'none';
}
async function act(actName, id, status) {
  const fd = new FormData(); fd.append('id', id);
  if (status) fd.append('status', status);
  const url = actName.includes('appt_set_status') ? A + 'appt_set_status' : A + 'appt_call';
  const r = await fetch(url, { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) load(); else alert(j.error || '操作失败');
}
/* ===== 代录预约 / 复诊预约 / 诊前提醒 ===== */
let _abCtx = { date: '', doctor: '', period: '上午' };
function openAdminBook() { _openAb(''); }
function openRebook(id, name, phone, idc) { _openAb(JSON.stringify({ name, phone, idc })); }
function _openAb(pre) {
  const p = pre ? JSON.parse(pre) : { name: '', phone: '', idc: '' };
  $('ab_name').value = p.name; $('ab_phone').value = p.phone; $('ab_idc').value = p.idc || '';
  $('ab_date').value = _abCtx.date = $('d').value;
  $('ab_slot').value = ''; $('abSlotGrid').innerHTML = '<span style="font-size:12px;color:#bbb">选择时段后显示</span>';
  $('mAdmin').classList.add('show');
  setTimeout(() => $('ab_doctor').value = '施宣赫', 0);
  _loadAbSlots();
}
function _loadAbSlots() {
  _abCtx.doctor = $('ab_doctor').value; _abCtx.period = $('ab_period').value; _abCtx.date = $('ab_date').value;
  const fd = new FormData();
  fd.append('doctor', _abCtx.doctor); fd.append('date', _abCtx.date); fd.append('period', _abCtx.period);
  fetch(A + 'appt_slots_view', { method: 'POST', body: fd }).then(r => r.json()).then(j => {
    if (!j.ok) { $('abSlotGrid').innerHTML = '<span style="font-size:12px;color:#c62828">' + esc(j.error || '加载失败') + '</span>'; return; }
    $('abSlotGrid').innerHTML = (j.slots || []).map(s => {
      if (s.full) return `<button type="button" disabled style="background:#eee;color:#bbb;cursor:not-allowed" class="slotbtn">${s.slot}<br><small>满</small></button>`;
      const rsv = s.reserved ? 'border-style:dashed;border-color:#ef6c00;color:#ef6c00' : 'border:1.5px solid var(--primary);color:var(--primary)';
      return `<button type="button" data-slot="${s.slot}" class="slotbtn" style="background:#fff;${rsv}" onclick="_pickAb(this)">${s.slot}${s.reserved ? '<br><small>预留</small>' : (s.capacity > 1 ? '<br><small>余' + (s.capacity - s.taken) + '</small>' : '')}</button>`;
    }).join('');
  });
}
function _pickAb(btn) {
  document.querySelectorAll('#abSlotGrid .slotbtn').forEach(b => { b.style.background = '#fff'; });
  btn.style.background = 'var(--primary)'; btn.style.color = '#fff';
  $('ab_slot').value = btn.dataset.slot;
}
async function submitAdminBook() {
  const fd = new FormData();
  ['name', 'phone', 'id_card', 'doctor', 'date', 'period', 'time_slot'].forEach(k => {
    const map = { name: 'ab_name', phone: 'ab_phone', id_card: 'ab_idc', doctor: 'ab_doctor', date: 'ab_date', period: 'ab_period', time_slot: 'ab_slot' };
    fd.append(k, $(map[k]).value);
  });
  fd.append('source', $('ab_src').value);
  const r = await fetch(A + 'appt_admin_book', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { $('mAdmin').classList.remove('show'); load(); } else alert(j.error || '失败');
}
async function showRemind() {
  const r = await fetch(A + 'appt_remind_list&date=' + $('d').value); const j = await r.json();
  if (!j.ok) return alert(j.error || '失败');
  const list = j.list || [];
  $('remindBody').innerHTML = list.length ? list.map(a =>
    `<div style="border-bottom:1px dashed #eee;padding:8px 0;font-size:13px">
      <b>${esc(a.name)}</b> ${esc(a.gender)}/${esc(a.age)}岁 · ${esc(a.phone)}<br>
      <span style="color:#1565c0">${esc(a.visit_date)} ${esc(a.time_period)} ${esc(a.time_slot || '')} ${esc(a.doctor)} 第${a.queue_no}号</span>
      <button class="btn outline sm" onclick="copyRemind(this.dataset.t)" data-t="${esc(`【福俭堂国医馆】${a.name}您好，您已预约 ${a.visit_date} ${a.time_period} ${a.time_slot || ''} ${a.doctor}医生门诊（第${a.queue_no}号）。就诊以现场叫号顺序为准，预约时间不等于就诊时间，请提前15分钟到店。地址可导航“福俭堂国医馆”。`)}">📋 复制短信</button>
    </div>`).join('') : '<p style="color:#99a1ad;font-size:13px">该日期无待提醒预约</p>';
  $('mRemind').classList.add('show');
}
function copyRemind(t) {
  navigator.clipboard.writeText(t).then(() => alert('已复制，可粘贴到微信/短信发送'));
}
/* ===== 导入预约（拍照 AI 识别）===== */
let _importList = [];
function openImport() {
  $('im_date').value = $('d').value;
  _initDoctors().then(() => { const s = $('im_doctor'); s.value = '施宣赫'; if (!s.value) s.selectedIndex = 0; });
  $('im_file').value = ''; $('imStatus').textContent = ''; $('imPreview').innerHTML = '';
  $('imCommitBtn').style.display = 'none'; _importList = [];
  $('mImport').classList.add('show');
}
async function importParse(input) {
  if (!input.files || !input.files[0]) return;
  const st = $('imStatus'), pv = $('imPreview');
  st.textContent = 'AI 识别中…（约 30-90 秒，识别完成后可核对再导入）';
  pv.innerHTML = '';
  $('imCommitBtn').style.display = 'none';
  try {
    const fd = new FormData();
    fd.append('doctor', $('im_doctor').value);
    fd.append('img', input.files[0]);
    const r = await fetch(A + 'appt_import_parse', { method: 'POST', body: fd }); const j = await r.json();
    if (!j.ok) { st.textContent = '❌ ' + (j.error || '识别失败'); return; }
    _importList = j.list;
    st.textContent = '✅ 识别出 ' + j.list.length + ' 条预约，请核对下方清单（时间段列已转为开始时间）后点「确认导入」';
    const rows = j.list.map((x, i) => `<tr>
      <td><input type="checkbox" checked onchange="window._importList[${i}]._skip = !this.checked"></td>
      <td><input value="${esc(x.name)}" onchange="window._importList[${i}].name=this.value" style="width:80px;border:1px solid #ddd;border-radius:4px;padding:2px 4px;font:inherit"></td>
      <td><input value="${esc(x.phone)}" onchange="window._importList[${i}].phone=this.value" style="width:110px;border:1px solid #ddd;border-radius:4px;padding:2px 4px;font:inherit"></td>
      <td>${esc(x.period)}</td>
      <td><input value="${esc(x.time_slot)}" onchange="window._importList[${i}].time_slot=this.value" style="width:60px;border:1px solid #ddd;border-radius:4px;padding:2px 4px;font:inherit"></td>
    </tr>`).join('');
    pv.innerHTML = '<table class="atab"><thead><tr><th>导入</th><th>姓名</th><th>手机号</th><th>时段</th><th>时间</th></tr></thead>' + rows + '</table>';
    $('imCommitBtn').style.display = 'inline-block';
  } catch (e) { st.textContent = '❌ 网络异常：' + e.message; }
}
async function importCommit() {
  const dErr = [];
  if (!$('im_doctor').value) dErr.push('请选择医生');
  if (!$('im_date').value) dErr.push('请选择预约日期');
  if (dErr.length) return alert(dErr.join('；'));
  const list = _importList.filter(x => !x._skip).map(x => ({ name: x.name, phone: x.phone, period: x.period, time_slot: x.time_slot }));
  if (!list.length) return alert('没有勾选任何行');
  $('imCommitBtn').disabled = true;
  try {
    const fd = new FormData();
    fd.append('doctor', $('im_doctor').value); fd.append('date', $('im_date').value);
    fd.append('list', JSON.stringify(list));
    const r = await fetch(A + 'appt_import_commit', { method: 'POST', body: fd }); const j = await r.json();
    if (j.ok) {
      let msg = '✅ 成功导入 ' + j.imported + ' 条预约';
      if ((j.skipped || []).length) msg += '，跳过 ' + j.skipped.length + ' 条：' + j.skipped.join('、');
      $('imStatus').textContent = msg;
      $('imPreview').innerHTML = ''; $('imCommitBtn').style.display = 'none';
      $('d').value = $('im_date').value; load();
      if (typeof notifyAttention === "function") notifyAttention('预约导入完成');
    } else { $('imStatus').textContent = '❌ ' + (j.error || '导入失败'); $('imCommitBtn').disabled = false; }
  } catch (e) { $('imStatus').textContent = '❌ 网络异常'; $('imCommitBtn').disabled = false; }
}
loadSlots(); load();
setInterval(load, 30000);
</script>

<div class="modal" id="mAdmin">
  <div class="mbox">
    <h3 style="font-size:16px;margin-bottom:10px">＋ 代录预约（现场/电话/复诊）</h3>
    <div class="mgrid">
      <label>姓名 *<input id="ab_name"></label>
      <label>电话 *<input id="ab_phone" inputmode="tel"></label>
      <label class="full">身份证号<input id="ab_idc"></label>
      <label>医生<select id="ab_doctor" onchange="_loadAbSlots()"></select></label>
      <label>日期<input id="ab_date" type="date" onchange="_loadAbSlots()"></label>
      <label>时段<select id="ab_period" onchange="_loadAbSlots()"><option>上午</option><option>下午</option></select></label>
      <label>来源<select id="ab_src"><option>现场</option><option>电话</option><option>复诊</option></select></label>
    </div>
    <label style="display:block;font-size:12px;color:#555;margin:10px 0 4px">选择时间段</label>
    <div id="abSlotGrid" style="display:flex;flex-wrap:wrap;gap:6px"></div>
    <input type="hidden" id="ab_slot">
    <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end">
      <button class="btn gray sm" onclick="document.getElementById('mAdmin').classList.remove('show')">取消</button>
      <button class="btn primary sm" onclick="submitAdminBook()">💾 保存</button>
    </div>
  </div>
</div>

<div class="modal" id="mImport">
  <div class="mbox">
    <h3 style="font-size:16px;margin-bottom:10px">📥 导入预约（拍照识别打印表）</h3>
    <div class="mgrid">
      <label>预约日期 *<input id="im_date" type="date"></label>
      <label>医生<select id="im_doctor"></select></label>
    </div>
    <label style="display:block;font-size:12px;color:#555;margin:10px 0 4px">预约表照片（拍照或选择图片，AI 自动识别姓名/手机号/时间段）</label>
    <label class="filebtn" style="background:var(--primary);border-radius:6px;padding:8px 14px;font-size:13px;display:inline-block;cursor:pointer">📷 拍照/选择图片<input type="file" id="im_file" accept="image/*" capture="environment" hidden onchange="importParse(this)"></label>
    <div id="imStatus" style="font-size:12px;color:#8a6d1a;margin-top:8px"></div>
    <div id="imPreview" style="max-height:40vh;overflow:auto;margin-top:8px"></div>
    <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
      <button class="btn gray sm" onclick="document.getElementById('mImport').classList.remove('show')">关闭</button>
      <button class="btn primary sm" id="imCommitBtn" style="display:none" onclick="importCommit()">💾 确认导入</button>
    </div>
  </div>
</div>

<div class="modal" id="mRemind">
  <div class="mbox">
    <h3 style="font-size:16px;margin-bottom:10px">📨 诊前提醒（<span id="remindDate"></span> 预约患者）</h3>
    <div id="remindBody" style="max-height:60vh;overflow:auto"></div>
    <div style="text-align:right;margin-top:10px"><button class="btn gray sm" onclick="document.getElementById('mRemind').classList.remove('show')">关闭</button></div>
  </div>
</div>
</body>
</html>
