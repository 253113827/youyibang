<?php
/* 医生后台 - 患者管理（全字段增删改查 + 已删恢复） */
require __DIR__ . '/config.php';
need_login();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>患者管理 - 福俭堂国医馆</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.navbar{display:flex;gap:4px;align-items:center;background:#1f6f43;color:#fff;padding:0 12px;height:44px}
.navbar .logo{font-weight:700;margin-right:14px}
.navbar a,.navbar span.mod{color:#d6efe0;text-decoration:none;padding:6px 10px;border-radius:6px;font-size:13px}
.navbar a.act{background:#2e9159;color:#fff}
.navbar .mod{opacity:.45;cursor:not-allowed}
.navbar .right{margin-left:auto}
body { background:#f5f6f7; }
.pwrap { max-width:1100px; margin:0 auto; padding:14px; }
.ptop { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
.ptop h1 { font-size:18px; margin-right:auto; }
.ptop input[type=search] { flex:1; min-width:200px; max-width:340px; padding:8px 12px; border:1px solid #ddd; border-radius:6px; font:inherit; }
.ptable { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.ptable th, .ptable td { padding:9px 10px; border-bottom:1px solid #f0f0f0; font-size:13px; text-align:left; white-space:nowrap; }
.ptable th { background:#fafbfc; color:#66707a; font-weight:600; }
.ptable tr:hover td { background:#f8fbfa; }
.ptable td.name b { font-size:14px; }
.ptable .ops a, .ptable .ops button { font-size:12px; cursor:pointer; text-decoration:none; }
.tag-del { color:#e5484d; background:#fdecec; padding:1px 6px; border-radius:8px; font-size:11px; }
.modal { position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:50; }
.modal.show { display:flex; }
.mbox { background:#fff; border-radius:12px; width:min(560px, 94vw); max-height:90vh; overflow:auto; padding:18px; }
.mbox h3 { font-size:16px; margin-bottom:12px; }
.mgrid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.mgrid label { display:block; font-size:12px; color:#66707a; }
.mgrid input, .mgrid select, .mgrid textarea { width:100%; margin-top:3px; padding:8px 10px; border:1px solid #ddd; border-radius:6px; font:inherit; font-size:14px; }
.mgrid .full { grid-column:1/-1; }
@media (max-width:640px) {
  .ptable thead { display:none; }
  .ptable tr { display:block; background:#fff; border-radius:10px; margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
  .ptable td { display:flex; justify-content:space-between; white-space:normal; border:0; }
  .ptable td::before { content:attr(data-l); color:#99a1ad; font-size:12px; flex:none; margin-right:10px; }
  .mgrid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<nav class="navbar">
  <span class="logo">有医帮</span>
  <a href="reception.php">门诊</a>
  <a href="index.php">医案问诊</a>
  <a class="act" href="patients.php">患者管理</a>
  <span class="right"><a href="logout.php">退出</a></span>
</nav>

<div class="pwrap">
  <div class="ptop">
    <h1>👥 患者管理</h1>
    <input type="search" id="q" placeholder="搜索姓名/电话/身份证/建档号" oninput="clearTimeout(window._qt);window._qt=setTimeout(load,300)">
    <label style="font-size:13px;display:flex;align-items:center;gap:4px"><input type="checkbox" id="showDel" onchange="load()" style="width:auto">含已删除</label>
    <button class="btn primary sm" onclick="openEdit(0)">＋ 新增患者</button>
  </div>
  <table class="ptable">
    <thead><tr><th>姓名</th><th>性别/年龄</th><th>电话</th><th>身份证号</th><th>建档号</th><th>过敏史</th><th>就诊次数</th><th>最近就诊</th><th>操作</th></tr></thead>
    <tbody id="tbody"><tr><td colspan="9" style="color:#99a1ad">加载中…</td></tr></tbody>
  </table>
</div>

<div class="modal" id="mEdit">
  <div class="mbox">
    <h3 id="mTitle">编辑患者</h3>
    <div class="mgrid">
      <label>姓名 *<input id="f_name"></label>
      <label>性别<select id="f_g"><option>男</option><option>女</option><option>未知</option></select></label>
      <label>年龄<input id="f_age" inputmode="numeric"></label>
      <label>出生日期<input id="f_birth" type="date"></label>
      <label>电话<input id="f_phone" inputmode="tel"></label>
      <label>身份证号<input id="f_idc" placeholder="患者自助查询需此字段"></label>
      <label class="full">住址<input id="f_addr"></label>
      <label class="full">过敏史<textarea id="f_al" rows="2"></textarea></label>
      <label class="full">备注<textarea id="f_note" rows="2"></textarea></label>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end">
      <button class="btn gray sm" onclick="closeEdit()">取消</button>
      <button class="btn primary sm" onclick="save()">💾 保存</button>
    </div>
  </div>
</div>

<script>
const A = 'api.php?act=';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
async function load() {
  const r = await fetch(A + 'patient_list&q=' + encodeURIComponent($('q').value) + ($('showDel').checked ? '&show_deleted=1' : ''));
  const j = await r.json();
  if (!j.ok) return;
  $('tbody').innerHTML = (j.patients || []).map(p => `<tr${p.is_deleted ? ' style="opacity:.55"' : ''}>
    <td class="name" data-l="姓名"><b>${esc(p.name)}</b>${p.is_deleted ? ' <span class="tag-del">已删除</span>' : ''}</td>
    <td data-l="性别/年龄">${esc(p.gender)} / ${esc(p.age)}岁</td>
    <td data-l="电话">${esc(p.phone || '-')}</td>
    <td data-l="身份证">${esc(p.id_card || '-')}</td>
    <td data-l="建档号">${esc(p.archive_no || '-')}</td>
    <td data-l="过敏史">${esc((p.allergy || '-').slice(0, 14))}</td>
    <td data-l="就诊次数">${p.visit_cnt || 0} 次</td>
    <td data-l="最近就诊">${esc((p.last_visit || '-').slice(0, 10))}</td>
    <td class="ops" data-l="操作">
      <a href="javascript:void(0)" onclick="openEdit(${p.id})">✏️ 编辑</a> ·
      ${p.is_deleted
        ? `<a href="javascript:void(0)" style="color:#00b578" onclick="restore(${p.id})">♻️ 恢复</a>`
        : `<a href="javascript:void(0)" style="color:#e5484d" onclick="del(${p.id}, '${esc(p.name)}')">🗑 删除</a>`}
    </td></tr>`).join('') || '<tr><td colspan="9" style="color:#99a1ad">无匹配患者</td></tr>';
}
async function openEdit(id) {
  window._editId = id;
  $('mTitle').textContent = id ? '编辑患者 #' + id : '新增患者';
  ['f_name','f_age','f_birth','f_phone','f_idc','f_addr','f_al','f_note'].forEach(i => $(i).value = '');
  $('f_g').value = '未知';
  if (id) {
    const r = await fetch(A + 'patient_get&patient_id=' + id); const j = await r.json();
    if (j.ok) {
      const p = j.patient;
      $('f_name').value = p.name || ''; $('f_g').value = p.gender || '未知'; $('f_age').value = p.age || '';
      $('f_birth').value = p.birthdate || ''; $('f_phone').value = p.phone || ''; $('f_idc').value = p.id_card || '';
      $('f_addr').value = p.address || ''; $('f_al').value = p.allergy || ''; $('f_note').value = p.note || '';
    }
  }
  $('mEdit').classList.add('show');
}
function closeEdit() { $('mEdit').classList.remove('show'); }
async function save() {
  const fd = new FormData();
  fd.append('patient_id', window._editId || 0);
  fd.append('name', $('f_name').value); fd.append('gender', $('f_g').value); fd.append('age', $('f_age').value);
  fd.append('birthdate', $('f_birth').value); fd.append('phone', $('f_phone').value); fd.append('id_card', $('f_idc').value);
  fd.append('address', $('f_addr').value); fd.append('allergy', $('f_al').value); fd.append('note', $('f_note').value);
  const r = await fetch(A + 'patient_admin_save', { method: 'POST', body: fd }); const j = await r.json();
  if (j.ok) { closeEdit(); load(); } else alert(j.error || '保存失败');
}
async function del(id, name) {
  if (!confirm('确定删除患者「' + name + '」？\\n病历/处方数据保留，可随时恢复，仅从列表隐藏。')) return;
  const fd = new FormData(); fd.append('patient_id', id);
  await fetch(A + 'del_patient', { method: 'POST', body: fd });
  load();
}
async function restore(id) {
  const fd = new FormData(); fd.append('patient_id', id);
  await fetch(A + 'patient_restore', { method: 'POST', body: fd });
  load();
}
load();
</script>
</body>
</html>
