<?php
/* 有医帮 - 移动端接诊（单栏：队列列表 → 患者卡/病历/处方 → 完成） */
require __DIR__ . '/config.php';
need_login();
$uid = (int)($_SESSION['uid'] ?? 1);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>有医帮 - 移动接诊</title>
<style>
* { box-sizing: border-box; }
body { margin:0; font:15px/1.6 -apple-system,"PingFang SC",sans-serif; background:#f0f2f5; }
.nav { background:#1f6f43; color:#fff; padding:12px 14px; display:flex; justify-content:space-between; position:sticky; top:0; }
.nav a { color:#d6efe0; text-decoration:none; font-size:13px; }
.wrap { padding:12px; max-width:640px; margin:0 auto; }
.card { background:#fff; border-radius:12px; padding:14px; margin-bottom:12px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.ptcard { display:flex; gap:12px; align-items:center; }
.avatar { width:44px; height:44px; border-radius:50%; background:#1f6f43; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; flex:none; }
.tag { font-size:11px; padding:2px 8px; border-radius:10px; }
.tag.waiting { background:#e6f0ff; color:#2563eb; } .tag.in_visit { background:#fff7e6; color:#d48806; } .tag.done { background:#f0f0f0; color:#888; }
.qitem { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f0f0f0; cursor:pointer; }
.qitem:last-child { border-bottom:0; }
.fld { margin-bottom:8px; font-size:14px; }
.fld label { color:#888; font-size:12px; display:block; }
.fld textarea, .fld input { width:100%; border:1px solid #d9d9d9; border-radius:8px; padding:8px 10px; font:inherit; margin-top:3px; }
.rxline { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #eee; font-size:13px; }
.btn { width:100%; padding:13px; border:0; border-radius:10px; background:#1f6f43; color:#fff; font-size:16px; cursor:pointer; }
.btn.blue { background:#2563eb; } .btn.gray { background:#6b7280; }
.sec-t { font-size:14px; color:#333; font-weight:600; margin:10px 0 6px; border-left:3px solid #1f6f43; padding-left:8px; }
.hint { font-size:12px; color:#999; }
</style>
</head>
<body>
<div class="nav"><b>有医帮 · 移动接诊</b><a href="reception.php">桌面版</a></div>
<div class="wrap">
  <div id="view">
    <div class="card"><div class="hint">加载中…</div></div>
  </div>
</div>
<script>
const A = 'api.php?act=';
const $ = id => document.getElementById(id);
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let Q = [];

async function boot() {
  const r = await fetch(A + 'queue'); const j = await r.json();
  if (!j.ok) { $('view').innerHTML = '<div class="card hint">' + esc(j.error || '加载失败') + '</div>'; return; }
  Q = j.today || [];
  const wait = Q.filter(v => v.status !== 'done'), done = Q.filter(v => v.status === 'done');
  $('view').innerHTML = '<div class="card"><div class="sec-t">今日待诊（' + wait.length + '）</div>' +
    (wait.map(v => qrow(v)).join('') || '<p class="hint">暂无待诊患者</p>') + '</div>' +
    '<div class="card"><div class="sec-t">今日已诊（' + done.length + '）</div>' +
    (done.map(v => qrow(v)).join('') || '<p class="hint">暂无</p>') + '</div>';
}
function qrow(v) {
  return `<div class="qitem" onclick="openVisit(${v.id})">
    <div><b>${esc(v.name)}</b> <span class="hint">${esc(v.gender)}/${esc(v.age)}岁 ${esc(v.phone || '')}</span></div>
    <span class="tag ${v.status}">${({waiting:'待诊',in_visit:'就诊中',done:'已诊'})[v.status]}</span></div>`;
}
async function openVisit(vid) {
  const r = await fetch(A + 'get_opd&visit_id=' + vid); const j = await r.json();
  if (!j.ok) { alert(j.error || '加载失败'); return; }
  const p = j.patient, rec = j.record || {}, rx = j.prescriptions || {};
  let rxHtml = '';
  const tcn = { western: '西药处方', chinese: '中药处方', infusion: '输液处方' };
  for (const t of ['western', 'infusion', 'chinese']) {
    if (!rx[t] || !(rx[t].items || []).length) continue;
    rxHtml += `<div class="sec-t">${tcn[t]}（合计 ¥${(+rx[t].total).toFixed(2)}）</div>` +
      rx[t].items.map(it => `<div class="rxline"><span>${esc(it.name)} ${esc(it.spec || '')}<br><span class="hint">${esc((it.usage || '') + ' ' + (it.freq || '') + ' ' + (it.dose || '') + '×' + (it.days || '') + '天')}</span></span><span>×${esc(it.qty || '')}</span></div>`).join('');
  }
  $('view').innerHTML = `
  <div class="card ptcard">
    <div class="avatar">${esc(p.name[0])}</div>
    <div style="flex:1"><b>${esc(p.name)}</b> ${esc(p.gender)}/${esc(p.age)}岁<br>
    <span class="hint">${esc(p.phone || '无电话')} · 档案 ${esc(p.archive_no)}</span><br>
    <span class="tag ${p.status}">${({waiting:'待诊',in_visit:'就诊中',done:'已诊'})[p.status]}</span>
    ${p.case_id ? ` <a href="case.php?id=${p.case_id}" class="hint">进入医案（问诊/拍照/AI解析）→</a>` : ''}</div>
  </div>
  <div class="card">
    <div class="sec-t">病历（关键项可编辑）</div>
    <div class="fld"><label>主诉</label><input id="f_cc" value="${esc(rec.chief_complaint || p.chief_complaint || '')}"></div>
    <div class="fld"><label>诊断</label><input id="f_dx" value="${esc(rec.diagnosis || '')}"></div>
    <div class="fld"><label>注意事项</label><textarea id="f_notes" rows="2">${esc(rec.notes || '')}</textarea></div>
    <div class="fld hint">现病史：${esc(rec.present_illness || '未填写')}<br>过敏史：${esc(rec.allergy_history || p.allergy || '未填写')}<br>查体：${esc(rec.physical_exam || '未填写')}</div>
  </div>
  <div class="card">${rxHtml || '<p class="hint">暂无处方（请在桌面端录入）</p>'}</div>
  <div class="card">
    ${p.case_id ? '<a class="btn blue" style="text-decoration:none;display:block;text-align:center;margin-bottom:10px" href="case.php?id=' + p.case_id + '">📷 拍照上传 / 语音问诊（医案）</a>' : '<p class="hint">未关联医案，无法拍照上传</p>'}
    <button class="btn" onclick="save()">💾 保存</button>
    <div style="height:10px"></div>
    <button class="btn gray" onclick="finish()">✅ 完成接诊</button>
  </div>`;
}
async function save() {
  const fd = new FormData();
  fd.append('visit_id', CUR); fd.append('chief_complaint', $('f_cc').value);
  fd.append('diagnosis', $('f_dx').value); fd.append('notes', $('f_notes').value);
  fd.append('present_illness', window._pi || ''); fd.append('allergy_history', '');
  fd.append('physical_exam', ''); fd.append('auxiliary_exam', '[]'); fd.append('treatments', '[]');
  fd.append('rx_items', '[]');
  const r = await fetch(A + 'save_opd', { method: 'POST', body: fd }); const j = await r.json();
  alert(j.ok ? '已保存' : (j.error || '失败'));
}
async function finish() {
  if (!confirm('确认完成本次接诊？')) return;
  const fd = new FormData(); fd.append('visit_id', CUR);
  const r = await fetch(A + 'finish_visit', { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) { alert('已完成'); boot(); } else alert(j.error || '失败');
}
let CUR = 0;
const _open = openVisit;
openVisit = async vid => { CUR = vid; _open(vid); };
boot();
</script>
</body>
</html>
