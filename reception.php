<?php
/* 有医帮 - 门诊接诊工作台（1:1 还原 ABC诊所管家 UI）
 * 布局：深蓝导航栏 | 左栏患者队列 | 中栏病历处方（黄色编辑区） | 右栏智能诊断
 * v3：按截图还原 UI，功能逻辑（JS/API/ID）不变 */
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
<script src="assets/speech.js?v=3"></script>
<script>var YYB_ASR = <?= empty($ASR_ENDPOINTS) ? 'false' : 'true' ?>;</script>
<script src="assets/recorder.js?v=8"></script>
<style>
:root {
  --nav-blue: #33538f; --primary: #2b7de1; --green: #00b578; --warn: #d48806;
  --danger: #c0392b; --edit-bg: #fffbe6; --edit-border: #f0e1a8; --line: #e8e8e8;
}
* { box-sizing: border-box; }
body { margin:0; font:13px/1.6 -apple-system,"PingFang SC","Microsoft YaHei",sans-serif; background:#eef1f6; color:#333; }
button { font-family: inherit; }
textarea { resize: vertical; }

/* ===== 顶部导航栏（深蓝） ===== */
.navbar { display:flex; gap:2px; align-items:center; background:var(--nav-blue); color:#fff; padding:0 14px; height:46px; position:sticky; top:0; z-index:50; box-shadow:0 1px 4px rgba(0,0,0,.15); }
.navbar .logo { display:inline-flex; align-items:center; justify-content:center; width:34px; height:26px; background:#fff; color:var(--nav-blue); border-radius:6px; font-weight:800; font-size:13px; margin-right:10px; letter-spacing:.5px; }
.navbar .clinic { font-weight:700; font-size:14px; margin-right:18px; }
.navbar .clinic::after { content:' ▾'; font-size:10px; opacity:.7; }
.navbar a, .navbar span.mod { color:#c9d8ef; text-decoration:none; padding:5px 11px; border-radius:4px; font-size:13px; position:relative; cursor:pointer; }
.navbar a:hover { color:#fff; }
.navbar a.act { background:rgba(255,255,255,.18); color:#fff; font-weight:600; }
.navbar .mod sup { background:#ff4d4f; color:#fff; font-size:9px; border-radius:8px; padding:0 4px; margin-left:2px; }
.navbar .mod.dis { opacity:.55; }
.navbar .right { margin-left:auto; display:flex; align-items:center; gap:10px; }
.navbar .right .ritem { color:#c9d8ef; font-size:12px; cursor:pointer; }
.navbar .right .ritem:hover { color:#fff; }
.navbar .avatar-mini { width:26px; height:26px; border-radius:50%; background:#5b8bd0; display:inline-flex; align-items:center; justify-content:center; font-size:12px; }

/* ===== 次级工具条 ===== */
.subbar { display:flex; align-items:center; gap:8px; background:#fff; padding:8px 14px; border-bottom:1px solid var(--line); position:sticky; top:46px; z-index:40; }
.subbar .search { display:flex; align-items:center; gap:0; }
.subbar .search input { width:220px; padding:6px 10px; border:1px solid #d9d9d9; border-radius:4px 0 0 4px; font:inherit; outline:none; }
.subbar .search input:focus { border-color:var(--primary); }
.btn-jz { background:var(--green); color:#fff; border:0; border-radius:0 4px 4px 0; padding:7px 12px; cursor:pointer; font-size:13px; white-space:nowrap; }
.subbar .crumb { margin-left:10px; font-size:13px; }
.subbar .crumb b { color:#333; font-size:14px; }
.subbar .crumb em { font-style:normal; color:#999; font-size:12px; }
.subbar .rtabs { margin-left:auto; display:flex; align-items:center; gap:2px; }
.subbar .rtabs span { font-size:12px; color:#666; padding:4px 10px; cursor:pointer; border-radius:4px; position:relative; }
.subbar .rtabs span.on { color:var(--primary); font-weight:600; }
.subbar .rtabs span sup { color:#ff4d4f; font-size:10px; }
.subbar .actions { display:flex; gap:6px; margin-left:14px; }

/* ===== 按钮 ===== */
.btn { border:1px solid transparent; border-radius:4px; padding:6px 14px; cursor:pointer; font-size:13px; background:#fff; color:#333; }
.btn.primary { background:var(--primary); color:#fff; }
.btn.primary:hover { background:#1f6bd0; }
.btn.outline-blue { border-color:var(--primary); color:var(--primary); background:#fff; }
.btn.outline { border-color:#d9d9d9; color:#333; background:#fff; }
.btn.green { background:var(--green); color:#fff; }
.btn.gray { background:#f5f5f5; color:#666; border-color:#d9d9d9; }
.btn.red { background:var(--danger); color:#fff; }
.btn.sm { padding:3px 10px; font-size:12px; }
.btn.ghost { background:#fff; color:var(--primary); border:1px solid var(--primary); }

/* ===== 三栏工作区 ===== */
.workspace { display:grid; grid-template-columns: 250px 1fr 310px; gap:0; height:calc(100vh - 88px); }
.col { overflow-y:auto; background:#fff; }
.col.left { border-right:1px solid var(--line); padding:10px 0; }
.col.mid { padding:0 14px 30px; }
.col.right { border-left:1px solid var(--line); padding:10px; }

/* ===== 左栏：患者队列 ===== */
.qhead { display:flex; align-items:center; padding:2px 12px 8px; }
.qhead b { color:var(--primary); font-size:14px; }
.qhead .qtoday { margin-left:12px; color:#999; font-size:12px; }
.qsearch-row { display:flex; gap:6px; padding:0 12px 10px; }
.qsearch-row input { flex:1; min-width:0; padding:6px 9px; border:1px solid #d9d9d9; border-radius:4px; font:inherit; outline:none; }
.qsearch-row input:focus { border-color:var(--primary); }
.qsec { padding:6px 12px 2px; font-size:12px; color:#999; }
.qitem { display:flex; align-items:center; gap:8px; padding:9px 12px; cursor:pointer; border-left:2px solid transparent; }
.qitem:hover { background:#f7f9fc; }
.qitem.sel { background:#e8f1fd; border-left-color:var(--primary); }
.avic { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; color:#fff; flex:none; }
.avic.m { background:#4a90d9; } .avic.f { background:#f0728f; }
.qmain { flex:1; min-width:0; display:flex; flex-direction:column; }
.qmain b { font-size:13px; font-weight:600; }
.qmain .qdoc { font-size:11px; color:#00b578; }
.qright { display:flex; flex-direction:column; align-items:flex-end; gap:2px; }
.qright .qtime { font-size:11px; color:#999; }
.st { font-size:12px; }
.st.waiting, .st.in_visit { color:var(--primary); }
.st.done { color:#bbb; }
.histitem { padding:8px 14px; font-size:12px; border-bottom:1px solid #f5f5f5; }
.histitem b { color:#333; }
/* 小工具 */
.tools { border-top:1px solid var(--line); margin-top:10px; padding:8px 0 0; }
.tools .tt { padding:6px 14px; font-size:13px; color:#333; cursor:pointer; display:flex; align-items:center; gap:8px; }
.tools .tt:hover { background:#f7f9fc; }
.tools .tt .sub { margin-left:auto; font-size:11px; color:#bbb; }
.tools .ticon { width:22px; height:22px; border-radius:5px; display:inline-flex; align-items:center; justify-content:center; font-size:12px; color:#fff; }
.ticon.b1 { background:#4a90d9; } .ticon.b2 { background:#00b578; } .ticon.b3 { background:#f5a623; }

/* ===== 中栏：患者条 ===== */
.ptbar { display:flex; align-items:center; gap:10px; padding:12px 4px; flex-wrap:wrap; border-bottom:1px solid var(--line); }
.ptbar .gavic { width:30px; height:30px; border-radius:50%; background:#f0728f; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:14px; flex:none; }
.ptbar b { font-size:16px; }
.ptbar .meta { color:#666; font-size:13px; }
.ptbar .doc-chip { color:#00b578; font-size:12px; background:#e8faf1; border-radius:3px; padding:2px 8px; }
.ptbar .fee { color:#333; font-size:13px; }
.ptbar .fee b { color:var(--danger); font-size:14px; }
.ptbar .paytype { color:#999; font-size:12px; }
.ptbar .actions { margin-left:auto; display:flex; gap:6px; }

/* ===== 语音问诊条 ===== */
.voicebar { display:flex; gap:10px; align-items:flex-start; background:#f7fafd; border:1px solid #dbe7f5; border-radius:6px; padding:8px; margin:10px 0; }
.vb-left { flex:none; }
.vb-right { flex:1; min-width:0; }

/* ===== 病历表单：标签左 + 黄色编辑区右 ===== */
.frow2 { display:grid; grid-template-columns: 100px 1fr; border-bottom:1px solid #f5eed3; }
.frow2 > .flabel { padding:9px 6px 9px 2px; color:#99a1ad; font-size:12px; text-align:left; line-height:1.4; }
.frow2 > .flabel small { font-size:10px; }
.frow2 > .fval { background:var(--edit-bg); padding:6px 8px; position:relative; }
.frow2 > .fval:focus-within { background:#fff8d9; }
.frow2 .mic-btn { position:absolute; right:8px; top:50%; transform:translateY(-50%); margin:0; }
.frow2 textarea, .frow2 input[type=text], .frow2 input:not([type]) { width:100%; border:0; background:transparent; font:inherit; color:#333; outline:none; padding:2px 0; }
.frow2 .pe3 { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:2px 0; }
.frow2 .pe3 input { width:64px; border:1px solid #e8d99a; background:#fff; border-radius:3px; padding:2px 6px; font:inherit; text-align:center; }
.frow2 .pe3 span { color:#8a6d1a; font-size:12px; }
.frow2 .auxline { display:flex; align-items:center; gap:8px; padding:2px 0; font-size:13px; }
.frow2 .auxline .times { width:56px; text-align:center; border:1px solid #e8d99a; background:#fff; border-radius:3px; padding:2px 4px; font:inherit; }
.frow2 .auxline .price { color:var(--danger); }
.frow2 .auxline .del { color:#bbb; cursor:pointer; font-size:12px; border:0; background:none; padding:0 4px; }
.frow2 .auxline .del:hover { color:var(--danger); }
.frow2 input.dd-input { border:0; background:transparent; font:inherit; outline:none; color:#bbb; }
.frow2 input.dd-input:focus { color:#333; }
/* 中医四诊面板 */
details.tcm { border:1px solid #d9e5dd; border-radius:6px; margin:10px 0; background:#fbfdfc; }
details.tcm summary { cursor:pointer; font-weight:600; font-size:13px; padding:8px 10px; color:var(--nav-blue); }
details.tcm .body { padding:4px 10px 10px; }
.tcmgrp { margin-bottom:8px; }
.tcmgrp > b { font-size:12px; color:#555; display:inline-block; min-width:64px; }
.tcmrow { display:flex; align-items:center; gap:4px; flex-wrap:wrap; margin:3px 0 3px 8px; }
.tcmrow .rl { font-size:11px; color:#888; min-width:56px; }
.chip { display:inline-block; font-size:12px; padding:2px 10px; border-radius:3px; border:1px solid #d3dce6; background:#fff; cursor:pointer; margin:1px 2px; user-select:none; color:#555; }
.chip.on { background:var(--primary); border-color:var(--primary); color:#fff; }
.chip.sym { border-color:#bcd9c8; background:#eefaf4; color:var(--green); cursor:pointer; }

/* ===== 处方区 ===== */
.rx-actions { display:flex; gap:8px; align-items:center; margin:12px 0 8px; flex-wrap:wrap; }
.rx-actions .addsheet { background:#fff; border:1px solid #d9d9d9; border-radius:4px; padding:5px 12px; font-size:13px; cursor:pointer; color:#333; }
.rx-actions .addsheet:hover { border-color:var(--primary); color:var(--primary); }
.rx-actions .right { margin-left:auto; display:flex; gap:8px; }
.rx-actions .tplbtn { background:none; border:0; color:#555; font-size:13px; cursor:pointer; }
.rx-actions .tplbtn:hover { color:var(--primary); }
.rxsheet { margin-bottom:10px; }
.rxsheet .hd { display:flex; align-items:center; gap:10px; padding:6px 0; }
.rxsheet .hd .rxtag { font-weight:600; font-size:13px; }
.rxsheet .hd .free { font-size:11px; color:#999; }
.rxsheet .hd .addrow { margin-left:8px; }
.rxsub { margin-left:auto; font-size:12px; color:var(--danger); font-weight:600; }
table.rx { width:100%; border-collapse:collapse; font-size:12px; }
table.rx th { border:0; border-bottom:1px solid var(--line); padding:6px 5px; color:#99a1ad; font-weight:400; text-align:left; white-space:nowrap; }
table.rx td { border-bottom:1px solid #f5f5f5; padding:4px 5px; vertical-align:middle; }
table.rx tr.hl td { background:#e8f1fd; }
table.rx input, table.rx select { border:0; background:transparent; font:inherit; width:100%; min-width:0; outline:none; }
table.rx input:focus, table.rx select:focus { background:#fff8d9; }
table.rx .drugname { color:var(--primary); }
table.rx .amount { color:#333; white-space:nowrap; }
table.rx .rowdel { color:#bbb; cursor:pointer; font-size:14px; border:0; background:none; }
table.rx .rowdel:hover { color:var(--danger); }
.step { display:inline-flex; align-items:center; gap:1px; }
.step button { border:1px solid #d9d9d9; background:#fff; width:20px; height:22px; line-height:1; cursor:pointer; font-size:12px; border-radius:3px 0 0 3px; padding:0; color:#666; }
.step button + input, .step button + button { border-left:0; border-radius:0; }
.step button:last-child { border-radius:0 3px 3px 0; }
.step input { width:44px; text-align:center; border-top:1px solid #d9d9d9; border-bottom:1px solid #d9d9d9; height:22px; }
.rxtotal-row { display:flex; justify-content:flex-end; padding:4px 8px; font-size:13px; color:#333; }
.rxtotal-row b { color:var(--danger); font-size:15px; }
.dd { position:relative; }
.dd-list { position:absolute; z-index:30; background:#fff; border:1px solid #ddd; border-radius:6px; max-height:220px; overflow:auto; width:100%; box-shadow:0 4px 12px rgba(0,0,0,.12); }
.dd-list div { padding:6px 10px; cursor:pointer; font-size:12px; }
.dd-list div:hover { background:#e8f1fd; }
.mic-btn { background:#eef4fd; border:1px solid #c4d8f2; border-radius:4px; margin-left:4px; padding:4px 7px; cursor:pointer; font-size:13px; line-height:1; }
.mic-btn.rec { background:var(--danger); color:#fff; animation:pulse 1.2s infinite; }
@keyframes pulse { 50% { opacity:.55; } }
.hint { font-size:11px; color:#999; }

/* ===== 右栏：智能诊断（截图1样式） ===== */
.tabs.rtop { display:flex; gap:18px; border-bottom:1px solid var(--line); padding:2px 4px; margin-bottom:6px; }
.tabs.rtop button { border:0; background:none; font-size:14px; color:#666; cursor:pointer; padding:6px 2px; position:relative; }
.tabs.rtop button.on { color:#333; font-weight:700; }
.tabs.rtop button.on::after { content:''; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:var(--primary); }
.aibox { border-bottom:1px solid #f0f0f0; padding:10px 4px; }
.dxname { font-weight:700; font-size:14px; color:#333; margin-bottom:4px; display:flex; align-items:center; gap:6px; }
.dxname .conf { font-size:10px; color:#fff; border-radius:3px; padding:1px 6px; font-weight:400; }
.airow { font-size:12px; margin:4px 0; display:flex; align-items:flex-start; gap:6px; flex-wrap:wrap; }
.airow > b { color:#99a1ad; font-weight:400; flex:none; line-height:22px; }
.pill { display:inline-block; background:#f2f4f7; color:#555; border-radius:3px; padding:2px 8px; font-size:12px; margin:1px 2px 1px 0; }
.chip.sym { margin:1px 2px 1px 0; }
.pill.blue { background:#e8f1fd; color:var(--primary); }
.ai-note { font-size:11px; color:#bbb; padding:8px 4px; }
.pill.redf { background:#fff1f0; color:var(--danger); }

/* ===== 弹窗 ===== */
.modal { position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:100; }
.modal.show { display:flex; }
.modal .box { background:#fff; border-radius:10px; padding:18px; width:380px; max-width:92vw; max-height:88vh; overflow:auto; }
.modal h3 { margin-top:0; font-size:15px; }
.modal label.fl { display:block; margin-bottom:10px; font-size:13px; color:#555; }
.modal label.fl input, .modal label.fl select, .modal label.fl textarea { width:100%; box-sizing:border-box; margin-top:3px; padding:6px 8px; border:1px solid #d9d9d9; border-radius:4px; font:inherit; }

/* ===== 移动端 ===== */
.mview { display:none; gap:6px; margin:6px; }
.mview button { flex:1; }
@media (max-width: 900px) {
  .workspace { display:block; height:auto; }
  .col.left, .col.right { display:none; }
  body[data-view="queue"] .col.left { display:block; }
  body[data-view="dx"] .col.right { display:block; }
  .mview { display:flex; }
  .subbar { flex-wrap:wrap; }
}
</style>
</head>
<body data-view="mid">
<div class="mview" style="display:none">
  <button class="btn gray sm" onclick="document.body.dataset.view='queue'">队列</button>
  <button class="btn primary sm" onclick="document.body.dataset.view='mid'">接诊</button>
  <button class="btn gray sm" onclick="document.body.dataset.view='dx'">AI/历史</button>
</div>

<nav class="navbar">
  <span class="logo">有医帮</span>
  <span class="clinic">仁和堂诊所</span>
  <a class="act" href="reception.php">门诊</a>
  <a href="index.php">医案问诊</a>
  <span class="mod dis">预约</span><span class="mod dis">收费</span><span class="mod dis">药房</span><span class="mod dis">执行</span><span class="mod dis">检查</span><span class="mod dis">库存<sup>1</sup></span><span class="mod dis">患者管理<sup>20</sup></span><span class="mod dis">微信</span><span class="mod dis">管理</span><span class="mod dis">更多 ▾</span><span class="mod dis">商城</span>
  <span class="right">
    <span class="ritem">🛡 医保端</span><span class="ritem">❓ 帮助</span><span class="ritem" onclick="logout()">退出</span>
    <span class="avatar-mini">医</span>
  </span>
</nav>

<div class="subbar">
  <div class="search"><input id="qsearch" placeholder="姓名/手机/项目/单号" oninput="renderQueue()"></div>
  <button class="btn green" style="border-radius:4px" onclick="openNewPt()">＋接诊</button>
  <span class="crumb"><b>门诊</b>　<em>第1次就诊【预约：转诊自康乐诊所】</em></span>
  <div class="rtabs">
    <span>💬 会话</span><span>历史 <sup>5</sup></span><span>影像 <sup>3</sup></span><span class="on">AI诊断</span>
  </div>
  <div class="actions">
    <button class="btn outline-blue sm" onclick="saveOpd()">费用预览</button>
    <button class="btn primary sm" onclick="saveOpd()">保存</button>
    <button class="btn outline sm" onclick="cancelOpd()">取消</button>
    <button class="btn outline sm" onclick="printOpd()">🖨 打印</button>
  </div>
</div>

<div class="workspace">
<!-- 左栏：患者队列 -->
<div class="col left">
  <div class="qhead"><b>门诊</b><span class="qtoday" id="qCount">0</span></div>
  <div class="qsearch-row">
    <input id="qsearch2" placeholder="搜索患者…" oninput="document.getElementById('qsearch').value=this.value;renderQueue()">
    <button class="btn green sm" onclick="openNewPt()">快速接诊</button>
  </div>
  <div class="qsec">今天 · 全部</div>
  <div id="todayList"></div>
  <div class="qsec" style="margin-top:8px">历史就诊</div>
  <div id="histList"></div>
  <div class="tools">
    <div class="tt"><span class="ticon b1">📢</span>叫号<span class="sub">下一位：王莹 ›</span></div>
    <div class="tt"><span class="ticon b2">📊</span>工作汇总<span class="sub">汇总工作报表 ›</span></div>
    <div class="tt"><span class="ticon b3">📅</span>预约看板<span class="sub">患者预约一览 ›</span></div>
  </div>
</div>

<!-- 中栏：病历+处方 -->
<div class="col mid">
  <div class="ptbar" id="ptbar">未选择患者（左侧点击「今天」列表）</div>
  <div id="recForm" style="display:none">
  <!-- 语音问诊横条 -->
  <div class="voicebar" id="voiceBar">
    <div class="vb-left" id="recMount"></div>
    <div class="vb-right">
      <label class="fl" style="display:block;font-size:12px;color:#99a1ad">转写全文（可编辑后再整理）
        <textarea id="f_transcript" rows="3" placeholder="点击左侧麦克风开始问诊录音，转写自动填入此处" style="width:100%;border:1px solid #dbe7f5;border-radius:4px;padding:5px 8px;font:inherit;margin-top:3px;box-sizing:border-box"></textarea></label>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn primary sm" onclick="organizeTranscript('western')">🩺 整理成病历(西医)</button>
        <button class="btn green sm" onclick="organizeTranscript('tcm')">🌿 整理成病历(中医)</button>
        <span id="orgNote" class="hint"></span>
      </div>
    </div>
  </div>

  <!-- 病历表单：标签左 + 黄色编辑区右 -->
  <div class="frow2"><div class="flabel">主诉</div><div class="fval"><textarea id="f_cc" rows="2"></textarea></div></div>
  <div class="frow2"><div class="flabel">现病史/既往史</div><div class="fval"><textarea id="f_pi" rows="3"></textarea></div></div>
  <div class="frow2"><div class="flabel">过敏/个人史</div><div class="fval"><input id="f_al"></div></div>
  <div class="frow2"><div class="flabel">体格检查</div><div class="fval"><div class="pe3">
    <span>体温</span><input id="pe_t" inputmode="decimal" placeholder="35.6">℃
    <span>血压</span><input id="pe_bp" placeholder="105/90">mmhg
    <span>心率</span><input id="pe_hr" inputmode="numeric" placeholder="120">bpm
  </div></div></div>
  <div class="frow2"><div class="flabel">诊断</div><div class="fval"><textarea id="f_dx" rows="2" oninput="diagSearch(this.value)" placeholder="输入可联想诊断字典"></textarea></div></div>
  <div class="frow2"><div class="flabel">辅助检查<small style="color:#bbb">（自动计费）</small></div><div class="fval">
    <div id="auxRows"></div>
    <div class="dd"><input id="f_aux" class="dd-input" placeholder="输入检查项" oninput="auxSearch(this.value)" autocomplete="off"></div>
  </div></div>
  <div class="frow2"><div class="flabel">治疗项目</div><div class="fval">
    <div id="trtRows"></div>
    <div class="dd"><input id="f_trt" class="dd-input" placeholder="输入治疗项" oninput="trtSearch(this.value)" autocomplete="off"></div>
  </div></div>

  <!-- 中医四诊勾选面板 -->
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
        <button class="btn primary sm" onclick="tcmReplaceBlock();this.textContent='✅ 已同步到现病史';setTimeout(()=>this.textContent='📋 同步到现病史',1500)">📋 同步到现病史</button>
        <button class="btn gray sm" onclick="tcmClear()">清空勾选</button>
        <span class="hint">勾选后【保存】时自动写入现病史「四诊摘要」段（替换式，不重复）；也可手动点同步预览</span>
      </div>
    </div>
  </details>

  <div class="rx-actions" id="rxTabs">
    <button class="addsheet" data-t="western">＋ 西药处方</button>
    <button class="addsheet" data-t="infusion">＋ 输液处方</button>
    <button class="addsheet" data-t="chinese">＋ 中药处方</button>
    <div class="right">
      <button class="tplbtn" onclick="applyTemplate()">选择模板</button>
      <button class="tplbtn" onclick="saveTemplate()">保存模板</button>
    </div>
  </div>
  <div id="rxArea"></div>
  <div class="rxtotal-row">合计金额：<b id="grandTotal" style="margin-left:4px">¥0.00</b></div>
  <div class="frow2" style="border-bottom:0"><div class="flabel">注意事项</div><div class="fval"><textarea id="f_notes" rows="2"></textarea></div></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
    <button class="btn primary" onclick="saveOpd()">💾 保存</button>
    <button class="btn green" onclick="finishVisit()">✅ 完成接诊</button>
    <span class="hint" style="align-self:center">AI 调用与打印需先保存</span>
  </div>
  </div>
</div>

<!-- 右栏：智能诊断 / 患者历史 -->
<div class="col right">
  <div class="tabs rtop">
    <button class="on" data-rtab="dx" onclick="rTab(this)">智能诊断</button>
    <button data-rtab="hist" onclick="rTab(this)">患者历史</button>
    <button data-rtab="rec" onclick="rTab(this);loadRecordings()">录音档案</button>
  </div>
  <div id="rtab-dx">
    <div style="display:flex;gap:6px;margin:8px 0;align-items:center;flex-wrap:wrap">
      <button class="btn primary sm" onclick="aiDx('western')">🤖 西医鉴别诊断</button>
      <button class="btn green sm" onclick="aiDx('tcm')">🌿 中医辨证论治</button>
      <button class="btn outline sm" onclick="aiRxReview()">🛡 处方安全审查</button>
    </div>
    <div class="hint" style="margin-bottom:6px">西医按 ICD-10 鉴别诊断；中医按证型辨证论治（含方剂出处）。内容严格依据教材/药典/指南，无出处不编造；仅供参考</div>
    <div id="aiResult"></div>
  </div>
  <div id="rtab-hist" style="display:none"><div id="ptHist">（先选择患者）</div></div>
  <div id="rtab-rec" style="display:none">
    <div class="hint" style="margin:6px 0">问诊录音归档（医疗留档）：录音结束自动上传；逐字稿可修正；行首标注 [医]/[患] 区分说话人</div>
    <div id="recList">（先选择患者）</div>
  </div>
</div>
</div>

<!-- 整理成病历预览确认弹窗 -->
<div class="modal" id="mOrg">
  <div class="box" style="width:520px">
    <h3>🩺 整理成病历（<span id="orgModeLabel">西医</span>标准 · 预览确认）</h3>
    <p class="hint">以下为 AI 整理结果，请核对编辑后确认写入（追加到对应栏，不覆盖已有内容）</p>
    <label class="fl">主诉<textarea id="org_cc" rows="2"></textarea></label>
    <label class="fl">现病史<textarea id="org_pi" rows="4"></textarea></label>
    <label class="fl">四诊摘要（写入体格检查）<textarea id="org_tcm" rows="3"></textarea></label>
    <div style="display:flex;gap:8px"><button class="btn primary" onclick="orgApply()">确认写入病历</button>
    <button class="btn gray" onclick="document.getElementById('mOrg').classList.remove('show')">取消</button></div>
  </div>
</div>

<!-- 快速接诊弹窗 -->
<div class="modal" id="mNew">
  <div class="box">
    <h3>快速接诊（新建患者）</h3>
    <label class="fl">姓名*<input id="np_name"></label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <label class="fl">性别<select id="np_g"><option>男</option><option>女</option><option>未知</option></select></label>
    <label class="fl">年龄<input id="np_age" inputmode="numeric"></label>
    </div>
    <label class="fl">电话<input id="np_phone"></label>
    <label class="fl">过敏史<input id="np_al"></label>
    <label class="fl">挂号费 ¥<input id="np_fee" value="10" inputmode="decimal"></label>
    <div style="display:flex;gap:8px"><button class="btn primary" onclick="createPatient()">挂号并接诊</button>
    <button class="btn gray" onclick="document.getElementById('mNew').classList.remove('show')">取消</button></div>
  </div>
</div>

<!-- 患者档案编辑弹窗 -->
<div class="modal" id="mEdit">
  <div class="box">
    <h3>编辑患者档案</h3>
    <label class="fl">姓名<input id="ep_name"></label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <label class="fl">性别<select id="ep_g"><option>男</option><option>女</option><option>未知</option></select></label>
    <label class="fl">年龄<input id="ep_age" inputmode="numeric"></label>
    </div>
    <label class="fl">电话<input id="ep_phone"></label>
    <label class="fl">过敏史<input id="ep_al"></label>
    <div style="display:flex;gap:8px"><button class="btn primary" onclick="savePatient()">保存档案</button>
    <button class="btn gray" onclick="document.getElementById('mEdit').classList.remove('show')">取消</button></div>
  </div>
</div>

<script>
var YYB_ASR = <?= empty($ASR_ENDPOINTS) ? 'false' : 'true' ?>;
function logout(){ location.href='logout.php'; }
const A = 'api.php?act=';
let CUR = { visit: null, patient: null, record: null, rx: {} };
let Q = { today: [], history: [] };
const $ = id => document.getElementById(id);
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

/* ---------- 语音转文字：给所有文本输入区挂 🎤（不支持的环境自动隐藏） ---------- */
function speechInit(scope) {
  if (!window.speechSupported || !window.speechSupported()) return; // 不支持 MediaRecorder/getUserMedia 的环境隐藏按钮
  window.YYB_ASR_URL = A + 'asr_chunk'; /* 字段级话筒走服务器 ASR 分片转写 */
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
  if (!Object.keys(_tcm).length) return alert('请先勾选四诊项目');
  tcmReplaceBlock();
  $('orgNote') && ($('orgNote').textContent = '');
}
/* 四诊摘要块：替换式写入现病史（幂等，多次保存/点击不重复） */
const TCM_MARK = '【四诊摘要】';
function tcmReplaceBlock() {
  if (!Object.keys(_tcm).length) return;
  const el = $('f_pi');
  let v = el.value.replace(/\s*$/, '');
  const start = v.indexOf(TCM_MARK);
  if (start >= 0) {
    let end = v.length;
    const m = v.slice(start + TCM_MARK.length).match(/\n【/);
    if (m) end = start + TCM_MARK.length + m.index + 1;
    v = (v.slice(0, start) + v.slice(end)).replace(/\s+$/, '');
  }
  el.value = (v ? v + '\n' : '') + TCM_MARK + '\n' + tcmText();
  el.dispatchEvent(new Event('change', { bubbles: true }));
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
  $('qCount').textContent = Q.today.filter(v => !kw || v.name.includes(kw)).length + ' 例';
  $('todayList').innerHTML = Q.today.filter(v => !kw || v.name.includes(kw)).map(v =>
    `<div class="qitem ${CUR.visit === v.id ? 'sel' : ''}" onclick="openVisit(${v.id})">
      <span class="avic ${v.gender === '女' ? 'f' : 'm'}">${v.gender === '女' ? '♀' : '♂'}</span>
      <span class="qmain"><b>${esc(v.name)}</b><span class="qdoc">${esc(v.gender)}/${esc(v.age)}岁</span></span>
      <span class="qright"><span class="qtime">${v.registered_at.slice(11,16) || ''}</span>
      <span class="st ${v.status}">${({waiting:'待诊',in_visit:'就诊中',done:'已诊'})[v.status]}</span></span></div>`).join('')
    || '<p class="hint" style="padding:10px 14px">今日暂无患者，点击「快速接诊」。</p>';
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
  $('ptbar').innerHTML = `<span class="gavic">${p.gender === '女' ? '♀' : '♂'}</span><b>${esc(p.name)}</b>
    <span class="meta">${esc(p.gender)} · ${esc(p.age)}岁 · ${esc(p.phone || p.archive_no || '')}</span>
    <span class="doc-chip">陈志强 - 全科</span><span class="fee">挂号费 <b>¥${(+p.reg_fee).toFixed(2)}</b></span>
    <span class="paytype">自费</span>
    <button class="btn outline sm" title="编辑患者档案" onclick="openEditPt()">✏️</button>
    ${p.case_id ? `<a class="btn outline sm" style="color:#00b578;border-color:#00b578" href="case.php?id=${p.case_id}">进入医案 →</a>` : ''}
    <span class="actions">
      <button class="btn primary sm" onclick="saveOpd()">保存</button>
      <button class="btn outline sm" onclick="cancelOpd()">取消</button>
      <button class="btn outline sm" onclick="printOpd()">🖨 打印</button>
    </span>`;
  $('recForm').style.display = 'block';
  const rec = j.record || {};
  $('f_cc').value = rec.chief_complaint || ''; $('f_pi').value = rec.present_illness || '';
  $('f_al').value = rec.allergy_history || p.allergy || '';
  peFill(rec.physical_exam || '');
  $('f_dx').value = rec.diagnosis || ''; $('f_notes').value = rec.notes || '';
  window._aux = JSON.parse(rec.auxiliary_exam || '[]'); window._trt = JSON.parse(rec.treatments || '[]');
  tcmRestore(rec.tcm_checklist || null);
  renderAux(); renderTrt(); renderRx(); renderHist(j.history);
  $('aiResult').innerHTML = '<p class="hint">未运行。点击上方按钮生成鉴别诊断。</p>';
  window._aiData = {}; window._aiHtml = {}; window._aiAdopted = {}; window._aiVisit = null;
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
  tcmReplaceBlock(); /* 四诊勾选自动写入现病史（替换式，幂等不重复） */
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
    `<div class="auxline">${esc(a.name)} <input class="times" type="number" value="${a.times || 1}" onchange="window._aux[${i}].times=+this.value;calcTotals()"> 次
     <span class="price">¥${(+a.price * (a.times || 1)).toFixed(2)}</span>
     <button class="del" onclick="window._aux.splice(${i},1);renderAux()">✕</button></div>`).join('');
}
function renderTrt() {
  $('trtRows').innerHTML = (window._trt || []).map((a, i) =>
    `<div class="auxline">${esc(a.name)} <input class="times" type="number" value="${a.times || 1}" onchange="window._trt[${i}].times=+this.value;calcTotals()"> 次
     <span class="price">¥${((+a.price || 0) * (+a.times || 1)).toFixed(2)}</span>
     <button class="del" onclick="window._trt.splice(${i},1);renderTrt()">✕</button></div>`).join('');
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
  return `<div class="rxsheet" id="sheet_${t}">
    <div class="hd"><span class="rxtag">${RX_TITLE[t].replace('处方','')}</span><span class="free">已免费</span>
      <button class="btn outline sm addrow" onclick="addRxRow('${t}')">＋ 添加药行</button>
      <span class="rxsub">小计 ¥${sub.toFixed(2)}</span></div>
    ${rxTable(t, items)}
    <div class="rxtotal-row">合计 <b>¥${sub.toFixed(2)}</b></div>
    <div class="dd"><input id="dd_${t}" class="dd-input" placeholder="＋ 输入药名" oninput="drugSearch('${t}')" autocomplete="off" style="width:100%;padding:7px 8px;border:0;background:transparent;font:inherit;outline:none;color:#bbb"></div></div>`;
}
function rxTable(t, items) {
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

/* ---------- AI 智能诊断（中西医双模式；伴随症状 chip 可点击写入病历） ---------- */
function appendSymptom(s) {
  $('f_pi').value = ($('f_pi').value ? $('f_pi').value.replace(/[；;]?\s*$/, '；') : '') + s + '；';
  $('f_pi').dispatchEvent(new Event('change', { bubbles: true }));
  $('f_pi').focus();
}
window._aiData = {}; window._aiHtml = {}; window._aiAdopted = {};
async function aiDx(mode = 'western') {
  if (!CUR.visit) return alert('未选择患者');
  /* 已有该模式结果：直接显示（换患者/重新生成时覆盖） */
  if (window._aiHtml[mode] && window._aiMode === mode && window._aiVisit === CUR.visit) {
    $('aiResult').innerHTML = window._aiHtml[mode];
    return;
  }
  window._aiMode = mode; window._aiVisit = CUR.visit;
  $('aiResult').innerHTML = '<p class="hint">AI 分析中（' + (mode === 'tcm' ? '中医辨证论治' : '西医鉴别诊断') + '标准，依据教材/药典/指南）…</p>';
  const fd = new FormData(); fd.append('visit_id', CUR.visit); fd.append('mode', mode);
  const r = await fetch(A + 'ai_dx', { method: 'POST', body: fd }); const j = await r.json();
  if (!j.ok) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.error || '失败') + '</p>'; return; }
  if (!(j.differentials || []).length) { $('aiResult').innerHTML = '<p class="hint">' + esc(j.note || 'AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）') + '</p>'; return; }
  window._aiData[mode] = j.differentials; window._aiAdopted = {};
  const rxLabel = mode === 'tcm' ? '🌿 采纳(中药方案)' : '✅ 采纳(西药方案)';
  const rxBtnCls = mode === 'tcm' ? 'btn green sm' : 'btn primary sm';
  const html = '<p class="hint" style="padding:4px">⚠️ 仅供参考，处方均标注出处（教材/药典/指南）；点「采纳」将诊断+检查+处方一键写入病历，请核对后保存</p>' + j.differentials.map((d, di) =>
    `<div class="aibox" id="aidx_${mode}_${di}"><div class="dxname">${di + 1}. ${esc(d.diagnosis)}
      <span class="conf" style="background:${d.confidence === '较可能' ? '#00b578' : (d.confidence === '可能' ? '#d48806' : '#c0392b')}">${esc(d.confidence || '可能')}</span></div>
     <div class="airow"><b>依据：</b>${esc(d.supporting || d.pathogenesis || '—')}</div>
     <div class="airow"><b>伴随：</b>${(d.associated_symptoms || []).map(s => `<span class="chip sym" onclick="appendSymptom('${esc(s)}')">＋ ${esc(s)}</span>`).join('') || '—'}</div>
     <div class="airow"><b>查体：</b>${esc(d.physical_findings || '—')}</div>
     <div class="airow"><b>检验：</b>${(d.suggested_tests || []).map(t => `<span class="pill blue">${esc(t)}</span>`).join('') || '—'}</div>
     ${d.formula ? `<div class="airow"><b>方剂：</b><span class="pill blue">${esc(d.formula)}</span><span class="hint">出处：${esc(d.formula_source || '方剂学教材')}</span></div>` : ''}
     ${d.against ? `<div class="airow"><b>不符点：</b>${esc(d.against)}</div>` : ''}
     ${d.red_flags ? `<div class="airow"><b>🚩 转诊指征：</b><span class="pill redf">${esc(d.red_flags)}</span></div>` : ''}
     <div style="margin-top:6px"><button class="${rxBtnCls}" onclick="adoptDx(${di},'${mode}')">${rxLabel}</button></div></div>`).join('')
    + ((j.missing_info || []).length ? `<div class="aibox"><b style="font-size:12px">建议补充采集：</b>${j.missing_info.map(m => `<div style="font-size:12px">· ${esc(m)}</div>`).join('')}</div>` : '')
    + '<p class="ai-note">⚠️ ' + esc(j.disclaimer || 'AI 建议仅供参考，最终诊断以执业医师判断为准。') + '</p>';
  window._aiHtml[mode] = html;
  $('aiResult').innerHTML = html;
}
/* 采纳 AI 诊断：诊断名→诊断栏；查体→体格检查；建议检验→辅助检查；
   处方按 type 生成（western=西药标准 / tcm=中药饮片标准）→ 对应处方签；注意事项→注意事项 */
async function adoptDx(di, type = 'western') {
  if (!CUR.visit) return alert('未选择患者');
  const d = ((window._aiData || {})[type] || [])[di];
  if (!d) return;
  const key = type + '_' + di;
  if (window._aiAdopted && window._aiAdopted[key]) return alert('该诊断的' + (type === 'tcm' ? '中药' : '西药') + '方案已采纳过');
  /* 诊断名：去掉 ICD-10 编码括号 */
  const dxName = (d.diagnosis || '').replace(/[（(][A-Z]\d{2}[^）)]*[）)]/g, '').trim() || (d.diagnosis || '').trim();
  const btn = document.querySelector(`#aidx_${type}_${di} .btn`);
  /* 需求1：处方签已有 AI 生成的药行时，询问清空重写还是追加 */
  const sheet = window['_rx_' + (type === 'tcm' ? 'chinese' : 'western')] || [];
  const hasAI = sheet.some(x => x._ai);
  let clearAI = false;
  if (hasAI) {
    clearAI = confirm('该处方签中已有 AI 生成的药' + (type === 'tcm' ? '味' : '品') + '。\n【确定】清空原有 AI 药行后写入新方案\n【取消】保留原方案，新方案追加在后面');
  }
  if (btn) { btn.textContent = '⏳ 正在生成' + (type === 'tcm' ? '中药' : '西药') + '方案…'; btn.disabled = true; }
  let tp = null, err = '';
  try {
    const fd = new FormData(); fd.append('visit_id', CUR.visit); fd.append('diagnosis', dxName); fd.append('type', type);
    const r = await fetch(A + 'ai_treatment', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) tp = j; else err = j.error || '生成失败';
  } catch (e) { err = '网络异常'; }
  if (!tp) {
    if (btn) { btn.textContent = type === 'tcm' ? '🌿 中药方案' : '✅ 西药方案'; btn.disabled = false; }
    if (!confirm('治疗方案生成失败（' + err + '）。\n仅写入诊断和检查建议，继续吗？')) { window._aiAdopted[key] = undefined; return; }
  }
  if (dxName) appendField('f_dx', dxName);
  const pf = d.physical_findings || d.supporting || '';
  if (pf) appendField('pe_t', pf, true);
  (d.suggested_tests || []).forEach(t => {
    (window._aux = window._aux || []).push({ name: String(t), price: 0, times: 1 });
  });
  let rxCount = 0, extra = '';
  const aiTag = { _ai: true };
  if (tp && type === 'tcm' && (tp.chinese_rx || []).length) {
    _openSheets.chinese = true;
    if (clearAI) window._rx_chinese = (window._rx_chinese || []).filter(x => !x._ai);
    (window._rx_chinese = window._rx_chinese || []);
    if (tp.formula) { appendField('f_dx', '（中医方剂：' + tp.formula + '）'); extra = '，方剂：' + tp.formula; }
    tp.chinese_rx.forEach(x => {
      window._rx_chinese.push({
        drug_id: null, name: String(x.name || ''), qty: String(x.qty_g ?? ''), qty_unit: 'g',
        prep: String(x.prep || ''), decoct: String(x.decoct || ''), price: 0, note: String(x.note || ''),
        source: String(x.source || ''), _ai: true
      });
      rxCount++;
    });
  }
  if (tp && type === 'western' && (tp.western_rx || []).length) {
    _openSheets.western = true;
    if (clearAI) window._rx_western = (window._rx_western || []).filter(x => !x._ai);
    (window._rx_western = window._rx_western || []);
    tp.western_rx.forEach(x => {
      window._rx_western.push({
        drug_id: null, name: String(x.name || ''), spec: String(x.spec || ''),
        usage: String(x.usage || '口服'), freq: (FREQS.includes(x.freq) ? x.freq : 'bid'),
        dose: String(x.dose ?? ''), dose_unit: DOSE_UNITS.includes(x.dose_unit) ? x.dose_unit : '片',
        days: String(x.days ?? 3), qty: String(x.qty ?? 1),
        qty_unit: (QTY_UNITS.western.includes(x.qty_unit) ? x.qty_unit : '盒'),
        price: 0, note: String(x.note || ''), source: String(x.source || ''), _ai: true
      });
      rxCount++;
    });
  }
  if (tp && tp.aux_tests) {
    tp.aux_tests.forEach(a => { (window._aux = window._aux || []).push({ name: String(a.name || ''), price: 0, times: +(a.times || 1) }); });
  }
  if (tp && tp.notes) appendField('f_notes', String(tp.notes));
  renderAux(); renderRx();
  window._aiAdopted = window._aiAdopted || {}; window._aiAdopted[key] = true;
  if (btn) { btn.textContent = '✅ 已采纳'; btn.onclick = null; }
  alert('已采纳「' + dxName + '」（' + (type === 'tcm' ? '中药' : '西药') + '方案）写入病历' + (rxCount ? '，' + rxCount + ' 条药味/药品' : '') + extra + '\n价格为 0 请核对；药味剂量请按患者情况加减');
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
  $('rtab-rec').style.display = btn.dataset.rtab === 'rec' ? 'block' : 'none';
  if (btn.dataset.rtab === 'rec' && CUR.visit) loadRecordings();
}
/* ---------- 录音档案：列表/回放/逐字稿修正 ---------- */
function loadRecordings() {
  if (!CUR.visit) { $('recList').innerHTML = '<p class="hint">（先选择患者）</p>'; return; }
  fetch(A + 'recording_list&visit_id=' + CUR.visit).then(r => r.json()).then(j => {
    if (!j.ok) { $('recList').innerHTML = '<p class="hint">' + esc(j.error || '加载失败') + '</p>'; return; }
    if (!(j.recordings || []).length) { $('recList').innerHTML = '<p class="hint">暂无录音。点上方 🎤 开始问诊录音，结束后自动归档。</p>'; return; }
    $('recList').innerHTML = j.recordings.map(r =>
      `<div class="aibox" style="border-bottom:1px solid #f0f0f0">
        <div style="font-size:12px;color:#99a1ad;margin-bottom:4px">#${r.id} · ${esc((r.created_at || '').slice(0, 16))} · ${r.duration_sec || 0}s</div>
        <audio controls style="width:100%;height:32px" src="${esc(r.url)}"></audio>
        <textarea id="rt_${r.id}" rows="4" style="width:100%;box-sizing:border-box;margin-top:4px;border:1px solid #e0e0e0;border-radius:4px;font:inherit;font-size:12px;padding:4px 6px" placeholder="逐字稿（可修正，行首标注 [医]/[患] 区分说话人）">${esc(r.transcript || '')}</textarea>
        <div style="text-align:right;margin-top:2px"><button class="btn outline sm" onclick="saveRecTranscript(${r.id})">💾 保存逐字稿</button></div>
      </div>`).join('');
  }).catch(() => { $('recList').innerHTML = '<p class="hint">加载失败</p>'; });
}
function saveRecTranscript(rid) {
  const txt = document.getElementById('rt_' + rid).value;
  const fd = new FormData(); fd.append('rec_id', rid); fd.append('transcript', txt);
  fetch(A + 'save_recording_transcript', { method: 'POST', body: fd }).then(r => r.json()).then(j => {
    alert(j.ok ? '逐字稿已保存' : (j.error || '保存失败'));
  });
}
/* 录音结束自动归档上传（由 onStop 回调触发） */
async function uploadRecording(blob, fullText) {
  if (!blob || !CUR.visit) return;
  const fd = new FormData();
  fd.append('visit_id', CUR.visit);
  fd.append('audio', blob, 'visit.webm');
  fd.append('duration', Math.round((window._recDuration || 0)));
  try {
    const r = await fetch(A + 'upload_recording', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
      if (fullText) { const fd2 = new FormData(); fd2.append('rec_id', j.rec_id); fd2.append('transcript', fullText); await fetch(A + 'save_recording_transcript', { method: 'POST', body: fd2 }); }
      loadRecordings();
    }
  } catch (e) {}
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
    asr: { enabled: window.YYB_ASR === true, url: A + 'asr_chunk', wsUrl: 'wss://' + location.host + '/asr-ws' },
    onFinal: function (fin) {
      /* final 结果追加进「转写全文」可编辑框（interim 由组件自身实时区显示） */
      const el = $('f_transcript');
      el.value = (el.value ? el.value.replace(/\s*$/, '') + '\n' : '') + fin;
    },
    onStop: function (fullText, blob) {
      $('orgNote').textContent = '录音结束，可编辑转写后点击「整理成病历」';
      /* 录音归档：整段音频上传 + 转写随存（可回放/修正） */
      if (blob && blob.size > 2000) {
        window._recDuration = (window.REC && REC.durationSec) ? REC.durationSec() : 0;
        uploadRecording(blob, fullText);
      }
    }
  });
  if (!REC) mount.innerHTML = '<span class="hint">当前浏览器不支持语音录音，请手动输入</span>';
}

/* 整理成病历：调 act=organize_transcript，成功弹预览确认框；离线兜底插原始转写 */
async function organizeTranscript(mode = 'western') {
  const transcript = $('f_transcript').value.trim();
  if (!transcript) return alert('请先录音或输入转写内容');
  if (!CUR.visit) return alert('未选择患者');
  $('orgNote').textContent = 'AI 整理中（' + (mode === 'tcm' ? '中医' : '西医') + '标准）…';
  const fd = new FormData();
  fd.append('transcript', transcript);
  fd.append('visit_id', CUR.visit);
  fd.append('mode', mode);
  const r = await fetch(A + 'organize_transcript', { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) {
    $('org_cc').value = j.chief_complaint || '';
    $('org_pi').value = j.present_illness || '';
    $('org_tcm').value = j.tcm_summary || '';
    document.getElementById('orgModeLabel').textContent = (mode === 'tcm' ? '中医' : '西医');
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
