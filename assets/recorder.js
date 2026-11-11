/* 有医帮 - 录音问诊组件（v4 双通道实时转写）
 * 通道1：浏览器内置 Web Speech API（Chrome/Safari，实时 interim 上屏）
 * 通道2：云端分片转写 —— MediaRecorder 每 4s 分片 POST api.php?act=asr_chunk
 *        转发到 OpenAI 兼容 /v1/audio/transcriptions（config.php $ASR_ENDPOINTS 故障切换）
 * 修复：Chrome 内置识别依赖 Google 服务，国内网络静默失败（0 字）——
 *       现在错误会显示在状态栏，且云端分片通道自动接管，文字实时上屏。
 * 录音数据只用于转写，不上传留存。
 * 用法：
 *   var inst = Recorder.mount(el, {
 *     compact: false,                       // 紧凑版（case.php）
 *     asr: { enabled: bool, url: '...' },   // 云端分片转写开关
 *     onFinal: function(text, full){},      // 每段 final 结果（追加式）
 *     onInterim: function(text){},          // interim 实时结果
 *     onStop: function(full){}              // 停止时回调完整转写全文
 *   });
 */
(function () {
  'use strict';
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var AC = window.AudioContext || window.webkitAudioContext;
  var ERRMSG = {
    'not-allowed': '麦克风权限被拒绝：请在浏览器地址栏允许麦克风',
    'service-not-allowed': '浏览器不允许语音识别服务',
    'network': '浏览器识别服务不可达（Chrome 需连接 Google），已切换云端分片转写',
    'no-speech': '未检测到语音，请靠近麦克风说话',
    'audio-capture': '未捕获到麦克风设备'
  };

  function fmt(sec) {
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
  }

  window.Recorder = {
    mount: function (host, opts) {
      opts = opts || {};
      var asrCfg = opts.asr || null;
      if (!SR && !(asrCfg && asrCfg.enabled && window.MediaRecorder)) return null;
      if (host._recorderInst) return host._recorderInst;
      var compact = !!opts.compact;
      var fullText = '';
      var live = false, starting = false, processing = false;
      var rec = null, want = false, srHadText = false, srFatal = false;
      var timer = null, secs = 0;
      var waveTimer = null, audioCtx = null, analyser = null, stream = null, rafId = null;
      var mediaRec = null, asrStream = null, asrBusy = false, asrActive = false;

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
        if (mode === 'rec') { btn.textContent = '⏹'; btn.setAttribute('aria-label', '停止录音'); if (!statusEl.textContent) statusEl.textContent = '识别中…'; }
        else if (mode === 'proc') { btn.textContent = '…'; btn.setAttribute('aria-label', '处理中'); statusEl.textContent = '处理中…'; }
        else { btn.textContent = '🎤'; btn.setAttribute('aria-label', '开始录音'); statusEl.textContent = ''; }
      }
      function count() { countEl.textContent = '已识别 ' + fullText.replace(/\s/g, '').length + ' 字'; }
      function setInterim(t) { interimEl.textContent = t || ''; if (t) waveKick(); }
      function say(msg) { statusEl.textContent = msg; }

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
      function startFakeWave() {
        bars = new Array(24).fill(0.06);
        waveTimer = setInterval(function () {
          if (!live) return;
          for (var i = 0; i < bars.length; i++) bars[i] = 0.08 + Math.random() * 0.7;
          drawWave();
        }, 120);
      }
      function waveKick() {
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

      /* ---- 通道2：云端分片转写（不依赖浏览器 SR，任何网络可用） ---- */
      function appendFinal(t) {
        fullText += t;
        count();
        setInterim('');
        if (typeof opts.onFinal === 'function') opts.onFinal(t, fullText);
      }
      function startChunkASR() {
        if (asrActive) return;
        if (!asrCfg || !asrCfg.enabled) { say('调试: asrCfg 未启用'); return; }
        if (!window.MediaRecorder || !navigator.mediaDevices) { say('当前浏览器不支持录音分片，请改用 Chrome'); return; }
        say('分片通道启动中…');
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
          if (!live) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
          asrStream = s;
          try {
            mediaRec = new MediaRecorder(s);
          } catch (e) { say('录音分片初始化失败：' + e.message); return; }
          mediaRec.ondataavailable = function (e) {
            if (!live || !e.data || !e.data.size) return;
            if (srHadText || asrBusy) return; /* 浏览器识别已出字则不再走分片，避免重复 */
            asrBusy = true;
            var fd = new FormData();
            fd.append('audio', e.data, 'chunk.webm');
            fetch(asrCfg.url, { method: 'POST', body: fd })
              .then(function (r) { return r.json(); })
              .then(function (j) {
                asrBusy = false;
                if (!live) return;
                if (j && j.ok && j.text) {
                  if (srHadText) return;
                  appendFinal(j.text);
                  say('云端分片转写中');
                } else if (j && j.error) {
                  say('分片转写暂不可用（' + j.error + '）');
                }
              })
              .catch(function () { asrBusy = false; });
          };
          mediaRec.start(4000); /* 每 4s 产生一个分片 */
          asrActive = true;
          say('云端分片转写中…');
        }).catch(function () { /* 麦克风被 SR 占用等：静默，SR 仍可用 */ });
      }
      function stopChunkASR() {
        if (mediaRec && mediaRec.state !== 'inactive') { try { mediaRec.stop(); } catch (e) {} }
        mediaRec = null;
        if (asrStream) { asrStream.getTracks().forEach(function (t) { t.stop(); }); asrStream = null; }
        asrActive = false;
      }

      /* ---- 通道1：SpeechRecognition（continuous + onend 自动重启 + 错误可视化） ---- */
      function startSR() {
        if (!SR) return;
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
          if (fin || interim) srHadText = true;
          if (fin) {
            appendFinal(fin);
            say('识别中…');
          }
          setInterim(interim);
          if (typeof opts.onInterim === 'function') opts.onInterim(interim);
        };
        rec.onend = function () {
          if (want && !srFatal) { setTimeout(function () { try { rec.start(); } catch (e) { stopSR(); } }, 250); }
        };
        rec.onerror = function (ev) {
          var code = ev && ev.error;
          if (code === 'not-allowed' || code === 'service-not-allowed') {
            /* SR 单通道失败：只停 SR，云端分片通道继续（不杀整个组件） */
            stopSR();
            if (asrActive) say('浏览器识别不可用，云端分片转写中…');
            else { say(ERRMSG[code] || '浏览器识别不可用'); }
            return;
          }
          if (code === 'network') {
            srFatal = true; /* 不再无限重启 */
            if (asrActive) say(ERRMSG.network);
            else { say('浏览器识别不可达，尝试云端分片转写…'); startChunkASR(); }
            return;
          }
          if (code === 'no-speech' || code === 'audio-capture') say(ERRMSG[code] || code);
        };
        try { rec.start(); } catch (e) { /* SR 启动失败时云端分片仍可工作 */ }
      }
      function stopSR() {
        want = false; srFatal = true;
        if (rec) { try { rec.onend = null; rec.onerror = null; rec.stop(); } catch (e) {} }
        rec = null;
      }
      function hardStop() {
        stopSR();
        live = false;
        stopTimer(); stopWave(); stopChunkASR();
        setState('idle');
        if (typeof opts.onStop === 'function') opts.onStop(fullText);
      }

      btn.onclick = function () {
        if (processing) return;
        if (live) {
          processing = true; setState('proc');
          want = false; srFatal = false;
          try { rec && rec.stop(); } catch (e) {}
          stopChunkASR();
          setTimeout(function () {
            live = false; processing = false;
            stopTimer(); stopWave(); setState('idle');
            interimEl.textContent = '';
            if (typeof opts.onStop === 'function') opts.onStop(fullText);
          }, 200);
          return;
        }
        live = true; fullText = ''; srHadText = false; srFatal = false; asrBusy = false;
        count(); setState('rec');
        startTimer();
        startRealWave();
        startChunkASR(); /* 先启动分片通道：WebKit 中 SR 会独占麦克风，后启动会拿不到设备 */
        startSR();       /* SR 出字则分片自动让位（srHadText 去重） */
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
