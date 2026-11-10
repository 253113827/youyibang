/* 有医帮 - 录音问诊组件（v3 语音问诊升级）
 * 功能：三态大按钮（空闲🎤/录音中⏹红+脉冲/处理中…）、mm:ss 计时器、
 *       声波纹动画（优先 getUserMedia+AnalyserNode 真波形；失败自动降级伪波形）、
 *       边录边转（interim 实时上屏灰色斜体，final 追加进完整转写）、"已识别 X 字"。
 * 录音数据只留本地转写，不上传音频。
 * 兼容：iOS Safari 14.5+/macOS Safari/Chrome（需 HTTPS）。不支持环境返回 null（回退旧版 speech.js 隐藏按钮逻辑）。
 *
 * 用法：
 *   var inst = Recorder.mount(el, {
 *     compact: false,                 // 紧凑版（case.php）
 *     onFinal: function(text){},      // 每段 final 结果（追加式）
 *     onInterim: function(text){},    // interim 实时结果（组件自身也会显示）
 *     onStop: function(full){}        // 停止时回调完整转写全文
 *   });
 *   inst.start(); inst.stop(); inst.isRecording();
 */
(function () {
  'use strict';
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var AC = window.AudioContext || window.webkitAudioContext;

  function fmt(sec) {
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
  }

  window.Recorder = {
    mount: function (host, opts) {
      opts = opts || {};
      if (!SR) return null; // 环境不支持：不渲染组件，由页面回退旧版隐藏按钮逻辑
      if (host._recorderInst) return host._recorderInst;
      var compact = !!opts.compact;
      var fullText = '';
      var live = false, starting = false, processing = false;
      var rec = null, want = false;
      var timer = null, secs = 0;
      var waveTimer = null, audioCtx = null, analyser = null, stream = null, rafId = null;

      /* ---- DOM ---- */
      var root = document.createElement('div');
      root.className = 'recorder' + (compact ? ' compact' : '');
      root.innerHTML =
        '<div class="rc-main">' +
        '<button type="button" class="rc-btn" aria-label="开始录音">🎤</button>' +
        '<span class="rc-timer">00:00</span>' +
        '<canvas class="rc-wave" width="160" height="36"></canvas>' +
        '<span class="rc-status"></span>' +
        '</div>' +
        '<div class="rc-interim-row"><span class="rc-interim-label">实时转写：</span><span class="rc-interim"></span></div>' +
        '<div class="rc-count hint">已识别 0 字</div>';
      host.appendChild(root);

      var btn = root.querySelector('.rc-btn');
      var timerEl = root.querySelector('.rc-timer');
      var canvas = root.querySelector('.rc-wave');
      var statusEl = root.querySelector('.rc-status');
      var interimEl = root.querySelector('.rc-interim');
      var countEl = root.querySelector('.rc-count');
      var ctx2d = canvas.getContext('2d');

      function setState(mode) {
        root.classList.toggle('recording', mode === 'rec');
        root.classList.toggle('processing', mode === 'proc');
        if (mode === 'rec') { btn.textContent = '⏹'; btn.setAttribute('aria-label', '停止录音'); statusEl.textContent = '录音中'; }
        else if (mode === 'proc') { btn.textContent = '…'; btn.setAttribute('aria-label', '处理中'); statusEl.textContent = '处理中…'; }
        else { btn.textContent = '🎤'; btn.setAttribute('aria-label', '开始录音'); statusEl.textContent = ''; }
      }
      function count() { countEl.textContent = '已识别 ' + fullText.replace(/\s/g, '').length + ' 字'; }
      function setInterim(t) { interimEl.textContent = t || ''; if (t) waveKick(); }

      /* ---- 波形：真波形（AnalyserNode）为主，失败降级伪波形 ---- */
      var bars = [];
      function drawWave() {
        var w = canvas.width, h = canvas.height;
        ctx2d.clearRect(0, 0, w, h);
        var n = bars.length || 24, bw = w / n;
        ctx2d.fillStyle = '#1f6f43';
        for (var i = 0; i < n; i++) {
          var v = bars[i % bars.length] || 0;
          var bh = Math.max(2, v * h * 0.9);
          ctx2d.fillRect(i * bw + 1, (h - bh) / 2, Math.max(1, bw - 2), bh);
        }
      }
      function idleWave() {
        bars = []; for (var i = 0; i < 24; i++) bars.push(0.06);
        drawWave();
      }
      function startRealWave() {
        if (!AC || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { startFakeWave(); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
          stream = s;
          audioCtx = new AC();
          var src = audioCtx.createMediaStreamSource(s);
          analyser = audioCtx.createAnalyser();
          analyser.fftSize = 256;
          src.connect(analyser);
          var data = new Uint8Array(analyser.frequencyBinCount);
          bars = new Array(24).fill(0.06);
          (function loop() {
            if (!live) return;
            rafId = requestAnimationFrame(loop);
            analyser.getByteFrequencyData(data);
            var step = Math.floor(data.length / 24) || 1, j = 0;
            for (var i = 0; i < 24; i++, j += step) bars[i] = Math.min(1, (data[j] || 0) / 200);
            drawWave();
          })();
        }).catch(function () { startFakeWave(); }); /* 麦克风占用/权限冲突 → 伪波形，不挡录音 */
      }
      /* 伪波形：随识别事件与时间随机跳动（iOS Safari 下 SR 独占麦克风时常见降级） */
      function startFakeWave() {
        bars = new Array(24).fill(0.06);
        waveTimer = setInterval(function () {
          if (!live) return;
          for (var i = 0; i < bars.length; i++) bars[i] = 0.08 + Math.random() * 0.7;
          drawWave();
        }, 120);
      }
      function waveKick() { /* interim 到来时伪波形加一拍 */
        if (waveTimer) { for (var i = 0; i < bars.length; i++) bars[i] = 0.2 + Math.random() * 0.8; drawWave(); }
      }
      function stopWave() {
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (waveTimer) { clearInterval(waveTimer); waveTimer = null; }
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        if (audioCtx) { try { audioCtx.close(); } catch (e) {} audioCtx = null; }
        analyser = null;
        idleWave();
      }
      idleWave();

      /* ---- 计时器 ---- */
      function startTimer() {
        secs = 0; timerEl.textContent = '00:00';
        timer = setInterval(function () { secs++; timerEl.textContent = fmt(secs); }, 1000);
      }
      function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

      /* ---- SpeechRecognition：continuous + onend 自动重启 ---- */
      function startSR() {
        want = true;
        rec = new SR();
        rec.lang = 'zh-CN';
        rec.continuous = true;
        rec.interimResults = true;
        rec.onresult = function (ev) {
          var fin = '', interim = '';
          for (var i = ev.resultIndex; i < ev.results.length; i++) {
            if (ev.results[i].isFinal) fin += ev.results[i][0].transcript;
            else interim += ev.results[i][0].transcript;
          }
          if (fin) {
            fullText += fin;
            count();
            if (typeof opts.onFinal === 'function') opts.onFinal(fin, fullText);
          }
          setInterim(interim);
          if (typeof opts.onInterim === 'function') opts.onInterim(interim);
        };
        rec.onend = function () {
          /* 部分浏览器 continuous 会中断：仍在录音态则自动重启 */
          if (want) { setTimeout(function () { try { rec.start(); } catch (e) { hardStop(); } }, 250); }
        };
        rec.onerror = function (ev) {
          if (ev && ev.error === 'not-allowed') { hardStop(); return; }
          /* no-speech / network 等瞬时错误交给 onend 重启逻辑 */
        };
        try { rec.start(); } catch (e) { hardStop(); }
      }
      function hardStop() {
        want = false; live = false;
        stopTimer(); stopWave();
        setState('idle');
        if (typeof opts.onStop === 'function') opts.onStop(fullText);
      }

      btn.onclick = function () {
        if (processing) return;
        if (live) { /* 停止：进入处理中一瞬后复位 */
          processing = true; setState('proc');
          want = false;
          try { rec.stop(); } catch (e) {}
          setTimeout(function () {
            live = false; processing = false;
            stopTimer(); stopWave(); setState('idle');
            interimEl.textContent = '';
            if (typeof opts.onStop === 'function') opts.onStop(fullText);
          }, 200);
          return;
        }
        live = true; fullText = ''; count(); setState('rec');
        startTimer();
        startRealWave(); /* 内部失败自动降级伪波形 */
        startSR();
      };

      var inst = {
        isRecording: function () { return live; },
        text: function () { return fullText; },
        destroy: function () { if (live) btn.onclick(); host._recorderInst = null; }
      };
      host._recorderInst = inst;
      return inst;
    }
  };
})();
