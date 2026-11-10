<?php
require __DIR__ . '/config.php';
need_login();
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM cases WHERE id=?');
$st->execute([$id]);
$case = $st->fetch();
if (!$case) { http_response_code(404); exit('医案不存在'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>医案 #<?= $id ?> <?= htmlspecialchars($case['patient_name']) ?> - 有医帮</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/speech.js"></script>
<script src="assets/recorder.js"></script>
</head>
<body>
<header class="top">
  <a href="index.php" class="logo">← 有医帮</a>
  <span><?= htmlspecialchars($case['patient_name']) ?> · <?= $case['gender'] ?>/<?= htmlspecialchars($case['age']) ?>岁</span>
</header>
<main>
  <div class="case-head card">
    <b>主诉：</b><?= htmlspecialchars($case['chief_complaint'] ?: '（待补充）') ?>
    <time><?= $case['created_at'] ?></time>
  </div>

  <section class="card">
    <h2>问诊对话</h2>
    <div id="chat" class="chat"></div>

    <div id="presetBox" class="preset"></div>

    <div class="send-row">
      <input id="msgInput" placeholder="输入追问内容（可空）">
      <span id="recMountCompact"></span>
      <button id="sendBtn">发送</button>
    </div>
  </section>

  <section class="card">
    <h2>照片</h2>
    <div class="up-row">
      <select id="imgType">
        <option>面色</option><option>舌苔</option><option>化验单</option><option>其他</option>
      </select>
      <label class="filebtn">📷 拍照/上传<input type="file" id="imgInput" accept="image/*" capture="environment" multiple hidden></label>
    </div>
    <div id="imgList" class="imglist"></div>
  </section>

  <section class="card">
    <h2>病案整理</h2>
    <button id="genBtn">🤖 AI 整理病案</button>
    <div id="recView" class="record"></div>
    <details id="recEditBox"><summary>编辑病案</summary>
      <textarea id="recEdit" rows="12"></textarea>
      <button id="recSave">保存</button>
    </details>
    <div id="archStatus"></div>
    <div id="validPanel"></div>
    <div class="send-row" style="flex-wrap:wrap;gap:6px">
      <button id="validateBtn">✅ 规则校验</button>
      <button id="commitBtn">🗄 归档 EMR</button>
      <button id="verifyBtn">🔐 验证哈希链</button>
      <button id="exportBtn">🖨 导出打印版</button>
    </div>
  </section>

  <section class="card">
    <h2>追问与推荐</h2>
    <div class="send-row">
      <input id="askInput" placeholder="如：推荐诊断和方剂的依据是什么？">
      <button id="askBtn">提问</button>
    </div>
    <div id="askOut" class="record"></div>
  </section>
</main>
<p class="footer">本系统输出仅供参考，不构成医疗建议。<a href="javascript:window.print()">打印/导出本页</a></p>
<script>
const CASE_ID = <?= $id ?>;
let PRESET = [];
let lastMsg = 0, lastImg = 0, recVer = 0;
let presetDone = new Set(), busy = false;

const $ = s => document.querySelector(s);
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML;}
function mdLite(s){let h=esc(s);h=h.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');return h.split(/\n/).map(l=>{
  if(/^#{1,4}\s/.test(l))return '<h3>'+l.replace(/^#{1,4}\s/,'')+'</h3>';
  if(/^\s*[-*]\s/.test(l))return '<li>'+l.replace(/^\s*[-*]\s/,'')+'</li>';
  return l?'<p>'+l+'</p>':'';}).join('');}

