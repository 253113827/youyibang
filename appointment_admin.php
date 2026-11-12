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
    <div class="slots">号源：上午 <input id="am" type="number" min="1" max="50"> 下午 <input id="pm" type="number" min="1" max="50">
      <button class="btn outline sm" onclick="saveSlots()">💾 保存号源</button></div>
    <a class="btn outline-blue sm" href="appointment.php" target="_blank">患者端预览</a>
  </div>

  <div class="nowbanner" id="nowBanner" style="display:none">
    <span>当前叫号</span><b id="nbNo">--</b><span id="nbMeta"></span>
  </div>

  <table class="atab">
    <thead><tr><th>排号</th><th>患者</th><th>性别/年龄</th><th>电话</th><th>时段</th><th>来源</th><th>状态</th><th>操作</th></tr></thead>
    <tbody id="tbody"><tr><td colspan="8" style="color:#99a1ad">加载中…</td></tr></tbody>
  </table>
</div>

<script>
const A = 'api.php?act=';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
const stCn = { pending: '待诊', called: '已叫号', in_visit: '就诊中', done: '已完成', noshow: '过号', cancelled: '已取消' };
const stCls = { pending: 'pending', called: 'called', in_visit: 'in_visit', done: 'done', noshow: 'noshow', cancelled: 'cancelled' };
async function loadSlots() {
  const r = await fetch(A + 'appt_slots_get'); const j = await r.json();
  if (j.ok) { $('am').value = j.am_slots; $('pm').value = j.pm_slots; }
}
async function saveSlots() {
  const fd = new FormData();
  fd.append('am_slots', $('am').value); fd.append('pm_slots', $('pm').value);
  const r = await fetch(A + 'appt_slots_set', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) alert('号源已保存：上午 ' + j.am_slots + ' / 下午 ' + j.pm_slots);
  load();
}
async function load() {
  const r = await fetch(A + 'appt_admin_list&date=' + $('d').value); const j = await r.json();
  if (!j.ok) return;
  const list = j.list || [];
  $('tbody').innerHTML = list.map(a => `<tr>
    <td data-l="排号"><b style="font-size:16px;color:#e53935">${a.queue_no} 号</b></td>
    <td data-l="患者"><b>${esc(a.name)}</b></td>
    <td data-l="性别/年龄">${esc(a.gender)} / ${esc(a.age) || '-'}岁</td>
    <td data-l="电话">${esc(a.phone)}</td>
    <td data-l="时段">${esc(a.time_period)}</td>
    <td data-l="来源">${esc(a.source)}</td>
    <td data-l="状态"><span class="st ${stCls[a.status]}">${stCn[a.status]}</span></td>
    <td class="ops" data-l="操作">
      ${a.status === 'pending' ? `<button class="b-call" onclick="act('appt_call', ${a.id})">📢 叫号</button>` : ''}
      ${a.status === 'called' ? `<button class="b-vis" onclick="act('appt_set_status&x=1', ${a.id}, 'in_visit')">🩺 开始就诊</button>` : ''}
      ${a.status === 'in_visit' ? `<button class="b-done" onclick="act('appt_set_status&x=1', ${a.id}, 'done')">✅ 完成</button>` : ''}
      ${['pending', 'called'].includes(a.status) ? `<button class="b-ns" onclick="if(confirm('标记为过号？'))act('appt_set_status&x=1', ${a.id}, 'noshow')">过号</button>
      <button class="b-ns" onclick="if(confirm('确定取消该预约？'))act('appt_set_status&x=1', ${a.id}, 'cancelled')">取消</button>` : ''}
    </td></tr>`).join('') || '<tr><td colspan="8" style="color:#99a1ad">该日期暂无预约</td></tr>';
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
loadSlots(); load();
setInterval(load, 30000);
</script>
</body>
</html>
