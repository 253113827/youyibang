/* 有医帮 - 语音转文字公共模块（webkitSpeechRecognition）
 * 兼容：macOS Safari 14.1+/Chrome、iPhone Safari iOS 14.5+（需 HTTPS）
 * 不支持的环境自动隐藏 🎤 按钮，不影响手打输入。 */
(function () {
  'use strict';
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  function supported() { return !!SR; }

  /* 在输入框后追加 🎤 按钮；识别结果追加到光标处。
   * target: input/textarea 元素 */
  window.attachSpeech = function (target) {
    if (!supported()) return null; // 环境不支持：不渲染按钮
    if (target._speechBtn) return target._speechBtn;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mic-btn';
    btn.title = '语音输入（普通话）';
    btn.textContent = '🎤';
    btn.setAttribute('aria-label', '语音输入');
    var rec = null, live = false;
    function stop() {
      live = false;
      btn.classList.remove('rec');
      btn.textContent = '🎤';
      try { if (rec) rec.stop(); } catch (e) {}
    }
    btn.onclick = function () {
      if (live) { stop(); return; }
      rec = new SR();
      rec.lang = 'zh-CN';
      rec.continuous = true;
      rec.interimResults = true;
      rec.onresult = function (ev) {
        var fin = '';
        for (var i = ev.resultIndex; i < ev.results.length; i++) {
          if (ev.results[i].isFinal) fin += ev.results[i][0].transcript;
        }
        if (!fin) return;
        var el = target, pos = el.selectionEnd;
        if (typeof pos !== 'number' || pos === null) pos = (el.value || '').length;
        var v = el.value || '';
        el.value = v.slice(0, pos) + fin + v.slice(pos);
        var np = pos + fin.length;
        try { el.setSelectionRange(np, np); } catch (e) {}
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.focus();
      };
      rec.onend = stop;
      rec.onerror = function () { stop(); };
      try { rec.start(); live = true; btn.classList.add('rec'); btn.textContent = '⏹'; } catch (e) { stop(); }
    };
    if (target.nextSibling) target.parentNode.insertBefore(btn, target.nextSibling);
    else target.parentNode.appendChild(btn);
    target._speechBtn = btn;
    return btn;
  };

  /* 批量：给一组元素（或按 id 列表）挂语音按钮 */
  window.initSpeech = function (els) {
    Array.prototype.forEach.call(els, window.attachSpeech);
  };
  window.speechSupported = supported;
})();