function addMsg(m){
  const div=document.createElement('div');
  div.className='msg '+m.role;
  div.dataset.mid=m.id;
  div.innerHTML=(m.role==='user'?'<b>医生</b>':'<b>AI</b>')+'<span>'+mdLite(m.content)+'</span><time>'+esc(m.created_at)+'</time>';
  $('#chat').appendChild(div);
  $('#chat').scrollTop=1e9;
}
function renderPreset(){
  if(!PRESET.length||presetDone.size>=PRESET.length){if(PRESET.length)$('#presetBox').innerHTML='<small>预设问诊已完成</small>';return;}
  let i=presetDone.size;
  $('#presetBox').innerHTML='<div class="preset-q">预设 '+(i+1)+'/'+PRESET.length+'：'+esc(PRESET[i])+
   ' <button onclick="sendPreset('+i+')">发送</button> <button class="ghost" onclick="skipPreset('+i+')">跳过</button></div>';
}
window.sendPreset=async i=>{if(busy)return;busy=true;$('#sendBtn').disabled=true;
  await post({act:'send',case_id:CASE_ID,preset_index:i});presetDone.add(i);busy=false;$('#sendBtn').disabled=false;};
window.skipPreset=i=>{presetDone.add(i);renderPreset();};

async function post(data){
  const r=await fetch('api.php?act='+data.act,{method:'POST',body:new URLSearchParams(Object.entries(data).filter(([k])=>k!=='act'))});
  if(r.status===401){location='login.php';return{};}
  return r.json();
}

$('#sendBtn').onclick=async()=>{
  const t=$('#msgInput').value.trim();if(!t||busy)return;busy=true;$('#sendBtn').disabled=true;
  $('#msgInput').value='';
  await post({act:'send',case_id:CASE_ID,text:t});
  busy=false;$('#sendBtn').disabled=false;
};

$('#imgInput').onchange=async()=>{
  const fd=new FormData();fd.append('act','upload');fd.append('case_id',CASE_ID);fd.append('type',$('#imgType').value);
  for(const f of $('#imgInput').files)fd.append('files[]',f);
  $('#imgInput').value='';
  const r=await fetch('api.php?act=upload',{method:'POST',body:fd});
  const j=await r.json();
  if(j.error)alert(j.error);
};

function renderImg(im){
  const d=document.createElement('div');d.className='imgcard';d.id='img'+im.id;
  d.innerHTML='<div class="imgtag">'+esc(im.type)+(im.ai_result?' ✅已解析':'')+'</div><img loading="lazy" src="'+esc(im.path)+'">'+
    '<div class="aiout">'+(im.ai_result?mdLite(im.ai_result):'<button onclick="aiParse('+im.id+')">AI 解析</button>')+'</div>';
  $('#imgList').appendChild(d);
}
window.aiParse=async id=>{
  const d=$('#img'+id);d.querySelector('.aiout').innerHTML='解析中…';
  const j=await post({act:'ai_image',id});
  if(j.error)d.querySelector('.aiout').innerHTML='<span class="err">'+esc(j.error)+'</span> <button onclick="aiParse('+id+')">重试</button>';
  else d.querySelector('.aiout').innerHTML=mdLite(j.ai_result);
};

$('#genBtn').onclick=async()=>{
  $('#genBtn').disabled=true;$('#genBtn').textContent='AI 整理中，请稍候…';
  const j=await post({act:'record',case_id:CASE_ID});
  $('#genBtn').disabled=false;$('#genBtn').textContent='🤖 AI 整理病案';
  if(j.error)alert(j.error);else{recVer=Math.floor(Date.now()/1000);$('#recView').innerHTML=mdLite(j.content_md);$('#recEdit').value=j.content_md;}
};

$('#recSave').onclick=async()=>{
  const j=await post({act:'record_save',case_id:CASE_ID,content_md:$('#recEdit').value});
  if(j.ok){recVer=j.ver;$('#recView').innerHTML=mdLite($('#recEdit').value);alert('已保存');}
  else alert(j.error||'保存失败');
};

$('#askBtn').onclick=async()=>{
  const q=$('#askInput').value.trim();if(!q)return;
  $('#askOut').innerHTML='思考中…';
  const j=await post({act:'ask',case_id:CASE_ID,q});
  $('#askOut').innerHTML=j.error?'<span class="err">'+esc(j.error)+'</span>':mdLite(j.reply);
};

