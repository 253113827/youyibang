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
        '<label class="rc-far" title="多人/远距离场景：关闭降噪并增益放大远端人声"><input type="checkbox"> 远距增强</label>' +
        '<span class="rc-status"></span>' +
        '</div>' +
        '<div class="rc-interim-row"><span class="rc-interim-label">实时转写：</span><span class="rc-interim"></span></div>' +
        '<div class="rc-count hint">已识别 0 字</div>';
      host.appendChild(root);

      var btn = root.querySelector('.rc-btn');
      var farChk = root.querySelector('.rc-far input');
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
        navigator.mediaDevices.getUserMedia(farConstraints()).then(function (s) {
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

      /* ---- 远距增强（多人/远距离场景）----
       * 原理：浏览器默认 noiseSuppression 会把远距离人声当稳态噪声压掉；
       * 关闭 NS + 数字增益放大 + 动态压缩，可显著提升 1-3 米外人声识别率 */
      function farMode() { return farChk && farChk.checked; }
      function farConstraints() {
        var far = farMode();
        return {
          audio: {
            echoCancellation: true,
            noiseSuppression: !far,        /* 远距模式关降噪，保住远端人声 */
            autoGainControl: true,
            channelCount: 1
          }
        };
      }
      /* 在 ctx 上构建 增益→压缩 链，返回 {input, output}；gain 值随勾选实时变化 */
      function makeBoost(ctx) {
        var gain = ctx.createGain(), comp = ctx.createDynamicsCompressor();
        function apply() {
          gain.gain.value = farMode() ? 6 : 1;   /* 远距模式 +6 倍增益 */
          comp.threshold.value = -50; comp.knee.value = 20;
          comp.ratio.value = 8; comp.attack.value = 0.01; comp.release.value = 0.25;
        }
        apply();
        if (farChk) farChk.addEventListener('change', apply);
        gain.connect(comp);
        return { input: gain, output: comp };
      }

      /* ---- 通道2：云端分片转写（不依赖浏览器 SR，任何网络可用） ---- */
      function appendFinal(t) {
        fullText += t;
        count();
        setInterim('');
        if (typeof opts.onFinal === 'function') opts.onFinal(t, fullText);
      }
      /* ---- 云端分片：WebAudio 采集 PCM，前端切片编码 WAV（绕开 WebKit MediaRecorder 不支持 timeslice 的缺陷） ---- */
      var asrCtx = null, asrProc = null, asrSrc = null, asrBuf = [], asrBufLen = 0, asrMuted = false;
      var CHUNK_SEC = 2, TARGET_RATE = 16000;
      function encodeWAV(f32, rate) {
        /* 线性重采样到 16k + PCM16 WAV 封装 */
        var pcm = f32, ratio = 1;
        if (rate !== TARGET_RATE) {
          ratio = rate / TARGET_RATE;
          var n = Math.floor(f32.length / ratio);
          pcm = new Float32Array(n);
          for (var i = 0; i < n; i++) pcm[i] = f32[Math.floor(i * ratio)];
        }
        var buf = new ArrayBuffer(44 + pcm.length * 2), v = new DataView(buf);
        function wstr(o, s) { for (var i = 0; i < s.length; i++) v.setUint8(o + i, s.charCodeAt(i)); }
        wstr(0, 'RIFF'); v.setUint32(4, 36 + pcm.length * 2, true); wstr(8, 'WAVE');
        wstr(12, 'fmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
        v.setUint32(24, TARGET_RATE, true); v.setUint32(28, TARGET_RATE * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true);
        wstr(36, 'data'); v.setUint32(40, pcm.length * 2, true);
        for (var j = 0; j < pcm.length; j++) { var x = Math.max(-1, Math.min(1, pcm[j])); v.setInt16(44 + j * 2, x < 0 ? x * 0x8000 : x * 0x7FFF, true); }
        return new Blob([buf], { type: 'audio/wav' });
      }
      function uploadChunk(blob) {
        asrBusy = true;
        var fd = new FormData();
        fd.append('audio', blob, 'chunk.wav');
        fetch(asrCfg.url, { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            asrBusy = false;
            if (!live) return;
            if (j && j.ok && j.text) {
              if (srHadText) return;
              appendFinal(j.text);
              say('云端分片转写中…');
            } else if (j && j.error) {
              say('分片转写暂不可用（' + j.error + '）');
              asrMuted = true; /* 上游持续不可用就不再反复上传 */
            }
          })
          .catch(function () { asrBusy = false; });
      }
      function startChunkASR() {
        if (asrActive) return;
        if (!asrCfg || !asrCfg.enabled) return;
        if (!navigator.mediaDevices || !(window.AudioContext || window.webkitAudioContext)) { say('当前浏览器不支持录音分片'); return; }
        say('分片通道启动中…');
        var AC2 = window.AudioContext || window.webkitAudioContext;
        navigator.mediaDevices.getUserMedia(farConstraints()).then(function (s) {
          if (!live) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
          asrStream = s;
          asrCtx = new AC2();
          asrSrc = asrCtx.createMediaStreamSource(s);
          var boost = makeBoost(asrCtx); /* 远距增益链 */
          asrProc = asrCtx.createScriptProcessor(4096, 1, 1);
          var mute = asrCtx.createGain(); mute.gain.value = 0;
          asrBuf = []; asrBufLen = 0; asrMuted = false;
          var target = TARGET_RATE * CHUNK_SEC;
          asrProc.onaudioprocess = function (e) {
            if (!live || srHadText || asrMuted) return;
            var d = e.inputBuffer.getChannelData(0);
            asrBuf.push(new Float32Array(d));
            asrBufLen += d.length;
            if (asrBufLen >= target && !asrBusy) {
              var merged = new Float32Array(asrBufLen), off = 0;
              for (var k = 0; k < asrBuf.length; k++) { merged.set(asrBuf[k], off); off += asrBuf[k].length; }
              asrBuf = []; asrBufLen = 0;
              uploadChunk(encodeWAV(merged, asrCtx.sampleRate));
            }
          };
          asrSrc.connect(boost.input); boost.output.connect(asrProc);
          asrProc.connect(mute); mute.connect(asrCtx.destination);
          asrActive = true;
          say('云端分片转写中…');
        }).catch(function (e) { say('分片通道麦克风失败：' + (e && e.name)); });
      }
      function stopChunkASR() {
        if (asrProc) { try { asrProc.disconnect(); } catch (e) {} asrProc = null; }
        if (asrSrc) { try { asrSrc.disconnect(); } catch (e) {} asrSrc = null; }
        if (asrCtx) { try { asrCtx.close(); } catch (e) {} asrCtx = null; }
        if (asrStream) { asrStream.getTracks().forEach(function (t) { t.stop(); }); asrStream = null; }
        asrBuf = []; asrBufLen = 0;
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
        stopTimer(); stopWave(); stopChunkASR(); stopStreamWS();
        setState('idle');
        if (typeof opts.onStop === 'function') opts.onStop(fullText, window._lastRecBlob || null);
      }

      btn.onclick = function () {
        if (processing) return;
        if (live) {
          processing = true; setState('proc');
          want = false; srFatal = false;
          try { rec && rec.stop(); } catch (e) {}
          stopChunkASR();
          stopStreamWS(); /* 结束流式会话 */
          setTimeout(function () {
            live = false; processing = false;
            stopTimer(); stopWave(); setState('idle');
            interimEl.textContent = '';
            if (typeof opts.onStop === 'function') opts.onStop(fullText, window._lastRecBlob || null);
          }, 200);
          return;
        }
        live = true; fullText = ''; srHadText = false; srFatal = false; asrBusy = false;
        window._recStartAt = Date.now();
        count(); setState('rec');
        startTimer();
        startRealWave();
        startStreamWS(); /* 真流式优先：WebSocket 增量文本实时上屏（走 wss 代理） */
        startChunkASR(); /* 分片通道兜底：流式未出字时启用 */
        startSR();       /* SR 出字则分片自动让位（srHadText 去重） */
      };

      /* ---- 通道0：真流式 WebSocket（R2T2 ws_server，增量文本，亚秒上屏）+ 整段归档录音 ---- */
      var ws = null, wsLive = false, wsAudioCtx = null, wsProc = null, wsSrc = null, wsStream = null, wsSent = 0;
      var archRec = null, archChunks = [];
      function startStreamWS() {
        if (!asrCfg || !asrCfg.wsUrl || !window.WebSocket || !navigator.mediaDevices) return;
        navigator.mediaDevices.getUserMedia(farConstraints()).then(function (s) {
          if (!live) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
          wsStream = s;
          ws.binaryType = 'arraybuffer';
          ws.onopen = function () {
            if (!live) return;
            wsLive = true;
            say('流式转写中…');
            var AC2 = window.AudioContext || window.webkitAudioContext;
            wsAudioCtx = new AC2();
            wsSrc = wsAudioCtx.createMediaStreamSource(s);
            var wboost = makeBoost(wsAudioCtx); /* 远距增益链 */
            wsProc = wsAudioCtx.createScriptProcessor(4096, 1, 1);
            var mute = wsAudioCtx.createGain(); mute.gain.value = 0;
            /* 整段归档录音：录「增益后」的混音流，存档音量与识别一致 */
            try {
              var wsDest = wsAudioCtx.createMediaStreamDestination();
              wboost.output.connect(wsDest);
              archChunks = [];
              archRec = new MediaRecorder(wsDest.stream);
              archRec.ondataavailable = function (e) { if (e.data && e.data.size) archChunks.push(e.data); };
              archRec.onstop = function () {
                if (archChunks.length) {
                  var types = archChunks.map(function (c) { return c.type; });
                  window._lastRecBlob = new Blob(archChunks, { type: types[0] || 'audio/webm' });
                }
                archChunks = [];
              };
              archRec.start();
            } catch (e) { archRec = null; }
            var downCnt = 0;
            wsProc.onaudioprocess = function (e) {
              if (!wsLive) return;
              var d = e.inputBuffer.getChannelData(0);
              /* 48k→16k 简单抽取（1/3）后转 PCM16 */
              var n = Math.floor(d.length / 3), pcm = new Int16Array(n);
              for (var i = 0; i < n; i++) {
                var x = Math.max(-1, Math.min(1, d[i * 3]));
                pcm[i] = x < 0 ? x * 0x8000 : x * 0x7FFF;
              }
              if (ws.readyState === 1) ws.send(pcm.buffer);
            };
            wsSrc.connect(wboost.input); wboost.output.connect(wsProc); wsProc.connect(mute); mute.connect(wsAudioCtx.destination);
          };
          ws.onmessage = function (e) {
            try {
              var j = JSON.parse(e.data);
              if (j && j.text && !srHadText) {
                /* 增量文本追加（流式已提交文本不回改） */
                if (fullText && fullText.slice(-1) !== '\n' && j.text.charAt(0) !== '，' && j.text.charAt(0) !== '。') {
                  /* 直接顺序拼接即可 */
                }
                fullText += j.text;
                count();
                srHadText = true; /* 流式已出字：分片/SR 通道让位 */
                say('流式转写中…');
                if (typeof opts.onStream === 'function') opts.onStream(j.text, fullText);
              }
            } catch (err) {}
          };
          ws.onerror = function () { if (live && !asrActive && !srHadText) say('流式服务不可用，使用分片转写'); };
          ws.onclose = function () { wsLive = false; };
        }).catch(function () {});
      }
      function stopStreamWS() {
        wsLive = false;
        if (archRec && archRec.state !== 'inactive') { try { archRec.stop(); } catch (e) {} }
        archRec = null;
        if (wsProc) { try { wsProc.disconnect(); } catch (e) {} wsProc = null; }
        if (wsSrc) { try { wsSrc.disconnect(); } catch (e) {} wsSrc = null; }
        if (wsAudioCtx) { try { wsAudioCtx.close(); } catch (e) {} wsAudioCtx = null; }
        if (wsStream) { wsStream.getTracks().forEach(function (t) { t.stop(); }); wsStream = null; }
        if (ws) { try { ws.close(); } catch (e) {} ws = null; }
      }

      var inst = {
        isRecording: function () { return live; },
        durationSec: function () { return Math.round((Date.now() - (window._recStartAt || Date.now())) / 1000); },
        text: function () { return fullText; },
        destroy: function () { if (live) btn.onclick(); host._recorderInst = null; }
      };
      host._recorderInst = inst;
      return inst;
    }
  };
})();
