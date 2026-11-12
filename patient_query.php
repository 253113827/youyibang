<?php
/* 患者自助查询（公开，无需登录）：姓名+电话+身份证号 → 最近 7 日就诊记录 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>就诊记录查询 - 福俭堂国医馆</title>
<style>
:root { --primary:#00b578; --bg:#f5f6f7; --card:#fff; --line:#eee; --txt:#26303a; --sub:#99a1ad; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font:15px/1.6 -apple-system,BlinkMacSystemFont,"PingFang SC","Helvetica Neue",sans-serif; background:var(--bg); color:var(--txt); min-height:100vh; }
.wrap { max-width:480px; margin:0 auto; padding:16px 14px 40px; }
header { text-align:center; padding:22px 0 14px; }
header .logo { display:inline-block; background:var(--primary); color:#fff; font-weight:800; padding:8px 18px; border-radius:10px; font-size:18px; letter-spacing:2px; }
header p { color:var(--sub); font-size:12px; margin-top:8px; }
.card { background:var(--card); border-radius:12px; padding:18px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:14px; }
.card h2 { font-size:15px; margin-bottom:12px; color:var(--primary); }
label { display:block; font-size:13px; color:#555; margin:10px 0 4px; }
input { width:100%; padding:11px 12px; border:1px solid #ddd; border-radius:8px; font:inherit; font-size:15px; }
input:focus { outline:none; border-color:var(--primary); }
button { width:100%; margin-top:16px; padding:12px; border:0; border-radius:8px; background:var(--primary); color:#fff; font-size:16px; font-weight:600; cursor:pointer; }
button:disabled { opacity:.6; }
.err { color:#e5484d; font-size:13px; margin-top:10px; display:none; }
.visit { background:var(--card); border-radius:12px; padding:16px; margin-bottom:12px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.visit .vt { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.visit .vt b { font-size:15px; }
.visit .vt .st { font-size:11px; color:var(--primary); background:#e6f7f0; padding:2px 8px; border-radius:10px; }
.visit .vt .st.done { color:var(--sub); background:#f0f1f2; }
.visit dl { font-size:13px; line-height:1.8; }
.visit dt { color:var(--sub); font-size:12px; margin-top:6px; }
.visit dd { white-space:pre-wrap; word-break:break-all; }
.visit .rx { margin-top:8px; border-top:1px dashed var(--line); padding-top:6px; font-size:13px; }
.visit .rx b { color:#8a6d1a; font-size:12px; }
.hint { text-align:center; color:var(--sub); font-size:12px; margin-top:20px; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="logo">福俭堂国医馆</div>
    <p>就诊记录自助查询 · 仅显示最近 7 日</p>
  </header>

  <div class="card" id="formCard">
    <h2>🔍 查询我的就诊记录</h2>
    <label>姓名 *</label>
    <input id="q_name" placeholder="就诊时登记的姓名">
    <label>电话 *</label>
    <input id="q_phone" inputmode="tel" placeholder="就诊时登记的电话">
    <label>身份证号 *</label>
    <input id="q_idc" placeholder="就诊时登记的身份证号">
    <button id="qbtn" onclick="doQuery()">查询</button>
    <div class="err" id="qerr"></div>
  </div>

  <div id="results"></div>
  <p class="hint">信息仅用于本人记录核对 · 福俭堂国医馆 · youyibang.com</p>
</div>
<script>
async function doQuery() {
  const name = document.getElementById('q_name').value.trim();
  const phone = document.getElementById('q_phone').value.trim();
  const idc = document.getElementById('q_idc').value.trim();
  const err = document.getElementById('qerr');
  err.style.display = 'none';
  if (!name || !phone || !idc) { err.textContent = '请填写姓名、电话和身份证号'; err.style.display = 'block'; return; }
  const btn = document.getElementById('qbtn');
  btn.disabled = true; btn.textContent = '查询中…';
  try {
    const fd = new FormData();
    fd.append('name', name); fd.append('phone', phone); fd.append('id_card', idc);
    const r = await fetch('api.php?act=patient_self_query', { method: 'POST', body: fd });
    const j = await r.json();
    if (!j.ok) { err.textContent = j.error || '查询失败'; err.style.display = 'block'; return; }
    render(j.patient, j.visits || []);
  } catch (e) {
    err.textContent = '网络异常，请稍后重试'; err.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = '查询';
  }
}
const esc = s => String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
const rxType = t => ({western:'西药处方', chinese:'中药处方', infusion:'输液处方'}[t] || t);
function render(name, visits) {
  document.getElementById('formCard').style.display = 'none';
  const box = document.getElementById('results');
  if (!visits.length) {
    box.innerHTML = '<div class="card"><h2>📭 暂无记录</h2><p style="font-size:13px;color:#99a1ad">' + esc(name) + '，您最近 7 日内没有就诊记录。如有疑问请联系诊所。</p><button onclick="location.reload()">返回重新查询</button></div>';
    return;
  }
  let html = '<div class="card"><h2>📋 ' + esc(name) + ' 的就诊记录（近 7 日 ' + visits.length + ' 次）</h2></div>';
  for (const v of visits) {
    const ins = v.inspection || {};
    const insLine = [ins.face ? '面色' + ins.face : '', ins.tongue_body ? '舌质' + ins.tongue_body : '', ins.tongue_coat ? '舌苔' + ins.tongue_coat : ''].filter(Boolean).join('，');
    html += '<div class="visit"><div class="vt"><b>' + esc(v.date) + '</b><span class="st' + (v.status === 'done' ? ' done' : '') + '">' + (v.status === 'done' ? '已完成' : '就诊中') + '</span></div><dl>';
    if (v.diagnosis) html += '<dt>诊断</dt><dd>' + esc(v.diagnosis) + '</dd>';
    if (v.chief_complaint) html += '<dt>主诉</dt><dd>' + esc(v.chief_complaint) + '</dd>';
    if (v.present_illness) html += '<dt>现病史</dt><dd>' + esc(v.present_illness) + '</dd>';
    if (v.physical_exam) html += '<dt>体格检查</dt><dd>' + esc(v.physical_exam) + '</dd>';
    if (insLine) html += '<dt>望诊</dt><dd>' + esc(insLine) + '</dd>';
    if (v.notes) html += '<dt>医嘱</dt><dd>' + esc(v.notes) + '</dd>';
    for (const rx of (v.prescriptions || [])) {
      html += '<div class="rx"><b>' + esc(rx.label || rxType(rx.type)) + '</b>：' + esc((rx.items || []).join('、')) + '</div>';
    }
    for (const ph of (v.photos || [])) {
      html += '<div class="rx" style="display:flex;gap:10px;align-items:flex-start">'
        + '<a href="' + esc(ph.path) + '" target="_blank" style="flex:none"><img loading="lazy" src="' + esc(ph.path) + '" style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #eee"></a>'
        + '<div style="flex:1;min-width:0;font-size:12px;color:#666"><b style="color:#8a6d1a">' + esc(ph.type) + '</b>'
        + (ph.ai ? '<div style="margin-top:2px;white-space:pre-wrap">' + esc(ph.ai) + '</div>' : '<div style="margin-top:2px;color:#bbb">AI 分析未生成</div>')
        + '</div></div>';
    }
    html += '</dl></div>';
  }
  html += '<div class="card" style="text-align:center;font-size:11px;color:#99a1ad">免责声明：本内容由有医帮AI系统辅助生成，仅供参考，不构成诊疗指令，请以医生签字确认为准。</div>';
  html += '<button onclick="location.reload()">返回重新查询</button>';
  box.innerHTML = html;
  window.scrollTo(0, 0);
}
</script>
</body>
</html>