// ---------- 语音问诊（紧凑版录音组件：转写结果追加进对话输入框；不支持则回退 speech.js 隐藏逻辑） ----------
(function () {
  const mount = document.getElementById('recMountCompact');
  const target = document.getElementById('msgInput');
  if (window.Recorder && window.Recorder.mount(mount, {
    compact: true,
    onFinal: function (fin) {
      /* final 结果追加到输入框光标处 */
      const pos = typeof target.selectionEnd === 'number' ? target.selectionEnd : target.value.length;
      target.value = target.value.slice(0, pos) + fin + target.value.slice(pos);
      target.dispatchEvent(new Event('input', { bubbles: true }));
    }
  })) return;
  /* 回退：不支持录音组件 → 旧版隐藏按钮逻辑 */
  const btn = document.createElement('button');
  btn.type = 'button'; btn.id = 'micBtn'; btn.title = '语音输入'; btn.textContent = '🎤';
  mount.replaceWith(btn);
  window.attachSpeech ? window.attachSpeech(target) : (btn.hidden = true);
})();

// ---------- 规则校验面板 ----------
function renderValid(j){
  const p = $('#validPanel');
  const li = (arr, cls) => (arr||[]).map(x=>'<li class="'+cls+'">'+esc(x)+'</li>').join('');
  p.innerHTML = '<ul class="validlist" style="margin:6px 0">'
    + li(j.errors,'err-item') + li(j.warnings,'warn-item') + '</ul>'
    + '<small>' + (j.errors&&j.errors.length ? '<b style="color:#c00">存在阻断项</b>' : (j.warnings&&j.warnings.length?'<b style="color:#8a6d00">存在警告项</b>':'校验通过')) + '</small>';
}
$('#validateBtn').onclick = async () => {
  $('#validPanel').innerHTML = '校验中…';
  const j = await (await fetch('api.php?act=validate&case_id='+CASE_ID)).json();
  if (j.error) $('#validPanel').innerHTML = '<span class="err">'+esc(j.error)+'</span>'; else renderValid(j);
};

// ---------- 归档 / 验证哈希链 / 导出 ----------
$('#commitBtn').onclick=async()=>{
  if(!confirm('将当前病历追加归档到 EMR（不可修改）？'))return;
  const j=await post({act:'commit_emr',case_id:CASE_ID});
  if(j.error)alert(j.error);else{alert('已归档\n内容哈希：'+j.content_hash);refreshStatus();}
};
$('#verifyBtn').onclick=async()=>{
  const j=await (await fetch('api.php?act=verify_emr&case_id='+CASE_ID)).json();
  if(j.error)alert(j.error);
  else alert(j.valid?'✅ 哈希链完整（共 '+j.total+' 条归档）':'❌ 哈希链在第 '+j.broken_at+' 条记录处被篡改！');
};
$('#exportBtn').onclick=()=>window.open('api.php?act=export&case_id='+CASE_ID,'_blank');

// ---------- 归档状态 ----------
let emrState={archived:false};
async function refreshStatus(){
  const j=await (await fetch('api.php?act=validate&case_id='+CASE_ID)).json();
  if(j.error){$('#archStatus').innerHTML='';return;}
  emrState={archived:j.archived};
  $('#archStatus').innerHTML='<small>状态：'+(j.archived?'<b style="color:#080">已归档 🔐</b>':'未归档')+'</small>';
  $('#commitBtn').disabled=j.archived;
  if(j.errors||j.warnings)renderValid(j);
}

// 轮询同步（手机拍照 → 电脑实时出现）
async function poll(){
  try{
    const j=await (await fetch('api.php?act=poll&case_id='+CASE_ID+'&last_msg='+lastMsg+'&last_img='+lastImg+'&rec_ver='+recVer)).json();
    if(j.error)return;
    for(const m of (j.messages||[])){if(m.id>lastMsg){lastMsg=m.id;addMsg(m);}}
    for(const im of (j.images||[])){if(im.id>lastImg){lastImg=im.id;renderImg(im);}}
    if(j.record){$('#recView').innerHTML=mdLite(j.record.content_md);$('#recEdit').value=j.record.content_md;recVer=j.record.ver;emrState={archived:!!j.record.archived};refreshStatus();}
    if(j.preset&&j.preset.length){PRESET=j.preset;renderPreset();}
  }catch(e){}
}
setInterval(poll,3000);poll();refreshStatus();
</script>
</body>
</html>
