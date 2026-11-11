/* 有医帮 - 字段级语音输入公共模块 v2
 * 方案：MediaRecorder 录音 → 停止后上传 api?act=asr_chunk（服务器 faster-whisper）→ 文本插入光标处
 * 不再依赖 Web Speech API（Chrome 的 SR 需连 Google，国内网络静默失败）
 * 用法：window.attachSpeech(target)（需页面先设 window.YYB_ASR_URL = 'api.php?act=asr_chunk'）
 */
(function () {
  'use strict';
  var AC = window.AudioContext || window.webkitAudioContext;

  function supported() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  }

  window.attachSpeech = function (target) {
    if (target._speechBtn) return target._speechBtn;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mic-btn';
    btn.title = '点击开始语音输入，再点结束并转写';
    btn.textContent = '🎤';
    btn.setAttribute('aria-label', '语音输入');
    var mediaRec = null, stream = null, live = false;

    function setIdle() {
      live = false;
      btn.classList.remove('rec');
      btn.textContent = '🎤';
      btn.title = '点击开始语音输入，再点结束并转写';
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    }
    function insertAtCursor(text) {
      if (!text) return;
      var el = target, pos = el.selectionEnd;
      if (typeof pos !== 'number' || pos === null) pos = (el.value || '').length;
      var v = el.value || '';
      el.value = v.slice(0, pos) + text + v.slice(pos);
      var np = pos + text.length;
      try { el.setSelectionRange(np, np); } catch (e) {}
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.focus();
    }
    btn.onclick = function () {
      if (live) { /* 停止并转写 */
        live = false;
        btn.textContent = '…转写中';
        try { mediaRec.stop(); } catch (e) {}
        return;
      }
      if (!window.YYB_ASR_URL) { alert('语音输入未配置转写服务'); return; }
      navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
        stream = s;
        mediaRec = new MediaRecorder(s);
        var chunks = [];
        mediaRec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
        mediaRec.onstop = function () {
          setIdle();
          var blob = new Blob(chunks, { type: chunks[0] ? chunks[0].type : 'audio/webm' });
          if (blob.size < 2000) { alert('未录到有效语音，请靠近麦克风'); return; }
          btn.textContent = '…转写中'; btn.disabled = true;
          var fd = new FormData();
          fd.append('audio', blob, 'speech.webm');
          fetch(window.YYB_ASR_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              btn.textContent = '🎤'; btn.disabled = false;
              if (j && j.ok && j.text) insertAtCursor(j.text);
              else alert('转写失败：' + ((j && j.error) || '未知错误'));
            })
            .catch(function () { btn.textContent = '🎤'; btn.disabled = false; alert('转写请求失败，请检查网络'); });
        };
        mediaRec.start();
        live = true;
        btn.classList.add('rec');
        btn.textContent = '⏹';
      }).catch(function (e) { alert('麦克风不可用：' + (e && e.name)); });
    };
    if (target.nextSibling) target.parentNode.insertBefore(btn, target.nextSibling);
    else target.parentNode.appendChild(btn);
    target._speechBtn = btn;
    return btn;
  };

  window.initSpeech = function (els) {
    Array.prototype.forEach.call(els, window.attachSpeech);
  };
  window.speechSupported = supported;
})();
