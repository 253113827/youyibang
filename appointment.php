<?php
/* 福俭堂国医馆 - 预约挂号（患者端，公开）
 * 就诊前：预约 | 就诊中：排号+叫号提醒 | 就诊后：记录
 * 文案要求：以现场叫号顺序为准，预约时间≠就诊时间 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>预约挂号 - 福俭堂国医馆</title>
<style>
:root { --primary:#00b578; --bg:#f5f6f7; --card:#fff; --line:#eee; --txt:#26303a; --sub:#99a1ad; --warn:#ef6c00; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font:15px/1.6 -apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif; background:var(--bg); color:var(--txt); min-height:100vh; }
.wrap { max-width:480px; margin:0 auto; padding:0 14px 40px; }
header { text-align:center; padding:18px 0 10px; }
header .logo { display:inline-block; background:var(--primary); color:#fff; font-weight:800; padding:8px 18px; border-radius:10px; font-size:18px; letter-spacing:2px; text-decoration:none; }
header p { color:var(--sub); font-size:12px; margin-top:6px; }
/* 当前叫号横幅 */
.nowcall { background:linear-gradient(135deg,#1565c0,#42a5f5); color:#fff; border-radius:12px; padding:12px 16px; margin-bottom:12px; display:flex; align-items:center; gap:12px; }
.nowcall .no { font-size:34px; font-weight:800; }
.nowcall .lbl { font-size:12px; opacity:.85; }
.nowcall .meta { margin-left:auto; text-align:right; font-size:12px; opacity:.9; }
.tabs { display:flex; background:#fff; border-radius:12px; padding:4px; margin-bottom:12px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.tabs button { flex:1; border:0; background:none; padding:10px; font:inherit; font-size:15px; border-radius:9px; cursor:pointer; color:#667; }
.tabs button.on { background:var(--primary); color:#fff; font-weight:600; }
.pane { display:none; }
.pane.show { display:block; }
.card { background:var(--card); border-radius:12px; padding:18px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:12px; }
.card h2 { font-size:15px; margin-bottom:10px; color:var(--primary); }
label { display:block; font-size:13px; color:#555; margin:9px 0 4px; }
input, select { width:100%; padding:11px 12px; border:1px solid #ddd; border-radius:8px; font:inherit; font-size:15px; }
input:focus, select:focus { outline:none; border-color:var(--primary); }
.row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
button.go { width:100%; margin-top:14px; padding:12px; border:0; border-radius:8px; background:var(--primary); color:#fff; font-size:16px; font-weight:600; cursor:pointer; }
button.go:disabled { opacity:.6; }
.err { color:#e5484d; font-size:13px; margin-top:8px; display:none; }
.ok-box { background:#e6f7f0; border:1px solid #b8e6d2; border-radius:8px; padding:12px; margin-top:10px; font-size:14px; display:none; }
.ok-box b { color:var(--primary); font-size:18px; }
.appt { background:var(--card); border-radius:12px; padding:14px 16px; margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.appt .t { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.appt .t b { font-size:15px; }
.st { font-size:11px; padding:2px 10px; border-radius:10px; }
.st.pending { color:#ef6c00; background:#fff3e0; }
.st.called { color:#fff; background:#e53935; font-weight:700; animation:blink 1s infinite; }
.st.in_visit { color:#1565c0; background:#e3f2fd; }
.st.done { color:#99a1ad; background:#f0f1f2; }
.st.noshow { color:#99a1ad; background:#f0f1f2; }
@keyframes blink { 50% { opacity:.5; } }
.appt dl { font-size:13px; line-height:1.8; color:#555; }
.appt .no { font-size:22px; font-weight:800; color:#e53935; }
.notice { background:#fff8e1; border:1px solid #ffe082; border-radius:10px; padding:10px 14px; font-size:12px; color:#8d6e00; line-height:1.8; margin-bottom:12px; }
.slotbtn { padding:7px 12px;border-radius:6px;font:inherit;font-size:13px;cursor:pointer; }
.hint { text-align:center; color:var(--sub); font-size:12px; margin-top:16px; }
a.home { color:var(--primary); font-size:12px; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a class="logo" href="index.php">福俭堂国医馆</a>
    <p>预约挂号 · <a class="home" href="index.php">返回首页</a> · <a class="home" href="patient_query.php">就诊记录</a></p>
  </header>

  <div class="nowcall" id="nowCall" style="display:none">
    <div><div class="lbl">当前叫号</div><div class="no" id="ncNo">--</div></div>
    <div class="meta" id="ncMeta"></div>
  </div>

  <div class="notice">
    📢 <b>温馨提示：</b>就诊以<b>现场叫号顺序为准</b>（紧急特殊情况除外）；门诊情况复杂，<b>预约时间不等于就诊时间</b>，请耐心等待叫号。叫到您的号时，本页面会发出铃声提醒。
  </div>

  <div class="tabs">
    <button class="on" data-p="book" onclick="switchPane('book')">预约挂号</button>
    <button data-p="mine" onclick="switchPane('mine'); loadMine();">我的预约</button>
  </div>

  <!-- 就诊前：预约 -->
  <div class="pane show" id="pane-book">
    <div class="card">
      <h2>📝 填写预约信息</h2>
      <label>姓名 *</label><input id="b_name" placeholder="就诊人姓名">
      <div class="row2">
        <div><label>性别</label><select id="b_g"><option>女</option><option>男</option><option>未知</option></select></div>
        <div><label>年龄</label><input id="b_age" inputmode="numeric" placeholder="如 32"></div>
      </div>
      <label>手机号 *（用于查询和叫号提醒）</label><input id="b_phone" inputmode="tel" placeholder="11 位手机号">
      <label>身份证号 *（用于建档与查询就诊记录）</label><input id="b_idc" placeholder="18 位身份证号">
      <div class="row2">
        <div><label>预约医生 *</label><select id="b_doctor" onchange="loadSlots()"></select></div>
        <div><label>就诊日期 *</label><select id="b_date" onchange="syncPeriods(); loadSlots()"></select></div>
      </div>
      <div class="row2">
        <div><label>时段 *</label><select id="b_period" onchange="loadSlots()"><option>上午</option><option>下午</option></select></div>
        <div><label>预约渠道</label><select id="b_src"><option>网上</option><option>电话</option><option>现场</option></select></div>
      </div>
      <label>选择时间段 *（点击可选的时段；灰色已被约）</label>
      <div id="slotGrid" style="display:flex;flex-wrap:wrap;gap:6px"><span style="font-size:12px;color:#bbb">请先选择医生和日期</span></div>
      <input type="hidden" id="b_slot" value="">
      <button class="go" id="bbtn" onclick="book()">提交预约</button>
      <div class="err" id="berr"></div>
      <div class="ok-box" id="bok"></div>
    </div>
  </div>

  <!-- 就诊前/中/后：我的预约 -->
  <div class="pane" id="pane-mine">
    <div class="card">
      <h2>🔍 查询我的预约</h2>
      <label>姓名 *</label><input id="m_name">
      <label>手机号 *</label><input id="m_phone" inputmode="tel">
      <button class="go" onclick="loadMine(true)">查询</button>
      <div class="err" id="merr"></div>
    </div>
    <div id="mineList"></div>
  </div>

  <p class="hint">福俭堂国医馆 · youyibang.com</p>
</div>

<script>
const A = 'api.php?act=';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
function switchPane(p) {
  document.querySelectorAll('.pane').forEach(x => x.classList.remove('show'));
  $('pane-' + p).classList.add('show');
  document.querySelectorAll('.tabs button').forEach(b => b.classList.toggle('on', b.dataset.p === p));
  if (p === 'mine') { $('m_name').value = $('b_name').value; $('m_phone').value = $('b_phone').value; }
}
/* 日期下拉：今天起 14 天 */
let _doctors = [];
async function initDoctors() {
  try {
    const r = await fetch(A + 'appt_doctors'); const j = await r.json();
    if (!j.ok) return;
    _doctors = j.doctors || [];
    const sel = $('b_doctor');
    sel.innerHTML = '';
    _doctors.forEach(d => { const o = document.createElement('option'); o.value = d.name; o.textContent = d.name + ' · ' + (d.dept || ''); sel.appendChild(o); });
    $('b_doctor').onchange = () => { syncPeriods(); loadSlots(); };
    syncPeriods();
  } catch (e) {}
}
function doctorWeek() {
  const d = _doctors.find(x => x.name === $('b_doctor').value);
  return (d && d.week) || {};
}
/* 按所选日期星期刷新时段下拉 */
function syncPeriods() {
  const sel = $('b_period');
  const wd = new Date($('b_date').value + 'T00:00:00').getDay();
  const sessions = doctorWeek()[String(wd)] || [];
  const old = sel.value;
  sel.innerHTML = sessions.length
    ? sessions.map(s => `<option>${s.p}</option>`).join('')
    : '<option value="">无门诊</option>';
  if (sessions.some(s => s.p === old)) sel.value = old;
}
async function loadSlots() {
  const grid = $('slotGrid'), hid = $('b_slot');
  hid.value = '';
  const doctor = $('b_doctor').value, date = $('b_date').value, period = $('b_period').value;
  if (!doctor || !date) return;
  grid.innerHTML = '<span style="font-size:12px;color:#bbb">加载中…</span>';
  try {
    const fd = new FormData();
    fd.append('doctor', doctor); fd.append('date', date); fd.append('period', period);
    fd.append('phone', $('b_phone').value.trim());
    const r = await fetch(A + 'appt_slots_view', { method: 'POST', body: fd }); const j = await r.json();
    if (!j.ok) { grid.innerHTML = '<span style="font-size:12px;color:#c62828">' + esc(j.error || '加载失败') + '</span>'; return; }
    if (j.noweek) { grid.innerHTML = '<span style="font-size:13px;color:#c62828">该医生当日无门诊（出诊时间：周五 14:30-17:30 / 18:30-21:00，周日 09:00-12:00 / 14:30-17:30），请选择出诊日期</span>'; return; }
    let html = '';
    (j.sessions || []).forEach(ses => {
      const left = ses.cap - ses.booked;
      html += `<div style="flex-basis:100%;font-size:12px;color:#8a6d1a;margin:4px 0 2px"><b>${esc(ses.period)}</b> ${esc(ses.start)}-${esc(ses.end)} · 已约 ${ses.booked}/${ses.cap}（剩 ${left}）</div>`;
    });
    html += (j.slots || []).map(s => {
      const key = s.period + '|' + s.slot;
      if (s.full) return `<button type="button" disabled class="slotbtn" style="background:#eee;color:#bbb;cursor:not-allowed" data-key="${key}">${s.slot}<br><small>满</small></button>`;
      if (s.reserved) return `<button type="button" disabled class="slotbtn" style="background:#fff8e1;border:1.5px dashed #ef6c00;color:#bbb;cursor:not-allowed" title="预留号（到店挂号/电话预约可用）" data-key="${key}">${s.slot}<br><small>预留</small></button>`;
      return `<button type="button" data-slot="${s.slot}" data-key="${key}" class="slotbtn" style="background:#fff;border:1.5px solid var(--primary);color:var(--primary)" onclick="pickSlot(this)">${s.slot}${s.capacity > 1 ? '<br><small>余' + (s.capacity - s.taken) + '</small>' : ''}</button>`;
    }).join('');
    grid.innerHTML = html;
  } catch (e) { grid.innerHTML = '<span style="font-size:12px;color:#c62828">网络异常</span>'; }
}
function pickSlot(btn) {
  document.querySelectorAll('#slotGrid .slotbtn').forEach(b => { if (b.dataset.slot) { b.style.background = '#fff'; b.style.color = 'var(--primary)'; b.style.fontWeight = '400'; } });
  btn.style.background = 'var(--primary)'; btn.style.color = '#fff'; btn.style.fontWeight = '600';
  $('b_slot').value = btn.dataset.slot;
}
(function () {
  const sel = $('b_date');
  const wd = ['周日','周一','周二','周三','周四','周五','周六'];
  for (let i = 0; i < 14; i++) {
    const d = new Date(); d.setDate(d.getDate() + i);
    const ds = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    const o = document.createElement('option');
    o.value = ds;
    o.textContent = (i === 0 ? '今天 ' : i === 1 ? '明天 ' : '') + ds.slice(5) + ' ' + wd[d.getDay()];
    sel.appendChild(o);
  }
})();
async function book() {
  const err = $('berr'); err.style.display = 'none';
  const name = $('b_name').value.trim(), phone = $('b_phone').value.trim();
  const idc = $('b_idc').value.trim().replace(/\s+/g, '');
  if (!name || !phone) { err.textContent = '请填写姓名和手机号'; err.style.display = 'block'; return; }
  if (!/^1\d{10}$/.test(phone)) { err.textContent = '手机号格式不正确'; err.style.display = 'block'; return; }
  if (idc.length < 15) { err.textContent = '请填写正确的身份证号'; err.style.display = 'block'; return; }
  if (!$('b_period').value) { err.textContent = '该日期医生无门诊，请换出诊日期（周五/周日）'; err.style.display = 'block'; return; }
  if (!$('b_slot').value) { err.textContent = '请点击选择一个时间段'; err.style.display = 'block'; return; }
  const btn = $('bbtn'); btn.disabled = true; btn.textContent = '提交中…';
  try {
    const fd = new FormData();
    fd.append('name', name); fd.append('gender', $('b_g').value); fd.append('age', $('b_age').value);
    fd.append('phone', phone); fd.append('id_card', idc);
    fd.append('doctor', $('b_doctor').value);
    fd.append('date', $('b_date').value); fd.append('period', $('b_period').value);
    fd.append('time_slot', $('b_slot').value);
    fd.append('source', $('b_src').value);
    const r = await fetch(A + 'appt_book', { method: 'POST', body: fd }); const j = await r.json();
    if (j.ok) {
      $('bok').style.display = 'block';
      $('bok').innerHTML = '✅ 预约成功！<b>' + esc(j.date) + ' ' + esc(j.period) + ' ' + esc(j.slot) + '</b>（' + esc(j.doctor) + '）第 <b>' + j.queue_no + '</b> 号<br><span style="font-size:12px;color:#666">就诊以现场叫号顺序为准，预约时间不等于就诊时间，请提前 15 分钟到店，耐心等待叫号。</span>';
      sessionStorage.setItem('appt_name', name); sessionStorage.setItem('appt_phone', phone);
      setTimeout(() => { switchPane('mine'); loadMine(); }, 2500);
    } else { err.textContent = j.error || '预约失败'; err.style.display = 'block'; }
  } catch (e) { err.textContent = '网络异常，请稍后重试'; err.style.display = 'block'; }
  finally { btn.disabled = false; btn.textContent = '提交预约'; }
}
const stCn = { pending: '待诊', called: '🔔 已叫号', in_visit: '就诊中', done: '已完成', noshow: '过号', cancelled: '已取消' };
let _knownCalled = {}; /* 已提醒过的预约 id */
async function loadMine(showErr) {
  const err = $('merr'); err.style.display = 'none';
  const name = $('m_name').value.trim() || sessionStorage.getItem('appt_name') || '';
  const phone = $('m_phone').value.trim() || sessionStorage.getItem('appt_phone') || '';
  if (!name || !phone) { if (showErr) { err.textContent = '请填写姓名和手机号'; err.style.display = 'block'; } return; }
  try {
    const fd = new FormData(); fd.append('name', name); fd.append('phone', phone);
    const r = await fetch(A + 'appt_mine', { method: 'POST', body: fd }); const j = await r.json();
    if (!j.ok) { if (showErr) { err.textContent = j.error || '查询失败'; err.style.display = 'block'; } return; }
    renderMine(j.appointments || []);
  } catch (e) { if (showErr) { err.textContent = '网络异常'; err.style.display = 'block'; } }
}
function renderMine(list) {
  const box = $('mineList');
  if (!list.length) { box.innerHTML = '<div class="card"><p style="color:#99a1ad;font-size:13px">暂无预约记录（近 7 日），去「预约挂号」 tab 预约。</p></div>'; return; }
  box.innerHTML = list.map(a => {
    const isToday = a.visit_date === new Date().toISOString().slice(0, 10);
    return `<div class="appt">
      <div class="t"><b>${esc(a.visit_date)} ${esc(a.time_period)} ${esc(a.time_slot || '')}</b><span class="st ${a.status}">${stCn[a.status] || a.status}</span></div>
      <dl>${a.status !== 'cancelled' ? `<div>预约人：${esc(a.name_masked || '')} · ${esc(a.doctor || a.dept)} · 排号：<span class="no">${a.queue_no} 号</span></div>` : ''}
      ${a.called_at ? `<div>叫号时间：${esc(a.called_at.slice(5, 16))}</div>` : ''}
      ${a.finished_at && a.status === 'done' ? `<div>完成时间：${esc(a.finished_at.slice(5, 16))}</div>` : ''}</dl>
    </div>`;
  }).join('');
}
/* 诊前提醒：明日有预约 → 页面顶部横幅 */
async function checkTomorrow() {
  const name = sessionStorage.getItem('appt_name'), phone = sessionStorage.getItem('appt_phone');
  if (!name || !phone) return;
  try {
    const fd = new FormData(); fd.append('name', name); fd.append('phone', phone);
    const r = await fetch(A + 'appt_mine', { method: 'POST', body: fd }); const j = await r.json();
    if (!j.ok) return;
    const tmr = new Date(); tmr.setDate(tmr.getDate() + 1);
    const ds = tmr.getFullYear() + '-' + String(tmr.getMonth()+1).padStart(2,'0') + '-' + String(tmr.getDate()).padStart(2,'0');
    const t = (j.appointments || []).find(a => a.visit_date === ds && a.status === 'pending');
    const nb = document.querySelector('.notice');
    if (t && nb && !document.getElementById('tmrNotice')) {
      const d = document.createElement('div');
      d.id = 'tmrNotice';
      d.style.cssText = 'background:#e3f2fd;border:1px solid #90caf9;border-radius:10px;padding:10px 14px;font-size:12px;color:#1565c0;line-height:1.8;margin-bottom:12px';
      d.innerHTML = '📅 <b>诊前提醒：</b>您明天（' + ds + '）' + t.time_period + ' ' + (t.time_slot || '') + ' 有 ' + (t.doctor || '') + ' 医生门诊预约（第 ' + t.queue_no + ' 号），请提前 15 分钟到店。';
      nb.parentNode.insertBefore(d, nb);
    }
  } catch (e) {}
}
setTimeout(checkTomorrow, 1500);
/* 当前叫号横幅 + 被叫号铃声轮询 */
let _lastCurNo = null, _ringed = {};
function ring() {
  try { navigator.vibrate && navigator.vibrate([200, 100, 200, 100, 200]); } catch (e) {}
  try {
    const ac = window._ac || (window._ac = new (window.AudioContext || window.webkitAudioContext)());
    if (ac.state === 'suspended' && ac.resume) ac.resume();
    [0, 0.35, 0.7, 1.05].forEach(t => {
      const o = ac.createOscillator(), g = ac.createGain();
      o.frequency.value = 988; g.gain.value = 0.25;
      o.connect(g); g.connect(ac.destination);
      o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.18);
      g.gain.setValueAtTime(0.25, ac.currentTime + t);
      g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + t + 0.18);
    });
  } catch (e) {}
  const old = document.title;
  let on = false, n = 0;
  const t = setInterval(() => { document.title = (on = !on) ? '🔔 轮到你就诊了！' : old; if (++n > 20) { clearInterval(t); document.title = old; } }, 800);
}
async function poll() {
  try {
    const r = await fetch(A + 'appt_now'); const j = await r.json();
    if (j.ok) {
      const cur = j.current;
      const box = $('nowCall');
      if (cur) {
        box.style.display = 'flex';
        $('ncNo').textContent = cur.queue_no + ' 号';
        $('ncMeta').innerHTML = esc(cur.time_period) + '<br>' + (cur.status === 'in_visit' ? '就诊中' : '已叫号') + ' · ' + esc(cur.name ? cur.name[0] + '**' : '');
      } else box.style.display = 'none';
    }
    /* 我的预约被叫号 → 响铃 */
    if (sessionStorage.getItem('appt_phone')) {
      const fd = new FormData();
      fd.append('name', $('m_name').value.trim() || sessionStorage.getItem('appt_name') || '');
      fd.append('phone', sessionStorage.getItem('appt_phone'));
      if (fd.get('name') && fd.get('phone')) {
        const r2 = await fetch(A + 'appt_mine', { method: 'POST', body: fd }); const j2 = await r2.json();
        if (j2.ok) {
          const today = new Date().toISOString().slice(0, 10);
          (j2.appointments || []).forEach(a => {
            if (a.visit_date === today && a.status === 'called' && !_ringed[a.id]) {
              _ringed[a.id] = true;
              if (!$('pane-mine').classList.contains('show')) switchPane('mine');
              $('m_name').value = fd.get('name'); $('m_phone').value = fd.get('phone');
              loadMine();
              ring();
            }
          });
          renderMine(j2.appointments || []);
        }
      }
    }
  } catch (e) {}
}
/* 首次进入：若有记忆的预约人，自动查我的预约 */
if (sessionStorage.getItem('appt_name')) {
  $('m_name').value = sessionStorage.getItem('appt_name');
  $('m_phone').value = sessionStorage.getItem('appt_phone');
  loadMine();
}
poll(); setInterval(poll, 20000);
initDoctors();
/* 用户任意交互解锁音频（手机要求手势内） */
document.addEventListener('click', function unlock() {
  try { const ac = window._ac || (window._ac = new (window.AudioContext || window.webkitAudioContext)()); if (ac.state === 'suspended') ac.resume(); } catch (e) {}
  document.removeEventListener('click', unlock);
}, { once: true });
</script>
</body>
</html>
